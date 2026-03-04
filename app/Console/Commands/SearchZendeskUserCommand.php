<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ZendeskClient;
use Illuminate\Console\Command;

class SearchZendeskUserCommand extends Command
{
    protected $signature = 'zendesk:search-user {email : Email do usuário para buscar no Zendesk}';

    protected $description = 'Busca um usuário no Zendesk pela API Search (para debug)';

    public function handle(ZendeskClient $client): int
    {
        $email = $this->argument('email');

        $this->info("Buscando usuário no Zendesk: {$email}");
        $this->newLine();

        try {
            $users = $client->searchUsersByEmail($email);

            if (empty($users)) {
                $this->warn('Nenhum usuário encontrado na busca.');
                $this->warn('Verifique se o email está correto e se a API tem permissão de busca.');
                return self::FAILURE;
            }

            $this->info('Usuários encontrados: ' . count($users));
            $rows = [];
            foreach ($users as $u) {
                $userEmail = $u['email'] ?? '-';
                $match = strtolower(trim($userEmail)) === strtolower(trim($email)) ? 'SIM' : 'não';
                $rows[] = [$u['id'] ?? '-', $u['name'] ?? '-', $userEmail, $u['role'] ?? '-', $match];
            }
            $this->table(['zd_id', 'Nome', 'Email', 'Role', 'Match exato'], $rows);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Erro: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
