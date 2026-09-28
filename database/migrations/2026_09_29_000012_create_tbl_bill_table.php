<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Monthly billing statement per lease. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_bill', function (Blueprint $table) {
            $table->increments('bill_id');
            $table->unsignedInteger('lease_id');
            $table->unsignedInteger('reading_id')->nullable();
            $table->date('period_start');
            $table->date('due_date');
            $table->decimal('rent_amount', 10, 2);
            $table->decimal('water_amount', 10, 2)->default(0);
            $table->decimal('electricity_amount', 10, 2)->default(0);
            $table->decimal('appliance_amount', 10, 2)->default(0);
            $table->decimal('penalty_amount', 10, 2)->default(0);
            $table->decimal('other_charges', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['Unpaid', 'Paid', 'Overdue'])->default('Unpaid');
            $table->unsignedInteger('user_id');

            // One bill per lease per billing period.
            $table->unique(['lease_id', 'period_start']);

            $table->foreign('lease_id')->references('lease_id')->on('tbl_lease')->restrictOnDelete();
            $table->foreign('reading_id')->references('reading_id')->on('tbl_meter_reading')->restrictOnDelete();
            $table->foreign('user_id')->references('user_id')->on('tbl_user')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_bill');
    }
};
