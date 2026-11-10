<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectDelegation: string
{
    case Client = 'client';
    case Executor = 'executor';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Клиент',
            self::Executor => 'Исполнитель',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Client => 'заказывает работу в проекте и принимает результат',
            self::Executor => 'выполняет задачи проекта и отправляет отчёты',
        };
    }
}
