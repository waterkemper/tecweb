<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zd_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('zd_id')->unique();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('role')->nullable();
            $table->string('external_id')->nullable();
            $table->string('locale')->nullable();
            $table->string('timezone')->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamps();
        });

        Schema::table('zd_users', function (Blueprint $table) {
            $table->index('zd_id');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zd_users');
    }
};
