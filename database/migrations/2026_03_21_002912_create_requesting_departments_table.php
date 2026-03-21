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
        Schema::create('requesting_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pr_id')
                ->constrained('purchase_requisitions')
                ->nullOnDelete();
            $table->foreignId('department_id')
                ->constrained('departments')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requesting_departments');
    }
};
