<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 9b: a short free-text "details" column on the audit log (e.g. "Water rate: ₱200.00 → ₱250.00",
 * or the backup file name). Nullable, so entries written before this change still load.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_audit_log', function (Blueprint $table) {
            $table->string('details', 500)->nullable()->after('record_id');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_audit_log', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};
