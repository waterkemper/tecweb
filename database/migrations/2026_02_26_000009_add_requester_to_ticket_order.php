<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_order', function (Blueprint $table) {
            $table->unsignedBigInteger('requester_id')->nullable()->after('ticket_id');
        });

        DB::statement('
            UPDATE ticket_order
            SET requester_id = (SELECT requester_id FROM zd_tickets WHERE zd_tickets.id = ticket_order.ticket_id)
        ');

        Schema::table('ticket_order', function (Blueprint $table) {
            $table->index(['requester_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_order', function (Blueprint $table) {
            $table->dropIndex(['requester_id', 'sequence']);
            $table->dropColumn('requester_id');
        });
    }
};
