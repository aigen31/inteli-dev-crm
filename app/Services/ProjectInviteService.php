<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProjectMemberRole;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ProjectInvite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectInviteService
{
    public function __construct(
        private readonly ConsentService $consents,
    ) {}

    /**
     * @param  array{role?: string, email?: string|null, days?: int}  $data
     * @return array{invite: ProjectInvite, url: string}
     */
    public function create(Project $project, User $creator, array $data): array
    {
        $token = Str::random(48);
        $days = min(max((int) ($data['days'] ?? 7), 1), 30);

        $invite = $project->invites()->create([
            'token_hash' => hash('sha256', $token),
            'role' => $data['role'] ?? ProjectMemberRole::Member->value,
            'email' => filled($data['email'] ?? null) ? mb_strtolower(trim((string) $data['email'])) : null,
            'created_by' => $creator->id,
            'expires_at' => now()->addDays($days),
        ]);

        return [
            'invite' => $invite,
            'url' => route('invite.show', ['token' => $token]),
        ];
    }

    public function findUsable(string $token): ?ProjectInvite
    {
        $invite = ProjectInvite::query()
            ->with(['project:id,key,name,status,archived_at', 'project.client:id,name'])
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($invite === null || ! $invite->isUsable()) {
            return null;
        }

        if ($invite->project?->archived_at !== null) {
            return null;
        }

        return $invite;
    }

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function accept(ProjectInvite $invite, array $data, Request $request): User
    {
        $user = DB::transaction(function () use ($invite, $data): User {
            $locked = ProjectInvite::query()->lockForUpdate()->find($invite->id);

            if ($locked === null || ! $locked->isUsable()) {
                throw ValidationException::withMessages([
                    'invite' => 'Приглашение уже использовано или истекло.',
                ]);
            }

            $email = mb_strtolower(trim($data['email']));

            if ($locked->email !== null && $locked->email !== $email) {
                throw ValidationException::withMessages([
                    'email' => 'Приглашение выдано на другой e-mail.',
                ]);
            }

            if (User::query()->where('email', $email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Этот e-mail уже зарегистрирован. Войдите или попросите другое приглашение.',
                ]);
            }

            $user = User::create([
                'role' => UserRole::Client,
                'name' => trim($data['name']),
                'email' => $email,
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);

            $locked->project->members()->updateOrCreate(
                ['user_id' => $user->id],
                ['role' => $locked->role->value, 'added_by' => $locked->created_by],
            );

            $locked->update([
                'used_at' => now(),
                'accepted_user_id' => $user->id,
            ]);

            return $user;
        });

        $this->consents->record($user, 'invite-register', $request, [
            'project_id' => $invite->project_id,
            'invite_id' => $invite->id,
            'privacy_ack' => true,
        ], 'consent');

        $this->consents->record($user, 'invite-register', $request, [
            'project_id' => $invite->project_id,
            'invite_id' => $invite->id,
        ], 'privacy');

        return $user;
    }
}
