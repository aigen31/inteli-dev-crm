<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Client\ClientCreated;
use App\Notifications\ClientAccountCreated;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendClientCreatedNotification implements ShouldQueue
{
    public function handle(ClientCreated $event): void
    {
        $event->client->notify(
            new ClientAccountCreated($event->plainPassword)
        );
    }
}
