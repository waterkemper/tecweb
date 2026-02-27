<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zd_ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('zd_tickets')->cascadeOnDelete();
            $table->unsignedBigInteger('zd_comment_id');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->text('body')->nullable();
            $table->text('html_body')->nullable();
            $table->boolean('is_public')->default(true);
            $table->json('attachments_json')->nullable();
            $table->timestamps();
        });

        Schema::table('zd_ticket_comments', function (Blueprint $table) {
            $table->unique(['ticket_id', 'zd_comment_id']);
            $table->index('author_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zd_ticket_comments');
    }
};
