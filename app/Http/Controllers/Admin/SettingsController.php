<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiAction;
use App\Models\ApiToken;
use App\Models\CustomField;
use App\Services\CustomFieldService;
use App\Services\SettingsService;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(
        Request $request,
        SettingsService $settings,
        CustomFieldService $customFields,
    ): Response {
        $fields = CustomField::query()
            ->orderBy('entity_type')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CustomField $field) => $customFields->serializeDefinition($field))
            ->values();

        $tokens = ApiToken::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get(['id', 'name', 'prefix', 'abilities', 'last_used_at', 'expires_at', 'created_at']);

        $actions = AiAction::query()
            ->latest()
            ->limit(12)
            ->get(['id', 'tool_name', 'arguments', 'status', 'created_at'])
            ->map(fn (AiAction $action) => [
                'id' => $action->id,
                'tool_name' => $action->tool_name,
                'status' => $action->status->value,
                'status_label' => $action->status->label(),
                'arguments' => $action->arguments,
                'created_at' => $action->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Settings/Index', [
            'tab' => $request->query('tab', 'appearance'),
            'yookassa' => [
                'shop_id' => config('services.yookassa.shop_id') ? '••••'.substr((string) config('services.yookassa.shop_id'), -4) : null,
                'is_configured' => (bool) config('services.yookassa.shop_id'),
            ],
            'transfer' => $settings->transferFields(),
            'customFields' => $fields,
            'customFieldEntities' => Options::customFieldEntities(),
            'customFieldTypes' => Options::customFieldTypes(),
            'apiTokens' => $tokens,
            'aiActions' => $actions,
            'mcp' => [
                'api_url' => rtrim((string) config('app.url'), '/').'/api/v1',
                'command' => 'node mcp/src/server.mjs',
                'docs' => 'docs/api/README.md',
            ],
        ]);
    }

    public function update(Request $request, SettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'transfer_recipient' => ['nullable', 'string', 'max:150'],
            'transfer_inn' => ['nullable', 'string', 'max:20'],
            'transfer_bank' => ['nullable', 'string', 'max:150'],
            'transfer_account' => ['nullable', 'string', 'max:40'],
            'transfer_bik' => ['nullable', 'string', 'max:20'],
            'transfer_corr_account' => ['nullable', 'string', 'max:40'],
            'transfer_phone' => ['nullable', 'string', 'max:40'],
            'transfer_note' => ['nullable', 'string', 'max:500'],
        ]);

        $settings->saveTransfer($data);

        return redirect()
            ->route('admin.settings.edit', ['tab' => 'payments'])
            ->with('success', 'Реквизиты для прямого перевода сохранены.');
    }
}
