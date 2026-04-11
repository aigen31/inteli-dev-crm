<?php

declare(strict_types=1);

namespace App\Actions\Client;

use App\Enums\UserRole;
use App\Events\Client\ClientCreated;
use App\Models\User;

class CreateClientAction
{
    public function execute(array $data): User
    {
        $client = User::create([
            'role' => UserRole::Client,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'messenger_contacts' => $data['messenger_contacts'] ?? null,
            'password' => $data['password'],
            'is_active' => true,
        ]);

        ClientCreated::dispatch($client, $data['password']);

        return $client;
    }
}
