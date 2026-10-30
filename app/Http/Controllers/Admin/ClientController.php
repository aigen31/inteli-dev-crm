<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Client\CreateClientAction;
use App\Actions\Client\DeactivateClientAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClientRequest;
use App\Http\Requests\Admin\UpdateClientRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(): Response
    {
        $clients = User::where('role', UserRole::Client)
            ->latest()
            ->paginate(15);

        return Inertia::render('Admin/Clients/Index', [
            'clients' => $clients,
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

    public function show(User $client): Response
    {
        $client->load(['clientTasks' => fn ($q) => $q->latest()->take(10)]);

        return Inertia::render('Admin/Clients/Show', [
            'client' => $client,
            'tasks' => $client->clientTasks,
        ]);
    }

    public function edit(User $client): Response
    {
        return Inertia::render('Admin/Clients/Edit', [
            'client' => $client,
        ]);
    }

    public function update(UpdateClientRequest $request, User $client): RedirectResponse
    {
        $client->update($request->validated());

        return redirect()->route('admin.clients.show', $client)
            ->with('success', 'Данные клиента обновлены.');
    }

    public function deactivate(User $client, DeactivateClientAction $action): RedirectResponse
    {
        $action->execute($client);

        return redirect()->route('admin.clients.index')
            ->with('success', 'Аккаунт клиента деактивирован.');
    }
}
