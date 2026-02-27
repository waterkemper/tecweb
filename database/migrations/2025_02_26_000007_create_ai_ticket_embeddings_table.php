<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_ticket_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('zd_tickets')->cascadeOnDelete();
            $table->vector('embedding', 1536);
            $table->timestamps();
        });

        Schema::table('ai_ticket_embeddings', function (Blueprint $table) {
            $table->unique('ticket_id');
        });

        Schema::getConnection()->statement(
            'CREATE INDEX ai_ticket_embeddings_embedding_idx ON ai_ticket_embeddings USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_ticket_embeddings');
    }
};
