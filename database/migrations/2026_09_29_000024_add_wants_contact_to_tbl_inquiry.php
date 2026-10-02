<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instructor review (decision 19): "Wants to be contacted" when a room becomes available.
 * Existing inquiries start unticked (0); no inquiry data is changed or removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_inquiry', function (Blueprint $table) {
            $table->boolean('wants_contact')->default(false)->after('via_representative');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_inquiry', function (Blueprint $table) {
            $table->dropColumn('wants_contact');
        });
    }
};
