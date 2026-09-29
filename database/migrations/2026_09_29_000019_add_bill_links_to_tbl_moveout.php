<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decision 16: a settlement knows its final bill (last month's utilities) and its excess bill
 * (what the tenant still owes after the deposit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_moveout', function (Blueprint $table) {
            $table->unsignedInteger('final_bill_id')->nullable()->after('refund_due_date');
            $table->unsignedInteger('excess_bill_id')->nullable()->after('final_bill_id');

            $table->foreign('final_bill_id')->references('bill_id')->on('tbl_bill')->nullOnDelete();
            $table->foreign('excess_bill_id')->references('bill_id')->on('tbl_bill')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_moveout', function (Blueprint $table) {
            $table->dropForeign(['final_bill_id']);
            $table->dropForeign(['excess_bill_id']);
            $table->dropColumn(['final_bill_id', 'excess_bill_id']);
        });
    }
};
