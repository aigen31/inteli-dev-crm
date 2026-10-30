<?php

declare(strict_types=1);

namespace App\Enums;

enum ApiAbility: string
{
    case Read = 'read';
    case Write = 'write';

    public function label(): string
    {
        return match ($this) {
            self::Read => 'Чтение',
            self::Write => 'Запись',
        };
    }
}
