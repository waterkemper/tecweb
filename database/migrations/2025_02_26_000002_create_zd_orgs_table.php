<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zd_orgs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('zd_id')->unique();
            $table->string('name')->nullable();
            $table->json('domain_names')->nullable();
            $table->json('tags')->nullable();
            $table->json('custom_fields')->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamps();
        });

        Schema::table('zd_orgs', function (Blueprint $table) {
            $table->index('zd_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zd_orgs');
    }
};
