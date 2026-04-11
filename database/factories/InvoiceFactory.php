<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'client_id' => User::factory()->client(),
            'amount' => fake()->randomFloat(2, 500, 50000),
            'currency' => 'RUB',
            'description' => fake()->sentence(),
            'status' => InvoiceStatus::Pending,
            'is_recurring' => false,
            'recurring_interval' => null,
            'recurring_next_date' => null,
            'yookassa_payment_id' => null,
            'yookassa_payment_method_id' => null,
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
            'yookassa_payment_id' => fake()->uuid(),
        ]);
    }

    public function recurring(): static
    {
        return $this->state(fn () => [
            'is_recurring' => true,
            'recurring_interval' => 'monthly',
            'recurring_next_date' => now()->addMonth(),
        ]);
    }
}
