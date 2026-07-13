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
        Schema::create('receiving_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receiving_report_id')
                ->constrained('receiving_reports')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('purchase_order_item_id')
                ->constrained('purchase_order_items')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->unsignedInteger('quantity_received');
            $table->unsignedInteger('quantity_rejected')->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receiving_report_items');
    }
};
