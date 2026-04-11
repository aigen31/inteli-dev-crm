<?php

declare(strict_types=1);

namespace App\Actions\Client;

use App\Models\User;

class DeactivateClientAction
{
    public function execute(User $client): User
    {
        $client->update(['is_active' => false]);

        return $client;
    }
}
