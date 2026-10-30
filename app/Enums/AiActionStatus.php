<?php

declare(strict_types=1);

namespace App\Enums;

enum AiActionStatus: string
{
    case Proposed = 'proposed';
    case Executed = 'executed';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Ждёт подтверждения',
            self::Executed => 'Выполнено',
            self::Rejected => 'Отклонено',
            self::Failed => 'Ошибка',
        };
    }
}
