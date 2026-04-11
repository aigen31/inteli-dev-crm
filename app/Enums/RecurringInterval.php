<?php

declare(strict_types=1);

namespace App\Enums;

enum RecurringInterval: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Ежемесячно',
            self::Quarterly => 'Ежеквартально',
            self::Yearly => 'Ежегодно',
        };
    }
}
