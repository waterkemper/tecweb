<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zd_tickets', function (Blueprint $table) {
            $table->timestamp('due_at')->nullable()->after('closed_at');
        });

        Schema::table('zd_tickets', function (Blueprint $table) {
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('zd_tickets', function (Blueprint $table) {
            $table->dropIndex(['due_at']);
        });

        Schema::table('zd_tickets', function (Blueprint $table) {
            $table->dropColumn('due_at');
        });
    }
};
