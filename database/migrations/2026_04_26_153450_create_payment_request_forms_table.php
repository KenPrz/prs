<?php

use App\Enums\PaymentRequestFormStatus;
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
        Schema::create('payment_request_forms', function (Blueprint $table) {
            $table->id();
            $table->string('prf_number')
                ->index()
                ->unique();
            $table->foreignId('requestor_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('company_profile_id')->nullable()
                ->constrained('company_profiles')
                ->nullOnDelete();
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('invoice_number')->nullable();
            $table->date('due_date')->nullable();
            $table->date('stamp_date')->nullable();
            $table->string('status')
                ->default(PaymentRequestFormStatus::DRAFT->value);
            $table->foreignId('workflow_id')->nullable()
                ->constrained('workflow_definitions')
                ->nullOnDelete();
            $table->foreignId('workflow_instance_id')->nullable()
                ->constrained('workflow_instances')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_request_forms');
    }
};
