<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskReport>
 */
class TaskReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'author_id' => User::factory()->admin(),
            'content' => fake()->paragraphs(2, true),
            'attachments' => null,
        ];
    }
}
