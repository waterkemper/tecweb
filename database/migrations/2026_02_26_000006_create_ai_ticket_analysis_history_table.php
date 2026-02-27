<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_ticket_analysis_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('zd_tickets')->cascadeOnDelete();
            $table->string('model_version')->nullable();
            $table->text('summary')->nullable();
            $table->json('bullets')->nullable();
            $table->text('what_reported')->nullable();
            $table->text('what_tried')->nullable();
            $table->text('current_status')->nullable();
            $table->text('open_questions')->nullable();
            $table->json('open_questions_list')->nullable();
            $table->text('next_action')->nullable();
            $table->json('actions_needed_list')->nullable();
            $table->string('pending_action', 32)->nullable();
            $table->string('category')->nullable();
            $table->json('categories')->nullable();
            $table->json('modules')->nullable();
            $table->string('severity')->nullable();
            $table->string('urgency')->nullable();
            $table->boolean('requires_dev')->nullable();
            $table->decimal('effort_min', 8, 2)->nullable();
            $table->decimal('effort_max', 8, 2)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->text('effort_reason')->nullable();
            $table->timestamp('snapshot_at');
        });

        Schema::table('ai_ticket_analysis_history', function (Blueprint $table) {
            $table->index('ticket_id');
            $table->index('snapshot_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_ticket_analysis_history');
    }
};
