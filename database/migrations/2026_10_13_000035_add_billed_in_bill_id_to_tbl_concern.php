<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_concern', function (Blueprint $table) {
            if (! Schema::hasColumn('tbl_concern', 'billed_in_bill_id')) {
                $table->unsignedInteger('billed_in_bill_id')->nullable()->after('bill_id');
                $table->foreign('billed_in_bill_id')->references('bill_id')->on('tbl_bill')->nullOnDelete();
            }
            if (! Schema::hasColumn('tbl_concern', 'repair_cost')) {
                $table->decimal('repair_cost', 10, 2)->nullable()->after('cost');
            }
            if (! Schema::hasColumn('tbl_concern', 'charge_to_tenant')) {
                $table->boolean('charge_to_tenant')->default(false)->after('charged_to_tenant');
            }
        });

        DB::statement('UPDATE tbl_concern SET billed_in_bill_id = bill_id, repair_cost = cost, charge_to_tenant = charged_to_tenant');
    }

    public function down(): void
    {
        Schema::table('tbl_concern', function (Blueprint $table) {
            if (Schema::hasColumn('tbl_concern', 'billed_in_bill_id')) {
                $table->dropForeign(['billed_in_bill_id']);
                $table->dropColumn('billed_in_bill_id');
            }
            if (Schema::hasColumn('tbl_concern', 'repair_cost')) {
                $table->dropColumn('repair_cost');
            }
            if (Schema::hasColumn('tbl_concern', 'charge_to_tenant')) {
                $table->dropColumn('charge_to_tenant');
            }
        });
    }
};
