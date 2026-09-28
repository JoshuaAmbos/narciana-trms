<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Business rates and rules set by the Owner (one row). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_setting', function (Blueprint $table) {
            $table->increments('setting_id');
            $table->decimal('water_rate', 10, 2);
            $table->decimal('appliance_fee', 10, 2);
            $table->decimal('kwh_rate', 10, 2);
            $table->integer('grace_days');
            $table->enum('penalty_basis', ['Per day of rent', 'Flat % of total']);
            $table->decimal('penalty_rate', 5, 2);
            $table->integer('refund_days');
            $table->unsignedInteger('updated_by');

            $table->foreign('updated_by')->references('user_id')->on('tbl_user')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_setting');
    }
};
