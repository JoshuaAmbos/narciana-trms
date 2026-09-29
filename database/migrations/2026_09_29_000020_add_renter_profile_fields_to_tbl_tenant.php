<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra fields from the business's Renter's Profile form. Nullable in the database so tenants
 * encoded before this change still load; the form requires provincial_address and occupation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_tenant', function (Blueprint $table) {
            $table->string('provincial_address', 255)->nullable()->after('valid_id_no');
            $table->string('occupation', 100)->nullable()->after('provincial_address');
            $table->string('prev_building', 150)->nullable()->after('occupation');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_tenant', function (Blueprint $table) {
            $table->dropColumn(['provincial_address', 'occupation', 'prev_building']);
        });
    }
};
