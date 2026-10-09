<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tbl_moveout_item');
        Schema::dropIfExists('tbl_moveout');
    }

    public function down(): void
    {
        // Standalone move-out subsystem decommissioned.
    }
};
