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
            $table->foreignId('bill_to_id')->nullable()->constrained('addresses');
            $table->foreignId('ship_to_id')->nullable()->constrained('addresses');
            $table->string('payment_terms')->nullable();
            $table->string('currency', 3)->default('PHP')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['bill_to_id']);
            $table->dropForeign(['ship_to_id']);
            $table->dropColumn(['bill_to_id', 'ship_to_id', 'payment_terms', 'currency']);
        });
    }
};
