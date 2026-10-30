<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Invoice\CreateInvoiceAction;
use App\Actions\Invoice\MarkInvoiceAsPaidAction;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $invoices = Invoice::query()
            ->with(['client', 'task', 'customFieldValues.customField'])
            ->when($user->isClient(), fn ($query) => $query->where('client_id', $user->id))
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('client_id') && $user->isAdmin(), fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->latest()
            ->paginate(min(max((int) $request->input('per_page', 25), 1), 100));

        return InvoiceResource::collection($invoices);
    }

    public function show(Request $request, Invoice $invoice): InvoiceResource
    {
        $this->authorizeInvoice($request, $invoice);

        return new InvoiceResource($invoice->load(['client', 'task', 'customFieldValues.customField']));
    }

    public function store(Request $request, CreateInvoiceAction $action): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'task_id' => ['required', 'uuid', 'exists:tasks,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:500'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurring_interval' => ['nullable', 'string'],
        ]);

        $invoice = $action->execute($data);

        return (new InvoiceResource($invoice->load(['client', 'task', 'customFieldValues.customField'])))
            ->response()
            ->setStatusCode(201);
    }

    public function markPaid(Request $request, Invoice $invoice, MarkInvoiceAsPaidAction $action): InvoiceResource
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->authorizeInvoice($request, $invoice);

        $action->execute($invoice);

        return new InvoiceResource($invoice->refresh()->load(['client', 'task', 'customFieldValues.customField']));
    }

    private function authorizeInvoice(Request $request, Invoice $invoice): void
    {
        if ($request->user()->isClient() && $invoice->client_id !== $request->user()->id) {
            abort(403);
        }
    }
}
