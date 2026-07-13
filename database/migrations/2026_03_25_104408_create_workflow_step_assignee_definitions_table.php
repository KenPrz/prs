<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_step_assignee_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_step_definition_id')
                ->constrained('workflow_step_definitions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('assignee_type');
            $table->string('assignee_identifier')->nullable();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['workflow_step_definition_id', 'assignee_type', 'assignee_identifier'],
                'wf_step_assignee_def_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_step_assignee_definitions');
    }
};
