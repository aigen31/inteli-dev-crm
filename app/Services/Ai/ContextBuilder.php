<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\InvoiceStatus;
use App\Enums\TaskStatus;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\User;
use App\Services\CustomFieldService;

class ContextBuilder
{
    public function __construct(
        private readonly CustomFieldService $customFields,
    ) {}

    public function for(User $user, array $pageContext = []): string
    {
        $lines = [
            'Сейчас: '.now()->format('d.m.Y H:i').' ('.config('app.timezone').').',
            'Роль пользователя: '.$user->role->label().'.',
        ];

        if ($user->isAdmin()) {
            $lines = [...$lines, ...$this->adminContext()];
        } else {
            $lines = [...$lines, ...$this->clientContext($user)];
        }

        if (isset($pageContext['url']) && is_string($pageContext['url'])) {
            $lines[] = 'Пользователь открыл страницу: '.mb_substr($pageContext['url'], 0, 200);
        }

        return "КОНТЕКСТ CRM (только для ответа, не пересказывай его целиком):\n".implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function adminContext(): array
    {
        $activeStatuses = [
            TaskStatus::PendingReview,
            TaskStatus::Queued,
            TaskStatus::InProgress,
            TaskStatus::AwaitingReview,
            TaskStatus::RejectedByClient,
        ];

        $byStatus = Task::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $overdue = Task::query()
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<', today())
            ->whereIn('status', $activeStatuses)
            ->count();

        $pendingInvoices = Invoice::query()->where('status', InvoiceStatus::Pending);
        $monthRevenue = (float) Invoice::query()
            ->where('status', InvoiceStatus::Paid)
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $lines = [
            'Клиентов: '.User::query()->where('role', 'client')->count().'.',
            'Активных задач: '.Task::query()->whereIn('status', $activeStatuses)->count().'.',
            'Просрочено задач: '.$overdue.'.',
            'Неоплаченных счетов: '.$pendingInvoices->count().' на сумму '.number_format((float) $pendingInvoices->sum('amount'), 0, ',', ' ').' ₽.',
            'Доход за текущий месяц: '.number_format($monthRevenue, 0, ',', ' ').' ₽.',
        ];

        $statusLabels = collect(TaskStatus::cases())->mapWithKeys(fn (TaskStatus $status) => [$status->value => $status->label()]);

        foreach ($byStatus as $status => $count) {
            $lines[] = 'Задач в статусе «'.($statusLabels[$status] ?? $status).'»: '.$count.'.';
        }

        $recent = Task::query()->with('client')->latest()->limit(5)->get();

        if ($recent->isNotEmpty()) {
            $lines[] = 'Последние задачи: '.$recent->map(
                fn (Task $task) => $task->title.' ('.$task->status->label().', '.($task->client?->name ?? 'без клиента').')',
            )->implode('; ').'.';
        }

        $intakeField = $this->customFields->definitionByKey('client', 'intake_status');

        if ($intakeField !== null) {
            $lines[] = 'Поле приёма заявок: «'.$intakeField->label.'» (ключ intake_status), значения: '.collect($intakeField->normalizedOptions())->pluck('label')->implode(', ').'.';
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function clientContext(User $user): array
    {
        $activeStatuses = [
            TaskStatus::PendingReview,
            TaskStatus::Queued,
            TaskStatus::InProgress,
            TaskStatus::AwaitingReview,
            TaskStatus::RejectedByClient,
        ];

        $tasks = $user->clientTasks();
        $pendingInvoices = $user->invoices()->where('status', InvoiceStatus::Pending);
        $nextDeadline = $user->clientTasks()
            ->whereNotNull('deadline')
            ->whereIn('status', $activeStatuses)
            ->orderBy('deadline')
            ->first();

        $lines = [
            'Мои активные задачи: '.$tasks->whereIn('status', $activeStatuses)->count().'.',
            'Мои неоплаченные счета: '.$pendingInvoices->count().' на сумму '.number_format((float) $pendingInvoices->sum('amount'), 0, ',', ' ').' ₽.',
            'Ближайший срок: '.($nextDeadline?->deadline?->format('d.m.Y') ?? 'не задан').'.',
        ];

        $recentTasks = $user->clientTasks()->latest()->limit(5)->get();

        if ($recentTasks->isNotEmpty()) {
            $lines[] = 'Мои последние задачи: '.$recentTasks->map(
                fn (Task $task) => $task->title.' ('.$task->status->label().')',
            )->implode('; ').'.';
        }

        $recentInvoices = $user->invoices()->latest()->limit(3)->get();

        if ($recentInvoices->isNotEmpty()) {
            $lines[] = 'Мои последние счета: '.$recentInvoices->map(
                fn (Invoice $invoice) => number_format((float) $invoice->amount, 0, ',', ' ').' ₽, '.$invoice->status->label(),
            )->implode('; ').'.';
        }

        return $lines;
    }
}
