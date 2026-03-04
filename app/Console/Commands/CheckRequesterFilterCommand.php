<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ZdUser;
use Illuminate\Console\Command;

class CheckRequesterFilterCommand extends Command
{
    protected $signature = 'zendesk:check-requester-filter';

    protected $description = 'Verifica se os emails do ZENDESK_FILTER_REQUESTER_EMAILS existem em zd_users';

    public function handle(): int
    {
        $filterEmails = config('zendesk.filter_requester_emails', []);

        if (empty($filterEmails)) {
            $this->info('ZENDESK_FILTER_REQUESTER_EMAILS não está definido.');
            return self::SUCCESS;
        }

        $this->info('Emails configurados: ' . implode(', ', $filterEmails));
        $this->newLine();

        $lowerEmails = array_map(fn (string $e) => strtolower(trim($e)), $filterEmails);

        $users = ZdUser::where(function ($q) use ($lowerEmails) {
            foreach ($lowerEmails as $email) {
                $q->orWhereRaw('LOWER(TRIM(email)) = ?', [$email]);
            }
        })->get(['zd_id', 'name', 'email', 'role']);

        if ($users->isEmpty()) {
            $this->warn('Nenhum usuário encontrado em zd_users com esses emails.');
            $this->warn('Execute: php artisan zendesk:sync --users-only');
            $this->warn('Depois verifique no Zendesk se o email está correto (pode ter diferença de maiúsculas ou espaços).');
            return self::FAILURE;
        }

        $this->info('Usuários encontrados (' . $users->count() . '):');
        $this->table(
            ['zd_id', 'Nome', 'Email', 'Role'],
            $users->map(fn ($u) => [$u->zd_id, $u->name ?? '-', $u->email ?? '-', $u->role ?? '-'])
        );

        $this->newLine();
        $this->info('O filtro está OK. Os tickets desses requesters serão sincronizados.');

        return self::SUCCESS;
    }
}
