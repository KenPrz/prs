<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_step_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')
                ->constrained('workflow_instances')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('name');
            $table->string('step_type');
            $table->string('completion_strategy')->default('ALL');
            $table->string('status')->default('PENDING')->index();
            $table->unsignedSmallInteger('sla_hours')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['workflow_instance_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_step_instances');
    }
};
