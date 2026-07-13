<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_requisitions', function (Blueprint $table) {
            $table->foreignId('workflow_id')
                ->nullable()
                ->after('requestor_id')
                ->constrained('workflow_definitions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('workflow_instance_id')
                ->nullable()
                ->after('workflow_id')
                ->constrained('workflow_instances')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_requisitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_instance_id');
            $table->dropConstrainedForeignId('workflow_id');
        });
    }
};
