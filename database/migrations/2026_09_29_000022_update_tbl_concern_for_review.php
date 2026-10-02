<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Instructor review (decision 18):
 *  - clearer boarding-house categories (old values mapped to the closest new one);
 *  - a second photo (proof it was fixed);
 *  - who reported / started / resolved, for the progress timeline;
 *  - photos move from the public disk to private storage (shown only to logged-in users).
 */
return new class extends Migration
{
    private const NEW = ['Plumbing', 'Electrical', 'Lights', 'Aircon', 'Door/Lock/Keys', 'Water supply', 'Pest control',
        'Noise/Disturbance', 'Cleanliness (shared areas)', 'Damage to room or furniture', 'Billing concern', 'Other'];

    private const OLD = ['Maintenance', 'Professional repair', 'Shared area', 'Disturbance', 'Billing', 'Damage'];

    /** old => new */
    private const MAP = [
        'Maintenance' => 'Other',
        'Professional repair' => 'Other',
        'Shared area' => 'Cleanliness (shared areas)',
        'Disturbance' => 'Noise/Disturbance',
        'Billing' => 'Billing concern',
        'Damage' => 'Damage to room or furniture',
    ];

    public function up(): void
    {
        $this->changeCategories(self::OLD, self::NEW, self::MAP);

        Schema::table('tbl_concern', function (Blueprint $table) {
            $table->string('resolution_photo_path', 255)->nullable()->after('photo_path');
            $table->unsignedInteger('reported_by')->nullable()->after('reported_at');
            $table->dateTime('started_at')->nullable()->after('assigned_to');
            $table->unsignedInteger('started_by')->nullable()->after('started_at');
            $table->unsignedInteger('resolved_by')->nullable()->after('resolved_at');

            $table->foreign('reported_by')->references('user_id')->on('tbl_user')->restrictOnDelete();
            $table->foreign('started_by')->references('user_id')->on('tbl_user')->restrictOnDelete();
            $table->foreign('resolved_by')->references('user_id')->on('tbl_user')->restrictOnDelete();
        });

        // Fill in who logged / resolved existing concerns from the audit log (else: the assigned user).
        foreach (DB::table('tbl_concern')->get() as $c) {
            $who = fn (string $action) => DB::table('tbl_audit_log')->where('table_name', 'tbl_concern')
                ->where('record_id', $c->concern_id)->where('action', $action)->orderBy('log_id')->value('user_id');
            DB::table('tbl_concern')->where('concern_id', $c->concern_id)->update([
                'reported_by' => $who('Concern logged') ?? $c->assigned_to,
                'resolved_by' => $c->status === 'Resolved' ? ($who('Concern resolved') ?? $c->assigned_to) : null,
            ]);
        }

        Schema::table('tbl_concern', function (Blueprint $table) {
            $table->unsignedInteger('reported_by')->nullable(false)->change();
        });

        // Photos: public disk -> private (local) disk, same path.
        foreach (DB::table('tbl_concern')->whereNotNull('photo_path')->pluck('photo_path') as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('local')->put($path, Storage::disk('public')->get($path));
                Storage::disk('public')->delete($path);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('tbl_concern')->whereNotNull('photo_path')->pluck('photo_path') as $path) {
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('public')->put($path, Storage::disk('local')->get($path));
                Storage::disk('local')->delete($path);
            }
        }

        Schema::table('tbl_concern', function (Blueprint $table) {
            $table->dropForeign(['reported_by']);
            $table->dropForeign(['started_by']);
            $table->dropForeign(['resolved_by']);
            $table->dropColumn(['resolution_photo_path', 'reported_by', 'started_at', 'started_by', 'resolved_by']);
        });

        $back = ['Cleanliness (shared areas)' => 'Shared area', 'Noise/Disturbance' => 'Disturbance', 'Billing concern' => 'Billing',
            'Damage to room or furniture' => 'Damage'];
        $back += array_fill_keys(array_diff(self::NEW, array_keys($back)), 'Maintenance');
        $this->changeCategories(self::NEW, self::OLD, $back);
    }

    /** Widen the enum to both lists, convert the rows, then narrow it to the new list. */
    private function changeCategories(array $from, array $to, array $map): void
    {
        $enum = fn (array $values) => "ENUM('".implode("','", array_map(fn ($v) => str_replace("'", "''", $v), $values))."') NOT NULL";

        DB::statement('ALTER TABLE tbl_concern MODIFY category '.$enum(array_values(array_unique(array_merge($from, $to)))));
        foreach ($map as $old => $new) {
            DB::table('tbl_concern')->where('category', $old)->update(['category' => $new]);
        }
        DB::statement('ALTER TABLE tbl_concern MODIFY category '.$enum($to));
    }
};
