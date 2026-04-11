<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskStatus: string
{
    case PendingReview = 'pending_review';
    case Queued = 'queued';
    case InProgress = 'in_progress';
    case AwaitingReview = 'awaiting_review';
    case RejectedByClient = 'rejected_by_client';
    case Completed = 'completed';
    case RejectedByAdmin = 'rejected_by_admin';

    public function label(): string
    {
        return match ($this) {
            self::PendingReview => 'На рассмотрении',
            self::Queued => 'В очереди',
            self::InProgress => 'В работе',
            self::AwaitingReview => 'Ожидает оценки',
            self::RejectedByClient => 'Отклонено клиентом',
            self::Completed => 'Завершено',
            self::RejectedByAdmin => 'Отклонено администратором',
        };
    }

    /**
     * @return array<TaskStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingReview => [self::Queued, self::RejectedByAdmin],
            self::Queued => [self::InProgress],
            self::InProgress => [self::AwaitingReview, self::Queued],
            self::AwaitingReview => [self::Completed, self::RejectedByClient],
            self::RejectedByClient => [self::InProgress, self::Queued],
            self::Completed => [],
            self::RejectedByAdmin => [],
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions(), true);
    }

    /**
     * @return array<UserRole>
     */
    public function allowedTransitionRoles(self $newStatus): array
    {
        return match (true) {
            $this === self::PendingReview && $newStatus === self::Queued => [UserRole::Admin],
            $this === self::PendingReview && $newStatus === self::RejectedByAdmin => [UserRole::Admin],
            $this === self::Queued && $newStatus === self::InProgress => [UserRole::Admin],
            $this === self::InProgress && $newStatus === self::AwaitingReview => [UserRole::Admin],
            $this === self::InProgress && $newStatus === self::Queued => [UserRole::Admin],
            $this === self::AwaitingReview && $newStatus === self::Completed => [UserRole::Client],
            $this === self::AwaitingReview && $newStatus === self::RejectedByClient => [UserRole::Client],
            $this === self::RejectedByClient && $newStatus === self::InProgress => [UserRole::Admin],
            $this === self::RejectedByClient && $newStatus === self::Queued => [UserRole::Admin],
            default => [],
        };
    }

    public function canBeChangedBy(UserRole $role, self $newStatus): bool
    {
        return in_array($role, $this->allowedTransitionRoles($newStatus), true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::RejectedByAdmin], true);
    }

    public function canAddComments(): bool
    {
        return ! $this->isFinal();
    }
}
