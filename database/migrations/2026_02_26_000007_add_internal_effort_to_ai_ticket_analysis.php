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
            $table->decimal('internal_effort_min', 8, 2)->nullable()->after('effort_max');
            $table->decimal('internal_effort_max', 8, 2)->nullable()->after('internal_effort_min');
        });
    }

    public function down(): void
    {
        Schema::table('ai_ticket_analysis', function (Blueprint $table) {
            $table->dropColumn(['internal_effort_min', 'internal_effort_max']);
        });
    }
};
