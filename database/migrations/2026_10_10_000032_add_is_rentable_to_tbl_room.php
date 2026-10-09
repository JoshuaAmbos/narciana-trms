<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_room', function (Blueprint $table) {
            $table->boolean('is_rentable')->default(true)->after('status');
            $table->string('disabled_reason', 255)->nullable()->after('is_rentable');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_room', function (Blueprint $table) {
            $table->dropColumn(['is_rentable', 'disabled_reason']);
        });
    }
};
