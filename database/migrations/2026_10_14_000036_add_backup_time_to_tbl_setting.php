<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tbl_setting', function (Blueprint $table) {
            $table->time('backup_time')->default('02:00')->after('grace_days');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_setting', function (Blueprint $table) {
            $table->dropColumn('backup_time');
        });
    }
};
