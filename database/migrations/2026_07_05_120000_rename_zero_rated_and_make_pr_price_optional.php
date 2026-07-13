<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** All tables carrying a price_type value. */
    private const PRICE_TYPE_TABLES = [
        'line_items',
        'purchase_order_items',
        'purchase_requisitions',
        'purchase_orders',
    ];

    public function up(): void
    {
        foreach (self::PRICE_TYPE_TABLES as $table) {
            if (Schema::hasColumn($table, 'price_type')) {
                DB::table($table)->where('price_type', 'ZERO_RATED')->update(['price_type' => 'ZERO_VAT']);
            }
        }

        // Requisition line prices are optional — pricing is finalized on the PO.
        Schema::table('line_items', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        foreach (self::PRICE_TYPE_TABLES as $table) {
            if (Schema::hasColumn($table, 'price_type')) {
                DB::table($table)->where('price_type', 'ZERO_VAT')->update(['price_type' => 'ZERO_RATED']);
            }
        }

        Schema::table('line_items', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->nullable(false)->change();
        });
    }
};
