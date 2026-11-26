<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Models\BackupTarget;
use App\Models\DrCluster;
use App\Models\InfrastructureService;
use App\Models\User;
use App\Services\Infrastructure\BackupManifestSync;
use App\Services\Infrastructure\InfrastructureBilling;
use App\Services\Infrastructure\InfrastructurePricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InfrastructureController extends Controller
{
    public function __construct(
        private readonly InfrastructurePricing $pricing,
        private readonly InfrastructureBilling $billing,
        private readonly BackupManifestSync $sync,
    ) {}

    public function index(): Response
    {
        $services = InfrastructureService::query()
            ->with([
                'client:id,name,email',
                'target' => fn ($query) => $query->with(['runs' => fn ($runs) => $runs->latest('uploaded_at')->limit(3)]),
                'cluster',
                'invoices' => fn ($query) => $query->latest()->limit(3),
            ])
            ->latest()
            ->get()
            ->map(fn (InfrastructureService $service): array => $this->payload($service));

        $active = $services->where('status', InfrastructureService::STATUS_ACTIVE);

        return Inertia::render('Admin/Infrastructure/Index', [
            'services' => $services->values(),
            'stats' => [
                'active' => $active->count(),
                'mrr' => (float) $active->sum('price_monthly'),
                'backups_week' => BackupRun::query()->where('uploaded_at', '>=', now()->subWeek())->count(),
                'failed' => BackupTarget::query()
                    ->whereNotNull('last_backup_at')
                    ->where('last_backup_at', '<', now()->subHours(48))
                    ->count(),
                'clusters' => DrCluster::query()->count(),
            ],
            'clients' => User::query()->where('role', '!=', 'admin')->where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'email']),
            'pricing' => $this->pricing->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data): void {
            $type = $data['type'];
            $isBackup = $type === InfrastructureService::TYPE_BACKUP;

            $price = $isBackup
                ? $this->pricing->backupMonthly((float) $data['quota_gb'], (int) $data['frequency_per_week'])
                : $this->pricing->clusterMonthly($data['tier'], $data['size']);

            $service = InfrastructureService::query()->create([
                'client_id' => $data['client_id'],
                'type' => $type,
                'name' => $data['name'],
                'status' => $data['status'] ?? InfrastructureService::STATUS_DRAFT,
                'price_monthly' => $price,
                'setup_fee' => $isBackup ? InfrastructurePricing::BACKUP_SETUP : $this->pricing->clusterSetup($data['tier'], $data['size']),
                'started_at' => $data['started_at'] ?? null,
                'next_invoice_at' => ($data['started_at'] ?? null) ? now()->startOfMonth()->addMonth()->toDateString() : null,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($isBackup) {
                $service->target()->create([
                    'db_type' => $data['db_type'],
                    'db_name' => $data['db_name'] ?? null,
                    'ftp_host' => $data['ftp_host'],
                    'ftp_port' => $data['ftp_port'] ?? 21,
                    'ftp_username' => $data['ftp_username'],
                    'ftp_password' => $data['ftp_password'],
                    'remote_path' => $data['remote_path'] ?? '/',
                    'frequency_per_week' => $data['frequency_per_week'],
                    'retention' => $data['retention'] ?? 3,
                    'quota_gb' => $data['quota_gb'],
                ]);
            } else {
                $service->cluster()->create([
                    'tier' => $data['tier'],
                    'size' => $data['size'],
                    'region' => $data['region'] ?? 'ru-central',
                    'rto_minutes' => $data['rto_minutes'] ?? 60,
                    'rpo_minutes' => $data['rpo_minutes'] ?? 15,
                    'status' => 'draft',
                    'runbook' => $data['runbook'] ?? null,
                ]);
            }
        });

        return back()->with('success', 'Услуга добавлена. Цена рассчитана по тарифу.');
    }

    public function status(Request $request, InfrastructureService $service): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                InfrastructureService::STATUS_DRAFT,
                InfrastructureService::STATUS_ACTIVE,
                InfrastructureService::STATUS_SUSPENDED,
            ])],
        ]);

        $service->update([
            'status' => $data['status'],
            'started_at' => $service->started_at ?? ($data['status'] === InfrastructureService::STATUS_ACTIVE ? today() : null),
            'next_invoice_at' => $data['status'] === InfrastructureService::STATUS_ACTIVE
                ? ($service->next_invoice_at ?? now()->addMonth()->startOfMonth()->toDateString())
                : $service->next_invoice_at,
        ]);

        return back()->with('success', 'Статус услуги обновлён.');
    }

    public function invoice(InfrastructureService $service): RedirectResponse
    {
        $invoice = $this->billing->createInvoice($service);

        return back()->with(
            $invoice ? 'success' : 'error',
            $invoice ? 'Счёт за текущий период создан.' : 'Счёт за этот период уже есть или цена не задана.',
        );
    }

    public function sync(Request $request): RedirectResponse
    {
        $path = $request->input('manifest_path');
        $result = $this->sync->sync(is_string($path) && $path !== '' ? $path : null);

        return back()->with('success', "Манифест обработан: {$result['targets']} целей, {$result['runs']} копий.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $type = (string) $request->input('type');

        $rules = [
            'client_id' => ['required', 'uuid', 'exists:users,id'],
            'type' => ['required', Rule::in([InfrastructureService::TYPE_BACKUP, InfrastructureService::TYPE_CLUSTER])],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'status' => ['nullable', Rule::in([InfrastructureService::STATUS_DRAFT, InfrastructureService::STATUS_ACTIVE])],
            'started_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($type === InfrastructureService::TYPE_BACKUP) {
            $rules += [
                'db_type' => ['required', Rule::in(['mysql', 'mariadb', 'postgresql', 'sqlite'])],
                'db_name' => ['nullable', 'string', 'max:120'],
                'ftp_host' => ['required', 'string', 'max:190'],
                'ftp_port' => ['nullable', 'integer', 'between:1,65535'],
                'ftp_username' => ['required', 'string', 'max:120'],
                'ftp_password' => ['required', 'string', 'max:255'],
                'remote_path' => ['nullable', 'string', 'max:255'],
                'frequency_per_week' => ['required', 'integer', 'between:1,7'],
                'retention' => ['nullable', 'integer', 'between:1,30'],
                'quota_gb' => ['required', 'numeric', 'between:0.1,1000'],
            ];
        } else {
            $rules += [
                'tier' => ['required', Rule::in(['cold', 'warm', 'hot', 'active_active'])],
                'size' => ['required', Rule::in(['S', 'M', 'L'])],
                'region' => ['nullable', 'string', 'max:40'],
                'rto_minutes' => ['nullable', 'integer', 'between:1,1440'],
                'rpo_minutes' => ['nullable', 'integer', 'between:0,1440'],
                'runbook' => ['nullable', 'string', 'max:8000'],
            ];
        }

        return $request->validate($rules);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(InfrastructureService $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'type' => $service->type,
            'type_label' => $service->typeLabel(),
            'status' => $service->status,
            'status_label' => $service->statusLabel(),
            'price_monthly' => (float) $service->price_monthly,
            'setup_fee' => (float) $service->setup_fee,
            'started_at' => $service->started_at?->toDateString(),
            'next_invoice_at' => $service->next_invoice_at?->toDateString(),
            'client' => $service->client?->only('id', 'name', 'email'),
            'target' => $service->target ? [
                'id' => $service->target->id,
                'db_type' => $service->target->db_type,
                'db_name' => $service->target->db_name,
                'frequency_per_week' => $service->target->frequency_per_week,
                'retention' => $service->target->retention,
                'quota_gb' => (float) $service->target->quota_gb,
                'last_backup_at' => $service->target->last_backup_at?->toIso8601String(),
                'last_backup_size' => $service->target->last_backup_size,
                'last_status' => $service->target->last_status,
                'runs' => $service->target->runs->map(fn (BackupRun $run): array => [
                    'filename' => $run->filename,
                    'size_bytes' => $run->size_bytes,
                    'status' => $run->status,
                    'uploaded_at' => $run->uploaded_at?->toIso8601String(),
                ])->all(),
            ] : null,
            'cluster' => $service->cluster ? [
                'tier' => $service->cluster->tier,
                'size' => $service->cluster->size,
                'region' => $service->cluster->region,
                'rto_minutes' => $service->cluster->rto_minutes,
                'rpo_minutes' => $service->cluster->rpo_minutes,
                'last_drill_at' => $service->cluster->last_drill_at?->toIso8601String(),
            ] : null,
            'invoices' => $service->invoices->map(fn ($invoice): array => [
                'id' => $invoice->id,
                'amount' => (float) $invoice->amount,
                'status' => $invoice->status?->value,
                'period' => $invoice->service_period,
                'created_at' => $invoice->created_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
