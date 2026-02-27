<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE zd_tickets ADD COLUMN collaborator_ids jsonb NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE zd_tickets DROP COLUMN IF EXISTS collaborator_ids');
    }
};
