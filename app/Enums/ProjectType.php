<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectType: string
{
    case Client = 'client';
    case Self = 'self';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Клиентский проект',
            self::Self => 'Self-проект',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Client => 'Заказчик — отдельный человек или компания.',
            self::Self => 'Внутренний проект: заказчик — администратор, без согласия и ожидания оценки.',
        };
    }

    public function hasClient(): bool
    {
        return $this === self::Client;
    }
}
