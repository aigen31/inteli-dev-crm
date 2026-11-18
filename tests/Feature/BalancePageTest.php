<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\BalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a user sees their own balance and transactions', function (): void {
    $user = User::factory()->client()->create();
    $other = User::factory()->client()->create();
    $balance = app(BalanceService::class);

    $balance->adjust($user, 2000, 'manual', 'Пополнение');
    $balance->adjust($user, -500, 'task_completed', 'Списание за задачу');
    $balance->adjust($other, 9999, 'manual', 'Чужое пополнение');

    $this->actingAs($user)
        ->get(route('balance.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Balance/Index')
            ->where('balance', 1500)
            ->has('transactions.data', 2)
            ->where('summary.topped_up', 2000)
            ->where('summary.charged', -500));

    $this->actingAs($user)
        ->get(route('client.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('balance', 1500)
            ->has('balanceTransactions', 2));
});

test('balance page is closed to guests', function (): void {
    $this->get(route('balance.index'))->assertRedirect(route('login'));
});
