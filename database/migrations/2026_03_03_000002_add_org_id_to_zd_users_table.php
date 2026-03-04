<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zd_users', function (Blueprint $table) {
            $table->unsignedBigInteger('org_id')->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('zd_users', function (Blueprint $table) {
            $table->dropColumn('org_id');
        });
    }
};
