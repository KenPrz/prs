<?php

use App\Enums\ReceivingReportStatus;
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
        Schema::dropIfExists('receiving_reports');

        Schema::create('receiving_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('rr_number')
                ->index()
                ->unique();
            $table->foreignId('received_by_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->date('received_date');
            $table->foreignId('workflow_id')
                ->nullable()
                ->constrained('workflow_definitions')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('workflow_instance_id')
                ->nullable()
                ->constrained('workflow_instances')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('status')
                ->default(ReceivingReportStatus::PENDING->value);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receiving_reports');

        Schema::create('receiving_reports', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
};
