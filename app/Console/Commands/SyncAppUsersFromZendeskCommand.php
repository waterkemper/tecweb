<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Models\ZdUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SyncAppUsersFromZendeskCommand extends Command
{
    protected $signature = 'zendesk:sync-users-app
        {--password= : Default password for newly created users (default: changeme)}';

    protected $description = 'Create/update app users from Zendesk users (by email). Run zendesk:sync first.';

    private const ROLE_MAP = [
        'admin' => 'admin',
        'agent' => 'colaborador',
        'end-user' => 'cliente',
    ];

    public function handle(): int
    {
        $defaultPassword = $this->option('password') ?? env('SYNC_USER_DEFAULT_PASSWORD', 'changeme');

        $zdUsers = ZdUser::whereNotNull('email')->where('email', '!=', '')->get();
        $created = 0;
        $updated = 0;

        foreach ($zdUsers as $zdUser) {
            $appRole = self::ROLE_MAP[strtolower($zdUser->role ?? '')] ?? 'cliente';

            $user = User::firstWhere('email', $zdUser->email);

            if ($user === null) {
                User::create([
                    'name' => $zdUser->name ?? $zdUser->email,
                    'email' => $zdUser->email,
                    'password' => Hash::make($defaultPassword),
                    'role' => $appRole,
                    'zd_user_id' => $zdUser->id,
                ]);
                $created++;
            } else {
                $updates = ['zd_user_id' => $zdUser->id];
                if ($user->role !== 'admin') {
                    $updates['role'] = $appRole;
                }
                $user->update($updates);
                $updated++;
            }
        }

        $this->info("Sync complete: {$created} created, {$updated} updated.");

        return self::SUCCESS;
    }
}
