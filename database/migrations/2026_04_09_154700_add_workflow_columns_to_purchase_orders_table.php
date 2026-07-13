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
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('workflow_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('workflow_definitions')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('workflow_instance_id')
                ->nullable()
                ->after('workflow_id')
                ->constrained('workflow_instances')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('document_id')
                ->nullable()
                ->after('workflow_instance_id')
                ->constrained('documents')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->text('terms_and_conditions')->nullable()->after('status');
            $table->text('remarks')->nullable()->after('terms_and_conditions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_id');
            $table->dropConstrainedForeignId('workflow_instance_id');
            $table->dropConstrainedForeignId('document_id');
            $table->dropColumn(['terms_and_conditions', 'remarks']);
        });
    }
};
