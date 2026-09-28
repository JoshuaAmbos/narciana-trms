<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Signed MOA (Contract of Rent) reference details. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_lease', function (Blueprint $table) {
            $table->increments('lease_id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('room_id');
            $table->date('moa_date');
            $table->date('move_in_date');
            $table->integer('due_day');
            $table->decimal('monthly_rent', 10, 2);
            $table->integer('min_stay_months');
            $table->decimal('advance_amount', 10, 2);
            $table->decimal('deposit_maintenance', 10, 2);
            $table->decimal('deposit_damage', 10, 2);
            $table->decimal('deposit_bills', 10, 2);
            $table->decimal('deposit_keys', 10, 2);
            $table->enum('status', ['Active', 'Ended', 'Cancelled'])->default('Active');
            $table->unsignedInteger('user_id');

            $table->foreign('tenant_id')->references('tenant_id')->on('tbl_tenant')->restrictOnDelete();
            $table->foreign('room_id')->references('room_id')->on('tbl_room')->restrictOnDelete();
            $table->foreign('user_id')->references('user_id')->on('tbl_user')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_lease');
    }
};
