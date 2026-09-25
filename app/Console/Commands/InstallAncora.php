<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class InstallAncora extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ancora:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cria o usuário único do Âncora (uso pessoal, sem cadastro público)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->error('Já existe um usuário cadastrado. O Âncora é um app de usuário único.');

            return self::FAILURE;
        }

        $name = $this->ask('Seu nome');
        $email = $this->ask('Seu e-mail de login');
        $password = $this->secret('Sua senha (mínimo 8 caracteres)');

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $user->patient()->create();

        $this->info("Usuário {$user->email} criado com sucesso. Faça login e complete seus dados em \"Meus dados\".");

        return self::SUCCESS;
    }
}
