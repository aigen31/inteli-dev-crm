<?php

declare(strict_types=1);

namespace App\Enums;

enum BriefStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::InProgress => 'Заполняется',
            self::Submitted => 'На проверке',
            self::Archived => 'В архиве',
        };
    }

    /**
     * Тон для бейджа (`Components/UI/Badge.vue`, type="brief").
     */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'draft',
            self::InProgress => 'in_progress',
            self::Submitted => 'submitted',
            self::Archived => 'archived',
        };
    }

    /**
     * Клиент может менять ответы, пока бриф не отправлен.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::InProgress], true);
    }

    public function isOpen(): bool
    {
        return $this !== self::Archived;
    }

    /**
     * @return list<self>
     */
    public static function openCases(): array
    {
        return [self::Draft, self::InProgress, self::Submitted];
    }
}
