<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_tenant', function (Blueprint $table) {
            $table->enum('status', ['Active', 'Inactive', 'Blacklisted'])->default('Active')->after('prev_building');
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });

        Schema::table('tbl_lease', function (Blueprint $table) {
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_tenant', function (Blueprint $table) {
            $table->dropColumn(['status', 'updated_at']);
        });

        Schema::table('tbl_lease', function (Blueprint $table) {
            $table->dropColumn(['created_at', 'updated_at']);
        });
    }
};
