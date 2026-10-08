<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_setting', function (Blueprint $table) {
            $table->decimal('standard_basic_rent', 10, 2)->default(3000.00)->after('appliance_fee');
            $table->decimal('standard_aircon_rent', 10, 2)->default(3600.00)->after('standard_basic_rent');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_setting', function (Blueprint $table) {
            $table->dropColumn(['standard_basic_rent', 'standard_aircon_rent']);
        });
    }
};
