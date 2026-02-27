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
            $table->json('open_questions_list')->nullable()->after('open_questions');
        });
    }

    public function down(): void
    {
        Schema::table('ai_ticket_analysis', function (Blueprint $table) {
            $table->dropColumn('open_questions_list');
        });
    }
};
