<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decision 15:
 *  - room_id becomes nullable (allowed only for category "Shared area"; checked in ConcernRequest);
 *  - bill_id links a concern charged to the tenant to the bill that charged it, so it is billed only once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_concern', function (Blueprint $table) {
            $table->unsignedInteger('room_id')->nullable()->change();
            $table->unsignedInteger('bill_id')->nullable()->after('charged_to_tenant');

            $table->foreign('bill_id')->references('bill_id')->on('tbl_bill')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_concern', function (Blueprint $table) {
            $table->dropForeign(['bill_id']);
            $table->dropColumn('bill_id');
            $table->unsignedInteger('room_id')->nullable(false)->change();
        });
    }
};
