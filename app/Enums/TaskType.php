<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskType: string
{
    case OneTime = 'one_time';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'Разовая',
            self::Maintenance => 'Обслуживание',
        };
    }
}
