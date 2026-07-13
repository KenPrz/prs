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
            $table->string('po_number')->nullable()->unique()->after('purchase_requisition_id');
            $table->date('expected_delivery_date')->nullable()->after('po_number');
            $table->string('status')->nullable()->after('expected_delivery_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['po_number', 'expected_delivery_date', 'status']);
        });
    }
};
