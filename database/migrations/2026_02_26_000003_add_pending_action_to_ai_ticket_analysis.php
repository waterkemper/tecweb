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
            $table->string('pending_action', 32)->nullable()->after('next_action');
        });
    }

    public function down(): void
    {
        Schema::table('ai_ticket_analysis', function (Blueprint $table) {
            $table->dropColumn('pending_action');
        });
    }
};
