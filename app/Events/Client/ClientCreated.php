<?php

declare(strict_types=1);

namespace App\Events\Client;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClientCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $client,
        public readonly string $plainPassword,
    ) {}
}
