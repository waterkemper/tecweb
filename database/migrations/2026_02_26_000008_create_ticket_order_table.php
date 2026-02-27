<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('zd_tickets')->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();

            $table->unique('ticket_id');
            $table->index('sequence');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_order');
    }
};
