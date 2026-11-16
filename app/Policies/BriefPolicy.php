<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Brief;
use App\Models\User;

class BriefPolicy
{
    public function view(User $user, Brief $brief): bool
    {
        return $brief->isVisibleTo($user);
    }

    /**
     * Заполнять бриф может администратор или сам клиент, пока бриф не отправлен.
     */
    public function fill(User $user, Brief $brief): bool
    {
        if (! $brief->isEditable()) {
            return false;
        }

        return $user->isAdmin() || $brief->client_id === $user->id;
    }

    public function update(User $user, Brief $brief): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Brief $brief): bool
    {
        return $user->isAdmin();
    }
}
