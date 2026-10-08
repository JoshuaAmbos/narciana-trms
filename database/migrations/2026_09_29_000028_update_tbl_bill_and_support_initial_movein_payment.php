<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_bill', function (Blueprint $table) {
            $table->index('lease_id');
            $table->dropUnique('tbl_bill_lease_id_period_start_unique');
            $table->boolean('is_initial')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_bill', function (Blueprint $table) {
            $table->dropColumn('is_initial');
            $table->unique(['lease_id', 'period_start']);
            $table->dropIndex(['lease_id']);
        });
    }
};
