<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')
                ->constrained('workflow_instances')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('workflow_step_instance_id')
                ->nullable()
                ->constrained('workflow_step_instances')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('workflow_assignment_id')
                ->nullable()
                ->constrained('workflow_assignments')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('actor_user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('action');
            $table->text('comment')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();

            $table->index(['workflow_instance_id', 'workflow_step_instance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_actions');
    }
};
