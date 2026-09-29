<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Concern;
use App\Models\Lease;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tenant concerns (decision 15).
 *  - Anyone logs and updates; only Owner/Manager set cost and charged_to_tenant.
 *  - Resolved: resolved_at is filled in automatically; back to Open/In progress clears it.
 *  - Once billed (bill_id set) cost, charge and status are locked.
 *  - Audited: Concern logged / updated / resolved / charged.
 */
class ConcernService
{
    /** Longest side of a stored photo, in pixels. */
    public const PHOTO_MAX_SIDE = 1600;

    /** Fields only the Owner/Manager may set. */
    private const MONEY_FIELDS = ['cost', 'charged_to_tenant'];

    public function create(array $data, User $by, ?UploadedFile $photo = null): Concern
    {
        return DB::transaction(function () use ($data, $by, $photo) {
            $data = $this->onlyAllowed($data, $by);
            $data['charged_to_tenant'] = (bool) ($data['charged_to_tenant'] ?? false);
            $data = $this->withResolvedAt($data, null);

            if ($photo) {
                $data['photo_path'] = $this->storePhoto($photo);
            }

            $concern = Concern::create($data);

            AuditLog::record('Concern logged', 'tbl_concern', $concern->concern_id, $by->user_id);
            if ($concern->status === 'Resolved') {
                AuditLog::record('Concern resolved', 'tbl_concern', $concern->concern_id, $by->user_id);
            }
            if ($concern->charged_to_tenant) {
                AuditLog::record('Concern charged', 'tbl_concern', $concern->concern_id, $by->user_id);
            }

            return $concern;
        });
    }

    public function update(Concern $concern, array $data, User $by, ?UploadedFile $photo = null, bool $removePhoto = false): Concern
    {
        return DB::transaction(function () use ($concern, $data, $by, $photo, $removePhoto) {
            $concern = Concern::whereKey($concern->concern_id)->lockForUpdate()->firstOrFail();
            $data = $this->onlyAllowed($data, $by);

            if ($concern->isBilled()) {
                // Billed: the charge on the bill must keep matching the concern.
                foreach (['status', 'cost', 'charged_to_tenant', 'lease_id'] as $field) {
                    unset($data[$field]);
                }
            }
            if (array_key_exists('charged_to_tenant', $data)) {
                $data['charged_to_tenant'] = (bool) $data['charged_to_tenant'];
            }

            $wasResolved = $concern->status === 'Resolved';
            $moneyBefore = [(string) $concern->cost, (bool) $concern->charged_to_tenant];
            $data = $this->withResolvedAt($data, $concern);

            $oldPhoto = $concern->photo_path;
            if ($photo) {
                $data['photo_path'] = $this->storePhoto($photo);
            } elseif ($removePhoto) {
                $data['photo_path'] = null;
            }

            $concern->update($data);

            if ($oldPhoto && $concern->photo_path !== $oldPhoto) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($oldPhoto));
            }

            AuditLog::record('Concern updated', 'tbl_concern', $concern->concern_id, $by->user_id);
            if (! $wasResolved && $concern->status === 'Resolved') {
                AuditLog::record('Concern resolved', 'tbl_concern', $concern->concern_id, $by->user_id);
            }
            if ($moneyBefore !== [(string) $concern->cost, (bool) $concern->charged_to_tenant]) {
                AuditLog::record('Concern charged', 'tbl_concern', $concern->concern_id, $by->user_id);
            }

            return $concern;
        });
    }

    /** Resolved, charged to the tenant, cost > 0 and not billed yet: can be picked for this lease's next bill. */
    public function chargeable(Lease $lease): Collection
    {
        return Concern::where('lease_id', $lease->lease_id)
            ->where('status', 'Resolved')
            ->where('charged_to_tenant', true)
            ->where('cost', '>', 0)
            ->whereNull('bill_id')
            ->orderBy('resolved_at')
            ->get();
    }

    /** How often each category was reported for one room (for the "repeated issue" note). */
    public function repeatedCategories(Collection $concerns): Collection
    {
        return $concerns->countBy('category')->filter(fn (int $n) => $n > 1)->sortDesc();
    }

    // ---------- Photo ----------

    /**
     * Save an uploaded photo: turned upright (camera EXIF orientation), shrunk to at most PHOTO_MAX_SIDE px,
     * stored as JPEG on the public disk with a random name. Returns the path on the disk.
     */
    public function storePhoto(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        $image = match ($file->getMimeType()) {
            'image/png' => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            default => imagecreatefromjpeg($path),
        };

        if ($image === false) {
            throw ValidationException::withMessages(['photo' => 'The photo could not be read. Try taking it again.']);
        }

        $image = $this->upright($image, $path, $file->getMimeType());
        $image = $this->shrink($image);

        $name = 'concerns/'.Str::random(32).'.jpg';
        ob_start();
        imagejpeg($image, null, 82);
        Storage::disk('public')->put($name, ob_get_clean());
        imagedestroy($image);

        return $name;
    }

    private function upright(\GdImage $image, string $path, ?string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }
        $orientation = @exif_read_data($path)['Orientation'] ?? 1;

        return match ((int) $orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    private function shrink(\GdImage $image): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $longest = max($w, $h);
        if ($longest <= self::PHOTO_MAX_SIDE) {
            return $image;
        }

        $scale = self::PHOTO_MAX_SIDE / $longest;

        return imagescale($image, (int) round($w * $scale), (int) round($h * $scale), IMG_BICUBIC);
    }

    // ---------- Helpers ----------

    /** The Caretaker's cost / charged_to_tenant values are ignored (decision 15). */
    private function onlyAllowed(array $data, User $by): array
    {
        return $by->hasRole('Owner', 'Manager') ? $data : array_diff_key($data, array_flip(self::MONEY_FIELDS));
    }

    /** Resolved gets a resolved_at (given or now); any other status clears it. */
    private function withResolvedAt(array $data, ?Concern $concern): array
    {
        $status = $data['status'] ?? $concern?->status ?? 'Open';

        if ($status !== 'Resolved') {
            $data['resolved_at'] = null;
        } elseif (empty($data['resolved_at'])) {
            $data['resolved_at'] = $concern?->resolved_at ?? Carbon::now();
        }

        return $data;
    }
}
