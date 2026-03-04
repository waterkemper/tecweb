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
            $table->index(['status', 'priority'], 'zd_tickets_status_priority_idx');
            $table->index(['requester_id', 'zd_updated_at'], 'zd_tickets_requester_updated_idx');
            $table->index(['org_id', 'zd_updated_at'], 'zd_tickets_org_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::table('zd_tickets', function (Blueprint $table) {
            $table->dropIndex('zd_tickets_status_priority_idx');
            $table->dropIndex('zd_tickets_requester_updated_idx');
            $table->dropIndex('zd_tickets_org_updated_idx');
        });
    }
};
