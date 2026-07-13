<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_step_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')
                ->constrained('workflow_definitions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('name');
            $table->string('step_type');
            $table->string('completion_strategy')->default('ALL');
            $table->unsignedSmallInteger('sla_hours')->nullable();
            $table->json('condition')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['workflow_definition_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_step_definitions');
    }
};
