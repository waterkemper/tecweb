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
            $table->timestamp('zd_deleted_at')->nullable()->after('zd_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('zd_tickets', function (Blueprint $table) {
            $table->dropColumn('zd_deleted_at');
        });
    }
};
