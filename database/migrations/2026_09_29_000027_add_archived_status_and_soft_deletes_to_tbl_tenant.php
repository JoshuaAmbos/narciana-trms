<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_tenant', function (Blueprint $table) {
            $table->string('status', 50)->default('Active')->change();
            $table->softDeletes()->after('status');
        });

        if (! Schema::hasColumn('tbl_inquiry', 'created_at')) {
            Schema::table('tbl_inquiry', function (Blueprint $table) {
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });

            // Backfill created_at and updated_at on existing inquiries
            DB::table('tbl_inquiry')->whereNull('created_at')->update([
                'created_at' => DB::raw("CONCAT(inquiry_date, ' 00:00:00')"),
                'updated_at' => DB::raw("CONCAT(inquiry_date, ' 00:00:00')"),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('tbl_tenant', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        DB::table('tbl_tenant')->where('status', 'Archived')->update(['status' => 'Inactive']);

        Schema::table('tbl_tenant', function (Blueprint $table) {
            $table->enum('status', ['Active', 'Inactive', 'Blacklisted'])->default('Active')->change();
        });

        if (Schema::hasColumn('tbl_inquiry', 'created_at')) {
            Schema::table('tbl_inquiry', function (Blueprint $table) {
                $table->dropColumn(['created_at', 'updated_at']);
            });
        }
    }
};
