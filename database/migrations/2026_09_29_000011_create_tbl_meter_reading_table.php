<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Electric meter readings for aircon rooms. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_meter_reading', function (Blueprint $table) {
            $table->increments('reading_id');
            $table->unsignedInteger('room_id');
            $table->date('reading_date');
            $table->decimal('previous_reading', 10, 2);
            $table->decimal('current_reading', 10, 2);
            $table->decimal('rate_per_kwh', 10, 2);
            $table->enum('source', ['Meter', 'Davao Light bill']);
            $table->unsignedInteger('user_id');

            $table->foreign('room_id')->references('room_id')->on('tbl_room')->restrictOnDelete();
            $table->foreign('user_id')->references('user_id')->on('tbl_user')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_meter_reading');
    }
};
