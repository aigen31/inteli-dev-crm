<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'role' => UserRole::Admin,
                'name' => 'Администратор',
                'password' => 'password',
                'is_active' => true,
            ]
        );
    }
}
