<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Move-out notice and settlement. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_moveout', function (Blueprint $table) {
            $table->increments('moveout_id');
            $table->unsignedInteger('lease_id');
            $table->date('notice_date');
            $table->date('planned_date');
            $table->date('actual_date')->nullable();
            $table->boolean('early_departure')->default(false);
            $table->decimal('unpaid_balance', 10, 2)->default(0);
            $table->decimal('damage_total', 10, 2)->default(0);
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->decimal('excess_amount', 10, 2)->default(0);
            $table->date('refund_due_date')->nullable();
            $table->enum('status', ['Pending', 'Refunded', 'Excess billed', 'Settled'])->default('Pending');
            $table->unsignedInteger('user_id');

            $table->foreign('lease_id')->references('lease_id')->on('tbl_lease')->restrictOnDelete();
            $table->foreign('user_id')->references('user_id')->on('tbl_user')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_moveout');
    }
};
