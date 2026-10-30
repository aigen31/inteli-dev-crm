<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Client\CreateClientAction;
use App\Actions\Client\DeactivateClientAction;
use App\Enums\CustomFieldEntity;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClientRequest;
use App\Http\Requests\Admin\UpdateClientRequest;
use App\Models\User;
use App\Services\CustomFieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request, CustomFieldService $customFields): Response
    {
        $fields = $customFields->definitions(CustomFieldEntity::Client)
            ->where('show_in_list', true)
            ->map(fn ($field) => $customFields->serializeDefinition($field))
            ->values();

        $clients = User::query()
            ->where('role', UserRole::Client)
            ->with('customFieldValues.customField')
            ->withCount(['clientTasks', 'invoices'])
            ->when($request->input('q'), function ($query, $search): void {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"));
            })
            ->when($request->input('field'), function ($query, $value): void {
                [$key, $fieldValue] = array_pad(explode(':', (string) $value, 2), 2, null);
                $query->whereHas('customFieldValues', function ($q) use ($key, $fieldValue): void {
                    $q->where('value_text', $fieldValue)
                        ->whereHas('customField', fn ($fieldQuery) => $fieldQuery->where('key', $key));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Clients/Index', [
            'clients' => $clients,
            'fields' => $fields,
            'filters' => $request->only('q', 'field'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Clients/Create');
    }

    public function store(StoreClientRequest $request, CreateClientAction $action): RedirectResponse
    {
        $action->execute($request->validated());

        return redirect()->route('admin.clients.index')
            ->with('success', 'Клиент успешно создан.');
    }

    public function show(User $client, CustomFieldService $customFields): Response
    {
        abort_unless($client->isClient(), 404);

        $client->load([
            'clientTasks' => fn ($query) => $query->with('assignee')->latest()->take(10),
            'invoices' => fn ($query) => $query->latest()->take(10),
            'customFieldValues.customField',
        ]);

        return Inertia::render('Admin/Clients/Show', [
            'client' => $client,
            'tasks' => $client->clientTasks,
            'invoices' => $client->invoices,
            'customFields' => $customFields->serializedValues($client),
        ]);
    }

    public function edit(User $client, CustomFieldService $customFields): Response
    {
        abort_unless($client->isClient(), 404);

        return Inertia::render('Admin/Clients/Edit', [
            'client' => $client,
            'customFields' => $customFields->serializedValues($client),
        ]);
    }

    public function update(UpdateClientRequest $request, User $client): RedirectResponse
    {
        $client->update($request->validated());

        return redirect()->route('admin.clients.show', $client)
            ->with('success', 'Данные клиента обновлены.');
    }

    public function updateCustomFields(
        Request $request,
        User $client,
        CustomFieldService $customFields,
    ): RedirectResponse {
        abort_unless($client->isClient(), 404);

        $data = $request->validate([
            'fields' => ['nullable', 'array'],
        ]);

        $customFields->sync($client, $data['fields'] ?? [], $request->user());

        return back()->with('success', 'Поля клиента сохранены.');
    }

    public function deactivate(User $client, DeactivateClientAction $action): RedirectResponse
    {
        $action->execute($client);

        return redirect()->route('admin.clients.index')
            ->with('success', 'Аккаунт клиента деактивирован.');
    }
}
