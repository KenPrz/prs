<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')
                ->constrained('workflow_definitions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('status')->default('PENDING')->index();
            $table->unsignedSmallInteger('current_step_order')->nullable();
            $table->foreignId('started_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id']);
            $table->index(['workflow_definition_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instances');
    }
};
