<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_ticket_analysis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('zd_tickets')->cascadeOnDelete();
            $table->string('model_version')->nullable();
            $table->text('summary')->nullable();
            $table->string('category')->nullable();
            $table->string('severity')->nullable();
            $table->string('urgency')->nullable();
            $table->decimal('effort_min', 8, 2)->nullable();
            $table->decimal('effort_max', 8, 2)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('extracted_json')->nullable();
            $table->json('next_actions')->nullable();
            $table->string('suggested_owner')->nullable();
            $table->timestamps();
        });

        Schema::table('ai_ticket_analysis', function (Blueprint $table) {
            $table->index('ticket_id');
            $table->index('category');
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_ticket_analysis');
    }
};
