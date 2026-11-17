<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case User = 'user';

    /** @deprecated Данные до объединения ролей; трактуются как User. */
    case Client = 'client';

    /** @deprecated Данные до объединения ролей; трактуются как User. */
    case Executor = 'executor';

    /** Роль «обычный пользователь»: и заказчик, и исполнитель по назначению. */
    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Администратор',
            self::User, self::Client, self::Executor => 'Пользователь',
        };
    }
}
