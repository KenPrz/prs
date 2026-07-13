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
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('line_item_id')
                ->constrained('line_items')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->foreignId('unit_id')
                ->constrained('item_units')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->decimal('price', 12, 2);
            $table->string('price_type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
