<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_ticket_analysis', function (Blueprint $table) {
            $table->json('bullets')->nullable()->after('summary');
            $table->text('what_reported')->nullable()->after('bullets');
            $table->text('what_tried')->nullable()->after('what_reported');
            $table->text('current_status')->nullable()->after('what_tried');
            $table->text('open_questions')->nullable()->after('current_status');
            $table->text('next_action')->nullable()->after('open_questions');
            $table->json('categories')->nullable()->after('category');
            $table->json('modules')->nullable()->after('categories');
            $table->boolean('requires_dev')->nullable()->after('urgency');
            $table->text('effort_reason')->nullable()->after('confidence');
            $table->json('suggested_tags')->nullable()->after('next_actions');
            $table->timestamp('last_ai_refresh_at')->nullable()->after('suggested_owner');
        });
    }

    public function down(): void
    {
        Schema::table('ai_ticket_analysis', function (Blueprint $table) {
            $table->dropColumn([
                'bullets',
                'what_reported',
                'what_tried',
                'current_status',
                'open_questions',
                'next_action',
                'categories',
                'modules',
                'requires_dev',
                'effort_reason',
                'suggested_tags',
                'last_ai_refresh_at',
            ]);
        });
    }
};
