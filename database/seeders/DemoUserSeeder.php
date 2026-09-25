<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder de conveniência para DESENVOLVIMENTO LOCAL (docs/02, seção 4).
 * Nunca deve rodar em produção — lá o usuário único é criado via
 * `php artisan ancora:install`.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'dev@ancora.test'],
            [
                'name' => 'Paciente Dev',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        $user->patient()->firstOrCreate([], [
            'full_name' => 'Paciente Dev',
            'birth_date' => now()->subYears(30),
        ]);
    }
}
