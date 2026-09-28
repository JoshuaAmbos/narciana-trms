<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Appliance register for basic rooms. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_appliance', function (Blueprint $table) {
            $table->increments('appliance_id');
            $table->unsignedInteger('lease_id');
            $table->string('appliance_name', 100);
            $table->decimal('monthly_fee', 10, 2);
            $table->date('date_added');
            $table->date('date_removed')->nullable();
            $table->enum('source', ['Declared', 'CCTV']);
            $table->unsignedInteger('user_id');

            $table->foreign('lease_id')->references('lease_id')->on('tbl_lease')->restrictOnDelete();
            $table->foreign('user_id')->references('user_id')->on('tbl_user')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_appliance');
    }
};
