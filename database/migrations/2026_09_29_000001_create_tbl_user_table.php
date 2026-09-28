<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** System users (Owner, Manager, Caretaker). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_user', function (Blueprint $table) {
            $table->increments('user_id');
            $table->string('full_name', 255);
            $table->string('username', 50)->unique();
            $table->string('password_hash', 255);
            $table->enum('role', ['Owner', 'Manager', 'Caretaker']);
            $table->string('contact_no', 15);
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_user');
    }
};
