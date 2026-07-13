<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a per-line "omission" (short-close) marker: an omitted line is excluded
     * from its document's PDF and from the fulfillment/receiving close calculations.
     */
    public function up(): void
    {
        Schema::table('line_items', function (Blueprint $table) {
            $table->timestamp('omitted_at')->nullable()->after('price');
            $table->string('omit_reason')->nullable()->after('omitted_at');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->timestamp('omitted_at')->nullable()->after('price');
            $table->string('omit_reason')->nullable()->after('omitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('line_items', function (Blueprint $table) {
            $table->dropColumn(['omitted_at', 'omit_reason']);
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['omitted_at', 'omit_reason']);
        });
    }
};
