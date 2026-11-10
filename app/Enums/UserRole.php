<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Client = 'client';
    case Executor = 'executor';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Администратор',
            self::Client => 'Клиент',
            self::Executor => 'Исполнитель',
        };
    }
}
