<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zd_tickets', function (Blueprint $table) {
            $table->timestamp('zd_created_at')->nullable()->after('raw_json');
            $table->timestamp('zd_updated_at')->nullable()->after('zd_created_at');
        });

        // Backfill from raw_json for existing tickets
        $rows = DB::table('zd_tickets')->whereNotNull('raw_json')->get(['id', 'raw_json']);
        foreach ($rows as $row) {
            $raw = is_string($row->raw_json) ? json_decode($row->raw_json, true) : $row->raw_json;
            if (! is_array($raw)) {
                continue;
            }
            $updates = [];
            if (! empty($raw['created_at'])) {
                $updates['zd_created_at'] = Carbon::parse($raw['created_at']);
            }
            if (! empty($raw['updated_at'])) {
                $updates['zd_updated_at'] = Carbon::parse($raw['updated_at']);
            }
            if (! empty($updates)) {
                DB::table('zd_tickets')->where('id', $row->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        Schema::table('zd_tickets', function (Blueprint $table) {
            $table->dropColumn(['zd_created_at', 'zd_updated_at']);
        });
    }
};
