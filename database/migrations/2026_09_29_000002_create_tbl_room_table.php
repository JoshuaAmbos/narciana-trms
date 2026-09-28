<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rentable rooms and their availability. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_room', function (Blueprint $table) {
            $table->increments('room_id');
            $table->string('room_no', 10)->unique();
            $table->enum('room_type', ['Aircon', 'Basic']);
            $table->boolean('is_metered');
            $table->integer('max_occupants')->default(2);
            $table->decimal('monthly_rent', 10, 2);
            $table->enum('status', ['Vacant', 'Occupied', 'Vacating'])->default('Vacant');
            $table->date('expected_vacant_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_room');
    }
};
