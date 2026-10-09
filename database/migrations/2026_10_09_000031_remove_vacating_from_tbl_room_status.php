<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tbl_room')->where('status', 'Vacating')->update([
            'status' => 'Vacant',
            'expected_vacant_date' => null,
        ]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `tbl_room` MODIFY `status` ENUM('Vacant', 'Occupied') NOT NULL DEFAULT 'Vacant'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `tbl_room` MODIFY `status` ENUM('Vacant', 'Occupied', 'Vacating') NOT NULL DEFAULT 'Vacant'");
        }
    }
};
