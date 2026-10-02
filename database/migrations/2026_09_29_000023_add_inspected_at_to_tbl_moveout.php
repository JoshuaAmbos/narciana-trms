<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Instructor review (decision 18): the move-out inspection is recorded as done (even when nothing is damaged),
 * and the settlement cannot be computed or approved before it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_moveout', function (Blueprint $table) {
            $table->dateTime('inspected_at')->nullable()->after('actual_date');
        });

        // Settlements already approved, or with items recorded, were inspected on the move-out date.
        DB::table('tbl_moveout')->whereNotNull('actual_date')
            ->where(fn ($q) => $q->where('status', '!=', 'Pending')
                ->orWhereExists(fn ($e) => $e->from('tbl_moveout_item')->whereColumn('tbl_moveout_item.moveout_id', 'tbl_moveout.moveout_id')))
            ->update(['inspected_at' => DB::raw('actual_date')]);
    }

    public function down(): void
    {
        Schema::table('tbl_moveout', function (Blueprint $table) {
            $table->dropColumn('inspected_at');
        });
    }
};
