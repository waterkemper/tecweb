<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_similar_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('zd_tickets')->cascadeOnDelete();
            $table->foreignId('similar_ticket_id')->constrained('zd_tickets')->cascadeOnDelete();
            $table->decimal('score', 8, 6);
            $table->text('rationale')->nullable();
            $table->boolean('is_duplicate_candidate')->default(false);
            $table->timestamps();
        });

        Schema::table('ai_similar_tickets', function (Blueprint $table) {
            $table->unique(['ticket_id', 'similar_ticket_id']);
            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_similar_tickets');
    }
};
