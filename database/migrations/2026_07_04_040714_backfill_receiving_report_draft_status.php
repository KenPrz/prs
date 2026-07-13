<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Receiving reports gained a real DRAFT status: reports still PENDING that
     * were never submitted to a workflow are drafts.
     */
    public function up(): void
    {
        DB::table('receiving_reports')
            ->where('status', 'PENDING')
            ->whereNull('workflow_instance_id')
            ->update(['status' => 'DRAFT']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('receiving_reports')
            ->where('status', 'DRAFT')
            ->update(['status' => 'PENDING']);
    }
};
