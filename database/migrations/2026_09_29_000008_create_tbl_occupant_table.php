<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Declared occupants per lease (for water billing). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_occupant', function (Blueprint $table) {
            $table->increments('occupant_id');
            $table->unsignedInteger('lease_id');
            $table->string('full_name', 255);
            $table->boolean('is_principal');
            $table->date('date_added');
            $table->date('date_removed')->nullable();

            $table->foreign('lease_id')->references('lease_id')->on('tbl_lease')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_occupant');
    }
};
