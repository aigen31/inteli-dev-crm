<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Actions\Payment\CreatePaymentAction;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $invoices = $request->user()
            ->invoices()
            ->with('task:id,title')
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Client/Invoices/Index', [
            'invoices' => $invoices,
            'filters' => $request->only('status'),
            'statuses' => collect(InvoiceStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $invoice->load('task');

        return Inertia::render('Client/Invoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    public function pay(Invoice $invoice, CreatePaymentAction $action): RedirectResponse
    {
        $this->authorize('pay', $invoice);

        $returnUrl = route('client.invoices.show', $invoice);
        $confirmationUrl = $action->execute($invoice, $returnUrl);

        return redirect()->away($confirmationUrl);
    }
}
