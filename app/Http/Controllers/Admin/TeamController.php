<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Team\CreateExecutorAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExecutorRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $executors = User::query()
            ->where('role', UserRole::Executor)
            ->withCount('assignedTasks')
            ->when($request->input('q'), fn ($query, $search) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Team/Index', [
            'executors' => $executors,
            'filters' => $request->only('q'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Team/Create');
    }

    public function store(StoreExecutorRequest $request, CreateExecutorAction $action): RedirectResponse
    {
        $executor = $action->execute($request->validated());

        return redirect()->route('admin.team.index')
            ->with('success', "Исполнитель {$executor->name} добавлен.");
    }

    public function toggle(User $executor): RedirectResponse
    {
        abort_unless($executor->isExecutor(), 404);

        $executor->update(['is_active' => ! $executor->is_active]);

        return back()->with('success', $executor->is_active ? 'Исполнитель активирован.' : 'Исполнитель деактивирован.');
    }
}
