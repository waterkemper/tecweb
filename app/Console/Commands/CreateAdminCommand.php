<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    protected $signature = 'user:create-admin
        {email : Email do usuário admin}
        {--password= : Senha (se omitido, será solicitada)}
        {--name=Admin : Nome do usuário}';

    protected $description = 'Cria ou atualiza um usuário admin';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->option('password') ?? $this->secret('Senha');
        $name = $this->option('name');

        if (empty($password)) {
            $this->error('A senha é obrigatória.');
            return self::FAILURE;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => 'admin',
            ]
        );

        $this->info("Admin criado/atualizado: {$email}");

        return self::SUCCESS;
    }
}
