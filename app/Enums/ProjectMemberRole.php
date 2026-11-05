<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectMemberRole: string
{
    case Manager = 'manager';
    case Member = 'member';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Менеджер',
            self::Member => 'Участник',
            self::Viewer => 'Наблюдатель',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Manager => 'видит проект и все задачи, может создавать задачи',
            self::Member => 'видит проект и задачи, может создавать задачи',
            self::Viewer => 'только просмотр проекта и своих задач',
        };
    }

    public function canCreateTasks(): bool
    {
        return $this !== self::Viewer;
    }

    public function canSeeTeam(): bool
    {
        return $this === self::Manager;
    }
}
