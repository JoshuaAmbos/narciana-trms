<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tenant concerns and maintenance requests. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_concern', function (Blueprint $table) {
            $table->increments('concern_id');
            $table->unsignedInteger('room_id');
            $table->unsignedInteger('lease_id')->nullable();
            $table->dateTime('reported_at');
            $table->enum('channel', ['Verbal', 'Text', 'Group chat']);
            $table->enum('category', ['Maintenance', 'Professional repair', 'Shared area', 'Disturbance', 'Billing', 'Damage']);
            $table->enum('priority', ['Routine', 'Urgent']);
            $table->string('description', 500);
            $table->string('photo_path', 255)->nullable();
            $table->unsignedInteger('assigned_to');
            $table->enum('status', ['Open', 'In progress', 'Resolved'])->default('Open');
            $table->string('resolution', 500)->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->boolean('charged_to_tenant')->default(false);
            $table->dateTime('resolved_at')->nullable();

            $table->foreign('room_id')->references('room_id')->on('tbl_room')->restrictOnDelete();
            $table->foreign('lease_id')->references('lease_id')->on('tbl_lease')->restrictOnDelete();
            $table->foreign('assigned_to')->references('user_id')->on('tbl_user')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_concern');
    }
};
