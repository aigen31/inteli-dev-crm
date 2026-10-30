<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Actions\Payment\CreatePaymentAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\CustomFieldService;
use App\Services\SettingsService;
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

    public function show(Invoice $invoice, SettingsService $settings, CustomFieldService $customFields): Response
    {
        $this->authorize('view', $invoice);

        $invoice->load(['task', 'customFieldValues.customField']);

        return Inertia::render('Client/Invoices/Show', [
            'invoice' => $invoice,
            'transfer' => $invoice->payment_method === PaymentMethod::Transfer
                ? $settings->transferDetails()
                : null,
            'customFields' => $customFields->serializedValues($invoice),
        ]);
    }

    public function pay(Invoice $invoice): RedirectResponse
    {
        $this->authorize('view', $invoice);

        if ($invoice->payment_method !== PaymentMethod::YooKassa) {
            return redirect()
                ->route('client.invoices.show', $invoice)
                ->with('error', 'По этому счёту оплата принимается прямым переводом на реквизиты.');
        }

        $this->authorize('pay', $invoice);

        try {
            $confirmationUrl = app(CreatePaymentAction::class)->execute(
                $invoice,
                route('client.invoices.show', $invoice),
            );
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('client.invoices.show', $invoice)
                ->with('error', 'Не удалось создать платёж. Проверьте настройки ЮКассы.');
        }

        return redirect()->away($confirmationUrl);
    }
}
