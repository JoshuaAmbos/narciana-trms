<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tenant profile. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_tenant', function (Blueprint $table) {
            $table->increments('tenant_id');
            $table->string('full_name', 255);
            $table->string('contact_no', 15);
            $table->string('valid_id_type', 50);
            $table->string('valid_id_no', 50);
            $table->dateTime('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_tenant');
    }
};
