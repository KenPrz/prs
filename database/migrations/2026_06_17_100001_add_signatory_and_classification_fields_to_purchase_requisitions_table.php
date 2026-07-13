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
        Schema::table('purchase_requisitions', function (Blueprint $table) {
            $table->string('purpose_type')->nullable()->after('price_type');
            $table->string('expected_useful_life')->nullable()->after('purpose_type');
            $table->string('accounting_details')->nullable()->after('expected_useful_life');
            $table->foreignId('received_by_id')
                ->nullable()
                ->after('requestor_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_requisitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_by_id');
            $table->dropColumn(['purpose_type', 'expected_useful_life', 'accounting_details']);
        });
    }
};
