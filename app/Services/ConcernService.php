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
 * Tenant concerns (decisions 15 and 18).
 *  - Anyone logs and updates; only Owner/Manager set cost and charged_to_tenant.
 *  - Timeline: who reported, who started (In progress) and who resolved, with the times.
 *  - Two private photos: proof of the problem and proof it was fixed.
 *  - Once billed (bill_id set) cost, charge and status are locked.
 *  - Audited: Concern logged / started / updated / resolved / charged.
 */
class ConcernService
{
    /** Longest side of a stored photo, in pixels. */
    public const PHOTO_MAX_SIDE = 1600;

    /** Fields only the Owner/Manager may set. */
    private const MONEY_FIELDS = ['cost', 'charged_to_tenant'];

    /** Private disk for concern photos (storage/app/private/concerns, decision 18). */
    public const PHOTO_DISK = 'local';

    /**
     * Log a concern. $photos: ['photo' => proof of the problem, 'resolution_photo' => proof it was fixed]
     * (UploadedFile or null).
     */
    public function create(array $data, User $by, array $photos = []): Concern
    {
        return DB::transaction(function () use ($data, $by, $photos) {
            $data = $this->onlyAllowed($data, $by);
            $data['charged_to_tenant'] = (bool) ($data['charged_to_tenant'] ?? false);
            $data['reported_by'] = $by->user_id;
            $data = $this->withProgress($data, null, $by);

            foreach (Concern::PHOTO_KINDS as $kind => $column) {
                if ($file = $photos[$this->field($kind)] ?? null) {
                    $data[$column] = $this->storePhoto($file);
                }
            }

            $concern = Concern::create($data);

            AuditLog::record('Concern logged', 'tbl_concern', $concern->concern_id, $by->user_id);
            if ($concern->status === 'In progress') {
                AuditLog::record('Concern started', 'tbl_concern', $concern->concern_id, $by->user_id);
            }
            if ($concern->status === 'Resolved') {
                AuditLog::record('Concern resolved', 'tbl_concern', $concern->concern_id, $by->user_id);
            }
            if ($concern->charged_to_tenant) {
                AuditLog::record('Concern charged', 'tbl_concern', $concern->concern_id, $by->user_id);
            }

            return $concern;
        });
    }

    /**
     * Update a concern. $photos may hold 'photo' / 'resolution_photo' (new uploads) and
     * 'remove_photo' / 'remove_resolution_photo' (true = delete the current one).
     */
    public function update(Concern $concern, array $data, User $by, array $photos = []): Concern
    {
        return DB::transaction(function () use ($concern, $data, $by, $photos) {
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

            $statusBefore = $concern->status;
            $moneyBefore = [(string) $concern->cost, (bool) $concern->charged_to_tenant];
            $data = $this->withProgress($data, $concern, $by);

            $oldPhotos = [];
            foreach (Concern::PHOTO_KINDS as $kind => $column) {
                $field = $this->field($kind);
                if ($file = $photos[$field] ?? null) {
                    $data[$column] = $this->storePhoto($file);
                } elseif ($photos['remove_'.$field] ?? false) {
                    $data[$column] = null;
                }
                $oldPhotos[$column] = $concern->{$column};
            }

            $concern->update($data);

            foreach ($oldPhotos as $column => $old) {
                if ($old && $concern->{$column} !== $old) {
                    DB::afterCommit(fn () => Storage::disk(self::PHOTO_DISK)->delete($old));
                }
            }

            AuditLog::record('Concern updated', 'tbl_concern', $concern->concern_id, $by->user_id);
            if ($statusBefore === 'Open' && $concern->status === 'In progress') {
                AuditLog::record('Concern started', 'tbl_concern', $concern->concern_id, $by->user_id);
            }
            if ($statusBefore !== 'Resolved' && $concern->status === 'Resolved') {
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
     * stored as JPEG on the private disk with a random name. Returns the path on the disk.
     */
    public function storePhoto(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if (! function_exists('imagecreatefromjpeg')) {
            $name = 'concerns/'.Str::random(32).'.jpg';
            Storage::disk(self::PHOTO_DISK)->put($name, file_get_contents($path));

            return $name;
        }

        $image = match ($file->getMimeType()) {
            'image/png' => @imagecreatefrompng($path),
            default => @imagecreatefromjpeg($path),
        };

        if ($image === false) {
            throw ValidationException::withMessages(['photo' => 'The photo could not be read. Try taking it again.']);
        }

        $image = $this->upright($image, $path, $file->getMimeType());
        $image = $this->shrink($image);

        $name = 'concerns/'.Str::random(32).'.jpg';
        ob_start();
        imagejpeg($image, null, 82);
        Storage::disk(self::PHOTO_DISK)->put($name, ob_get_clean());
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

    /**
     * Progress stamps for the timeline (decision 18):
     *  - In progress: started_at / started_by set the first time (kept when later resolved);
     *  - Resolved: resolved_at (given or now) and resolved_by;
     *  - going back clears the later steps (back to Open clears all; back to In progress clears Resolved).
     */
    private function withProgress(array $data, ?Concern $concern, User $by): array
    {
        $status = $data['status'] ?? $concern?->status ?? 'Open';

        if ($status === 'Open') {
            $data['started_at'] = null;
            $data['started_by'] = null;
        } elseif ($status === 'In progress' && ! $concern?->started_at) {
            $data['started_at'] = Carbon::now();
            $data['started_by'] = $by->user_id;
        }

        if ($status !== 'Resolved') {
            $data['resolved_at'] = null;
            $data['resolved_by'] = null;
        } else {
            if (empty($data['resolved_at'])) {
                $data['resolved_at'] = $concern?->resolved_at ?? Carbon::now();
            }
            $data['resolved_by'] = $concern?->status === 'Resolved' ? $concern->resolved_by : $by->user_id;
        }

        return $data;
    }

    /** reported -> photo, resolved -> resolution_photo (the form field names). */
    private function field(string $kind): string
    {
        return $kind === 'reported' ? 'photo' : 'resolution_photo';
    }
}
