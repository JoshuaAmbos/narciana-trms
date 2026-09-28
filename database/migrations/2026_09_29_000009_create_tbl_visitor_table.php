<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Visitor log (MOA two-day visitor rule). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_visitor', function (Blueprint $table) {
            $table->increments('visitor_id');
            $table->unsignedInteger('lease_id');
            $table->string('visitor_name', 255);
            $table->enum('relationship', ['Parent', 'Sibling', 'Spouse']);
            $table->date('arrival_date');
            $table->date('departure_date')->nullable();

            $table->foreign('lease_id')->references('lease_id')->on('tbl_lease')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_visitor');
    }
};
