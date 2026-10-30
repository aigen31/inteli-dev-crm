<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Invoice;
use App\Models\Task;
use App\Models\User;

enum CustomFieldEntity: string
{
    case Client = 'client';
    case Task = 'task';
    case Invoice = 'invoice';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Клиент',
            self::Task => 'Задача',
            self::Invoice => 'Счёт',
        };
    }

    /**
     * @return class-string<User|Task|Invoice>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Client => User::class,
            self::Task => Task::class,
            self::Invoice => Invoice::class,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $entity) => ['value' => $entity->value, 'label' => $entity->label()],
            self::cases(),
        );
    }
}
