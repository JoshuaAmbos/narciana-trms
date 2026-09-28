<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Payments received. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_payment', function (Blueprint $table) {
            $table->increments('payment_id');
            $table->unsignedInteger('bill_id');
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->enum('mode', ['Cash', 'GCash']);
            $table->string('gcash_ref_no', 30)->nullable();
            $table->string('ack_no', 20)->unique();
            $table->unsignedInteger('user_id');

            $table->foreign('bill_id')->references('bill_id')->on('tbl_bill')->restrictOnDelete();
            $table->foreign('user_id')->references('user_id')->on('tbl_user')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_payment');
    }
};
