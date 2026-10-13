<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_inquiry', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('inquiry_id');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('last_name', 100)->nullable()->after('middle_name');
            $table->string('prospect_name', 255)->nullable()->change();
        });

        // Backfill first_name and last_name from existing prospect_name
        DB::table('tbl_inquiry')->where(function ($query) {
            $query->whereNull('first_name')->orWhere('first_name', '');
        })->get()->each(function ($row) {
            $name = trim((string) $row->prospect_name);
            if ($name !== '') {
                $parts = preg_split('/\s+/', $name, 2);
                DB::table('tbl_inquiry')->where('inquiry_id', $row->inquiry_id)->update([
                    'first_name' => $parts[0] ?? 'Prospect',
                    'last_name' => $parts[1] ?? ($parts[0] ?? 'Inquirer'),
                ]);
            } else {
                DB::table('tbl_inquiry')->where('inquiry_id', $row->inquiry_id)->update([
                    'first_name' => 'Prospect',
                    'last_name' => 'Inquirer',
                ]);
            }
        });

        // Alter first_name and last_name to be required (not null)
        Schema::table('tbl_inquiry', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable(false)->change();
            $table->string('last_name', 100)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_inquiry', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'middle_name', 'last_name']);
            $table->string('prospect_name', 255)->nullable(false)->change();
        });
    }
};
