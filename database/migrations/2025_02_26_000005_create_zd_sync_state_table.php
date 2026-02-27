<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zd_sync_state', function (Blueprint $table) {
            $table->id();
            $table->string('resource')->unique(); // tickets, users, orgs, events
            $table->text('cursor')->nullable();
            $table->unsignedBigInteger('last_timestamp')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zd_sync_state');
    }
};
