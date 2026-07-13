<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('purchase_requisitions', 'received_by_id')) {
            Schema::table('purchase_requisitions', function (Blueprint $table) {
                $table->renameColumn('received_by_id', 'to_be_ordered_by_id');
            });
        }

        if (Schema::hasColumn('purchase_requisitions', 'accounting_details')) {
            Schema::table('purchase_requisitions', function (Blueprint $table) {
                $table->dropColumn('accounting_details');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_requisitions', 'to_be_ordered_by_id')) {
            Schema::table('purchase_requisitions', function (Blueprint $table) {
                $table->renameColumn('to_be_ordered_by_id', 'received_by_id');
            });
        }

        if (! Schema::hasColumn('purchase_requisitions', 'accounting_details')) {
            Schema::table('purchase_requisitions', function (Blueprint $table) {
                $table->string('accounting_details')->nullable()->after('expected_useful_life');
            });
        }
    }
};
