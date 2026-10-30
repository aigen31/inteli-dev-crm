<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskSource: string
{
    case Manual = 'manual';
    case Template = 'template';
    case Client = 'client';
    case Ai = 'ai';
    case Api = 'api';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Вручную',
            self::Template => 'Из шаблона',
            self::Client => 'Заявка клиента',
            self::Ai => 'Кики',
            self::Api => 'API',
        };
    }
}
