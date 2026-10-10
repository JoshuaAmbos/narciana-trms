<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_setting', function (Blueprint $table) {
            $table->integer('default_room_capacity')->default(2)->after('standard_aircon_rent');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_setting', function (Blueprint $table) {
            $table->dropColumn('default_room_capacity');
        });
    }
};
