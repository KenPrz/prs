<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_events', function (Blueprint $table) {
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
            $table->string('event_type');
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_events');
    }
};
