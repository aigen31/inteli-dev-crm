<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use App\Services\Ai\CrmToolbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('Kiki reads and adjusts balance; clients only see their own', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create(['email' => 'balance@example.com']);
    $toolbox = app(CrmToolbox::class);

    $own = $toolbox->execute('get_balance', $client, []);

    expect((float) $own['balance'])->toBe(0.0)
        ->and($own['user']['email'])->toBe('balance@example.com');

    $adjusted = $toolbox->execute('adjust_balance', $admin, [
        'user_id' => $client->email,
        'amount' => -250,
        'description' => 'Списание',
    ]);

    expect((float) $adjusted['balance'])->toBe(-250.0)
        ->and((float) $client->refresh()->balance)->toBe(-250.0);

    $foreign = $toolbox->execute('get_balance', $admin, ['user_id' => $client->id]);

    expect((float) $foreign['balance'])->toBe(-250.0)
        ->and($foreign['transactions'])->toHaveCount(1);
});

test('a regular user cannot adjust someone else balance', function (): void {
    $client = User::factory()->client()->create();
    $other = User::factory()->client()->create();
    $toolbox = app(CrmToolbox::class);

    expect(fn () => $toolbox->execute('adjust_balance', $client, [
        'user_id' => $other->id,
        'amount' => 100,
    ]))->toThrow(ValidationException::class);
});

test('Kiki creates a self project without a client', function (): void {
    $admin = User::factory()->admin()->create();
    $toolbox = app(CrmToolbox::class);

    $result = $toolbox->execute('create_project', $admin, [
        'name' => 'Внутренний проект Kiki',
        'type' => 'self',
    ]);

    $project = Project::query()->findOrFail($result['id']);

    expect($project->type->value)->toBe('self')
        ->and($project->client_id)->toBe($admin->id);
});
