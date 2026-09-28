<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Record of important user actions. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_audit_log', function (Blueprint $table) {
            $table->increments('log_id');
            $table->unsignedInteger('user_id');
            $table->string('action', 100);
            $table->string('table_name', 50);
            $table->integer('record_id');
            $table->dateTime('logged_at')->useCurrent();

            $table->foreign('user_id')->references('user_id')->on('tbl_user')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_audit_log');
    }
};
