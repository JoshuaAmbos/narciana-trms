<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Prospective tenant inquiries. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_inquiry', function (Blueprint $table) {
            $table->increments('inquiry_id');
            $table->string('prospect_name', 255);
            $table->string('contact_no', 15);
            $table->enum('preferred_type', ['Aircon', 'Basic', 'Any']);
            $table->integer('no_of_occupants');
            $table->enum('channel', ['Walk-in', 'Call']);
            $table->boolean('via_representative')->default(false);
            $table->date('inquiry_date');
            $table->enum('status', ['Waiting', 'Rented', 'Referred', 'Declined'])->default('Waiting');
            $table->unsignedInteger('room_id')->nullable();
            $table->unsignedInteger('user_id');
            $table->string('remarks', 255)->nullable();

            $table->foreign('room_id')->references('room_id')->on('tbl_room')->restrictOnDelete();
            $table->foreign('user_id')->references('user_id')->on('tbl_user')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_inquiry');
    }
};
