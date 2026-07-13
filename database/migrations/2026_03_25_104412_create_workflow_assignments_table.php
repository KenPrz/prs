<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_step_instance_id')
                ->constrained('workflow_step_instances')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('source_type');
            $table->string('source_identifier')->nullable();
            $table->string('status')->default('PENDING')->index();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('reassigned_from_user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('overridden_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamp('overridden_at')->nullable();
            $table->timestamps();

            $table->unique(['workflow_step_instance_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_assignments');
    }
};
