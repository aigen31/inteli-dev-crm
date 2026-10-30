<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case YooKassa = 'yookassa';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::YooKassa => 'ЮКасса (онлайн)',
            self::Transfer => 'Прямой перевод',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::YooKassa => 'ЮКасса',
            self::Transfer => 'Прямой перевод',
        };
    }

    public function isOnline(): bool
    {
        return $this === self::YooKassa;
    }
}
