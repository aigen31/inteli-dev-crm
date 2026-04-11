<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => User::factory()->client(),
            'created_by' => User::factory()->admin(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraphs(2, true),
            'type' => TaskType::OneTime,
            'status' => TaskStatus::PendingReview,
            'priority' => TaskPriority::Medium,
            'deadline' => fake()->optional()->dateTimeBetween('+1 week', '+3 months'),
            'completed_at' => null,
        ];
    }

    public function maintenance(): static
    {
        return $this->state(fn () => [
            'type' => TaskType::Maintenance,
        ]);
    }

    public function withStatus(TaskStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'completed_at' => $status === TaskStatus::Completed ? now() : null,
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(fn () => [
            'priority' => TaskPriority::High,
        ]);
    }
}
