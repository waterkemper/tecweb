<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zd_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('zd_id')->unique();
            $table->string('subject')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->nullable();
            $table->string('priority')->nullable();
            $table->string('type')->nullable();
            $table->unsignedBigInteger('requester_id')->nullable();
            $table->unsignedBigInteger('submitter_id')->nullable();
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedBigInteger('org_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('form_id')->nullable();
            $table->json('tags')->nullable();
            $table->json('custom_fields')->nullable();
            $table->json('via')->nullable();
            $table->timestamp('solved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('satisfaction_rating')->nullable();
            $table->boolean('ai_needs_refresh')->default(true);
            $table->json('raw_json')->nullable();
            $table->timestamps();
        });

        Schema::table('zd_tickets', function (Blueprint $table) {
            $table->index('zd_id');
            $table->index('status');
            $table->index('org_id');
            $table->index('assignee_id');
            $table->index('group_id');
            $table->index('created_at');
            $table->index('updated_at');
            $table->index('ai_needs_refresh');
        });

        Schema::getConnection()->statement(
            "CREATE INDEX zd_tickets_search_idx ON zd_tickets USING GIN (to_tsvector('simple', coalesce(subject,'') || ' ' || coalesce(description,'')))"
        );
    }

    public function down(): void
    {
        Schema::getConnection()->statement('DROP INDEX IF EXISTS zd_tickets_search_idx');
        Schema::dropIfExists('zd_tickets');
    }
};
