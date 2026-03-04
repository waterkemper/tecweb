<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetUserPasswordCommand extends Command
{
    protected $signature = 'user:set-password
        {email : User email (or zd_N@local.tecdesk for users without Zendesk email)}
        {--password= : New password (if omitted, will be prompted)}';

    protected $description = 'Set or change a user password manually';

    public function handle(): int
    {
        $email = trim($this->argument('email'));
        $password = $this->option('password') ?? $this->secret('Nova senha');

        if (empty($email)) {
            $this->error('Email é obrigatório.');
            return self::FAILURE;
        }

        if (empty($password)) {
            $this->error('A senha é obrigatória.');
            return self::FAILURE;
        }

        $user = User::firstWhere('email', $email);

        if ($user === null) {
            $this->error("Usuário não encontrado: {$email}");
            return self::FAILURE;
        }

        $user->update(['password' => Hash::make($password)]);
        $this->info("Senha atualizada para: {$email}");

        return self::SUCCESS;
    }
}
