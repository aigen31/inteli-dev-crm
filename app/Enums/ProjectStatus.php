<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Active => 'В работе',
            self::OnHold => 'На паузе',
            self::Completed => 'Завершён',
            self::Archived => 'В архиве',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'muted',
            self::Active => 'primary',
            self::OnHold => 'warning',
            self::Completed => 'success',
            self::Archived => 'muted',
        };
    }

    public function isArchived(): bool
    {
        return $this === self::Archived;
    }
}
