<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decision 14: a GCash reference number can be used for only one payment.
 * (Index only; Cash payments keep gcash_ref_no = NULL, and MySQL allows many NULLs in a unique index.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_payment', function (Blueprint $table) {
            $table->unique('gcash_ref_no');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_payment', function (Blueprint $table) {
            $table->dropUnique(['gcash_ref_no']);
        });
    }
};
