<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Client\CreateClientAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClientResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ClientController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $clients = User::query()
            ->where('role', UserRole::Client)
            ->when($user->isClient(), fn ($query) => $query->whereKey($user->id))
            ->with('customFieldValues.customField')
            ->withCount(['clientTasks', 'invoices'])
            ->when($request->input('q'), function ($query, $search): void {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->latest()
            ->paginate(min(max((int) $request->input('per_page', 25), 1), 100));

        return ClientResource::collection($clients);
    }

    public function show(Request $request, User $client): ClientResource
    {
        $this->authorizeClient($request, $client);

        $client->load('customFieldValues.customField')->loadCount(['clientTasks', 'invoices']);

        return new ClientResource($client);
    }

    public function store(Request $request, CreateClientAction $action): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', Password::min(8)],
        ]);

        $client = $action->execute($data);
        $client->load('customFieldValues.customField');

        return (new ClientResource($client))->response()->setStatusCode(201);
    }

    public function update(Request $request, User $client): ClientResource
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->authorizeClient($request, $client);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($client->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $client->update($data);

        return new ClientResource($client->refresh());
    }

    private function authorizeClient(Request $request, User $client): void
    {
        abort_unless($client->isClient(), 404);

        if ($request->user()->isClient() && $request->user()->id !== $client->id) {
            abort(403);
        }
    }
}
