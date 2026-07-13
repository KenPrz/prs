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
        Schema::create('line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_requisition_id')
                ->constrained('purchase_requisitions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('name');
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
        Schema::dropIfExists('line_items');
    }
};
