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
            $table->foreignId('pr_id')
                ->nullable()
                ->constrained('purchase_requisitions')
                ->nullOnDelete();
            $table->string('code');
            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('line_item_units')
                ->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['pr_id', 'code']);
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
