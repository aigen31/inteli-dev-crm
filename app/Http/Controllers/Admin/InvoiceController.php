<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Invoice\CancelInvoiceAction;
use App\Actions\Invoice\CreateInvoiceAction;
use App\Actions\Invoice\MarkInvoiceAsPaidAction;
use App\Actions\Invoice\ProcessRefundAction;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Models\Task;
use App\Services\CustomFieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly CustomFieldService $customFields,
    ) {}

    public function index(Request $request): Response
    {
        $invoices = Invoice::with(['task', 'client'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('client_id'), fn ($q, $id) => $q->where('client_id', $id))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Invoices/Index', [
            'invoices' => $invoices,
            'filters' => $request->only('status', 'client_id'),
            'statuses' => collect(InvoiceStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'summary' => [
                'pending' => (float) Invoice::query()->where('status', InvoiceStatus::Pending)->sum('amount'),
                'paid_month' => (float) Invoice::query()
                    ->where('status', InvoiceStatus::Paid)
                    ->whereMonth('paid_at', now()->month)
                    ->whereYear('paid_at', now()->year)
                    ->sum('amount'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $tasks = Task::with('client')
            ->whereNotIn('status', ['rejected_by_admin'])
            ->get(['id', 'title', 'client_id', 'type']);

        return Inertia::render('Admin/Invoices/Create', [
            'tasks' => $tasks,
            'selectedTaskId' => $request->query('task_id'),
        ]);
    }

    public function store(StoreInvoiceRequest $request, CreateInvoiceAction $action): RedirectResponse
    {
        $data = $request->validated();
        $customValues = $data['custom_fields'] ?? [];
        unset($data['custom_fields']);

        $invoice = $action->execute($data);
        $this->customFields->sync($invoice, $customValues, $request->user());

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'Счёт создан.');
    }

    public function show(Invoice $invoice): Response
    {
        $invoice->load(['task', 'client', 'customFieldValues.customField']);

        return Inertia::render('Admin/Invoices/Show', [
            'invoice' => $invoice,
            'customFields' => $this->customFields->serializedValues($invoice),
        ]);
    }

    public function markPaid(Invoice $invoice, MarkInvoiceAsPaidAction $action): RedirectResponse
    {
        $this->authorize('markPaid', $invoice);

        $action->execute($invoice);

        return back()->with('success', 'Поступление подтверждено, счёт оплачен.');
    }

    public function cancel(Invoice $invoice, CancelInvoiceAction $action): RedirectResponse
    {
        $this->authorize('cancel', $invoice);

        $action->execute($invoice);

        return back()->with('success', 'Счёт отменён.');
    }

    public function refund(Invoice $invoice, ProcessRefundAction $action): RedirectResponse
    {
        $this->authorize('refund', $invoice);

        $action->execute($invoice);

        return back()->with('success', 'Возврат инициирован.');
    }
}
