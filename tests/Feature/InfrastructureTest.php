<?php

declare(strict_types=1);

use App\Models\InfrastructureService;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Infrastructure\BackupManifestSync;
use App\Services\Infrastructure\InfrastructureBilling;
use App\Services\Infrastructure\InfrastructurePricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->client = User::factory()->client()->create();
});

test('pricing follows the published formula', function (): void {
    $pricing = new InfrastructurePricing;

    expect($pricing->backupMonthly(3, 1))->toBe(590.0)
        ->and($pricing->backupMonthly(3, 3))->toBe(890.0)
        ->and($pricing->backupMonthly(6, 7))->toBe(1650.0)
        ->and($pricing->clusterMonthly('warm', 'M'))->toBe(24900.0)
        ->and($pricing->clusterSetup('hot', 'L'))->toBe(170000.0);
});

test('an admin creates backup and cluster services with computed prices', function (): void {
    $this->actingAs($this->admin)->post(route('admin.infrastructure.store'), [
        'client_id' => $this->client->id,
        'type' => 'backup',
        'name' => 'Бэкап БД магазина',
        'status' => 'active',
        'started_at' => now()->toDateString(),
        'db_type' => 'mysql',
        'db_name' => 'shop',
        'ftp_host' => '152.228.118.128',
        'ftp_port' => 2121,
        'ftp_username' => 'bk-shop',
        'ftp_password' => 'secret',
        'frequency_per_week' => 3,
        'retention' => 3,
        'quota_gb' => 6,
    ])->assertRedirect();

    $service = InfrastructureService::query()->latest()->firstOrFail();

    expect($service->type)->toBe('backup')
        ->and((float) $service->price_monthly)->toBe(1050.0)
        ->and((float) $service->setup_fee)->toBe(1500.0);

    $target = $service->target()->firstOrFail();
    expect($target->ftp_username)->toBe('bk-shop')
        ->and($target->ftp_password)->toBe('secret');
    // Пароль хранится зашифрованным.
    expect(DB::table('backup_targets')->value('ftp_password'))->not->toBe('secret');

    $this->actingAs($this->admin)->post(route('admin.infrastructure.store'), [
        'client_id' => $this->client->id,
        'type' => 'cluster',
        'name' => 'DR тёплый',
        'status' => 'active',
        'tier' => 'warm',
        'size' => 'M',
    ])->assertRedirect();

    $cluster = InfrastructureService::query()->where('type', 'cluster')->firstOrFail();
    expect((float) $cluster->price_monthly)->toBe(24900.0)
        ->and($cluster->cluster()->firstOrFail()->tier)->toBe('warm');
});

test('billing creates one invoice per period and is idempotent', function (): void {
    $service = InfrastructureService::query()->create([
        'client_id' => $this->client->id,
        'type' => 'backup',
        'name' => 'Бэкап',
        'status' => 'active',
        'price_monthly' => 890,
        'started_at' => today(),
    ]);

    $billing = app(InfrastructureBilling::class);
    $invoice = $billing->createInvoice($service);
    $second = $billing->createInvoice($service);

    expect($invoice)->not->toBeNull()
        ->and($second)->toBeNull();

    $invoice->refresh();
    expect($invoice->infrastructure_service_id)->toBe($service->id)
        ->and($invoice->service_period)->toBe(now()->format('Y-m'))
        ->and((float) $invoice->amount)->toBe(890.0);

    $this->actingAs($this->admin)
        ->post(route('admin.infrastructure.invoice', $service))
        ->assertRedirect();

    expect(Invoice::query()->where('infrastructure_service_id', $service->id)->count())->toBe(1);
});

test('manifest sync imports runs and updates the target', function (): void {
    $service = InfrastructureService::query()->create([
        'client_id' => $this->client->id,
        'type' => 'backup',
        'name' => 'Бэкап',
        'status' => 'active',
        'price_monthly' => 590,
    ]);
    $target = $service->target()->create([
        'db_type' => 'postgresql',
        'ftp_host' => '152.228.118.128',
        'ftp_port' => 2121,
        'ftp_username' => 'bk-crm',
        'ftp_password' => 'secret',
        'frequency_per_week' => 3,
        'quota_gb' => 3,
    ]);

    $path = storage_path('framework/testing/infra-manifest.json');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, json_encode([
        'targets' => [[
            'service_id' => $service->id,
            'files' => [[
                'name' => 'crm-20261126T113533Z.sql.gz',
                'size' => 269312,
                'uploaded_at' => '2026-11-26T11:35:33Z',
                'sha256' => str_repeat('a', 64),
            ]],
        ]],
    ], JSON_THROW_ON_ERROR));

    $result = app(BackupManifestSync::class)->sync($path);

    expect($result)->toBe(['targets' => 1, 'runs' => 1]);

    $target->refresh();
    expect($target->last_status)->toBe('verified')
        ->and($target->last_backup_size)->toBe(269312)
        ->and($target->runs()->count())->toBe(1);

    File::delete($path);
});

test('regular users cannot open the infrastructure panel', function (): void {
    $this->actingAs($this->client)->get(route('admin.infrastructure.index'))->assertForbidden();
});

test('the infrastructure page renders for an admin', function (): void {
    $this->actingAs($this->admin)->get(route('admin.infrastructure.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Infrastructure/Index')
            ->has('stats')
            ->has('pricing.clusters'));
});

test('the storage agent reports a manifest with a bearer token', function (): void {
    config(['infrastructure.report_token' => 'test-report-token']);

    $service = InfrastructureService::query()->create([
        'client_id' => $this->client->id,
        'type' => 'backup',
        'name' => 'Бэкап',
        'status' => 'active',
        'price_monthly' => 590,
    ]);
    $service->target()->create([
        'db_type' => 'postgresql',
        'ftp_host' => '152.228.118.128',
        'ftp_port' => 2121,
        'ftp_username' => 'bk-crm',
        'ftp_password' => 'secret',
        'frequency_per_week' => 3,
        'quota_gb' => 3,
    ]);

    $payload = [
        'targets' => [[
            'service_id' => $service->id,
            'files' => [[
                'name' => 'crm-20261126T120000Z.sql.gz',
                'size' => 270000,
                'uploaded_at' => '2026-11-26T12:00:00Z',
            ]],
        ]],
    ];

    $this->postJson('/api/infrastructure/report', $payload)->assertUnauthorized();

    $this->withHeader('Authorization', 'Bearer test-report-token')
        ->postJson('/api/infrastructure/report', $payload)
        ->assertOk()
        ->assertJsonPath('data.runs', 1);

    expect($service->target->fresh()->last_backup_size)->toBe(270000);
});
