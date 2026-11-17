<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Enums\UserRole;
use App\Models\User;

class CreateExecutorAction
{
    public function execute(array $data): User
    {
        return User::create([
            'role' => UserRole::User,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'is_active' => true,
        ]);
    }
}
