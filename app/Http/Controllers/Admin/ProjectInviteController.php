<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectMemberRole;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectInvite;
use App\Services\ProjectInviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectInviteController extends Controller
{
    public function store(Request $request, Project $project, ProjectInviteService $invites): JsonResponse
    {
        $this->authorize('manageMembers', $project);

        $data = $request->validate([
            'role' => ['required', Rule::enum(ProjectMemberRole::class)],
            'email' => ['nullable', 'email', 'max:255'],
            'days' => ['required', 'integer', Rule::in([1, 3, 7, 14, 30])],
        ]);

        $result = $invites->create($project, $request->user(), $data);

        return response()->json([
            'url' => $result['url'],
            'invite' => [
                'id' => $result['invite']->id,
                'role' => $result['invite']->role->label(),
                'email' => $result['invite']->email,
                'expires_at' => $result['invite']->expires_at->toIso8601String(),
            ],
        ], 201);
    }

    public function destroy(Project $project, ProjectInvite $invite): RedirectResponse
    {
        $this->authorize('manageMembers', $project);
        abort_unless($invite->project_id === $project->id, 404);

        $invite->delete();

        return back()->with('success', 'Приглашение отозвано.');
    }
}
