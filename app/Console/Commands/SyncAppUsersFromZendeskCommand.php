<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Models\ZdUser;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class SyncAppUsersFromZendeskCommand extends Command
{
    protected $signature = 'zendesk:sync-users-app
        {--password= : Temporary password for new users (if omitted, a random strong password is generated)}
        {--force : Update password for existing users to the temp password}
        {--show-password : Print the temporary password in command output}';

    protected $description = 'Create app user for every ZdUser (with temp password). Run zendesk:sync first.';

    private const ROLE_MAP = [
        'admin' => 'admin',
        'agent' => 'colaborador',
        'end-user' => 'cliente',
    ];

    private const FALLBACK_EMAIL_DOMAIN = 'local.tecdesk';

    public function handle(): int
    {
        $tempPassword = (string) ($this->option('password') ?: env('SYNC_USER_DEFAULT_PASSWORD', ''));
        if ($tempPassword === '') {
            $tempPassword = Str::password(20);
        }
        $forcePassword = $this->option('force');
        $showPassword = (bool) $this->option('show-password');

        $zdUsers = ZdUser::all();
        $created = 0;
        $updated = 0;
        $usedEmails = [];

        foreach ($zdUsers as $zdUser) {
            $appRole = self::ROLE_MAP[strtolower($zdUser->role ?? '')] ?? 'cliente';
            $email = $this->resolveEmail($zdUser, $usedEmails);
            $usedEmails[$email] = true;

            $user = User::where('zd_user_id', $zdUser->id)->first()
                ?? User::firstWhere('email', $email);

            if ($user === null) {
                User::create([
                    'name' => $zdUser->name ?? $zdUser->email ?? "ZdUser #{$zdUser->zd_id}",
                    'email' => $email,
                    'password' => Hash::make($tempPassword),
                    'role' => $appRole,
                    'zd_user_id' => $zdUser->id,
                ]);
                $created++;
            } else {
                $updates = [
                    'zd_user_id' => $zdUser->id,
                    'name' => $zdUser->name ?? $zdUser->email ?? $user->name,
                ];
                if ($user->email !== $email) {
                    $updates['email'] = $email;
                }
                if ($user->role !== 'admin') {
                    $updates['role'] = $appRole;
                }
                if ($forcePassword) {
                    $updates['password'] = Hash::make($tempPassword);
                }
                $user->update($updates);
                $updated++;
            }
        }

        $this->info("Sync complete: {$created} created, {$updated} updated.");
        if ($showPassword) {
            $this->warn("Temporary password: {$tempPassword} (use user:set-password to change)");
        } else {
            $this->line('Temporary password was applied but not displayed. Use --show-password only in secure terminals.');
        }

        return self::SUCCESS;
    }

    private function resolveEmail(ZdUser $zdUser, array $usedEmails): string
    {
        $email = trim($zdUser->email ?? '');
        if ($email !== '' && ! isset($usedEmails[$email])) {
            return $email;
        }
        return 'zd_' . $zdUser->zd_id . '@' . self::FALLBACK_EMAIL_DOMAIN;
    }
}
