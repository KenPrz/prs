<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('price_type')->nullable()->after('status');
        });

        DB::table('purchase_orders')->orderBy('id')->each(function (object $purchaseOrder) {
            $priceType = DB::table('purchase_order_items')
                ->where('purchase_order_id', $purchaseOrder->id)
                ->orderBy('id')
                ->value('price_type')
                ?? DB::table('purchase_requisitions')
                    ->where('id', $purchaseOrder->purchase_requisition_id)
                    ->value('price_type')
                ?? 'VAT_INCLUSIVE';

            DB::table('purchase_orders')
                ->where('id', $purchaseOrder->id)
                ->update(['price_type' => $priceType]);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('price_type')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('price_type');
        });
    }
};
