<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_tenant', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('tenant_id');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('last_name', 100)->nullable()->after('middle_name');
            $table->string('name_extension', 10)->nullable()->after('last_name');
            $table->string('full_name', 255)->nullable()->change();
            $table->string('valid_id_type', 60)->change();
            $table->string('other_id_type', 100)->nullable()->after('valid_id_type');

            if (Schema::hasColumn('tbl_tenant', 'prev_building')) {
                $table->dropColumn('prev_building');
            }
        });

        // Backfill first_name and last_name from existing full_name data
        DB::table('tbl_tenant')->where(function ($query) {
            $query->whereNull('first_name')->orWhere('first_name', '');
        })->get()->each(function ($row) {
            $fullName = trim((string) $row->full_name);
            if ($fullName !== '') {
                $parts = preg_split('/\s+/', $fullName, 2);
                DB::table('tbl_tenant')->where('tenant_id', $row->tenant_id)->update([
                    'first_name' => $parts[0] ?? 'Tenant',
                    'last_name' => $parts[1] ?? ($parts[0] ?? 'Unknown'),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tbl_tenant', function (Blueprint $table) {
            $table->string('prev_building', 150)->nullable()->after('occupation');
            $table->dropColumn([
                'first_name',
                'middle_name',
                'last_name',
                'name_extension',
                'other_id_type',
            ]);
        });
    }
};
