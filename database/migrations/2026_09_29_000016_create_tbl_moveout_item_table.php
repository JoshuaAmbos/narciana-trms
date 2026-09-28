<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Itemized damage found at move-out. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_moveout_item', function (Blueprint $table) {
            $table->increments('item_id');
            $table->unsignedInteger('moveout_id');
            $table->string('description', 255);
            $table->decimal('cost', 10, 2);

            $table->foreign('moveout_id')->references('moveout_id')->on('tbl_moveout')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_moveout_item');
    }
};
