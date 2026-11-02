<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Enums\AiActionStatus;
use App\Http\Controllers\Controller;
use App\Models\AiAction;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\Ai\AgentService;
use App\Services\Ai\CrmToolbox;
use App\Services\Ai\PromptFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server contract used by the Kiki assistant kernel.
 *
 * The kernel sends the actor, conversation and tool arguments. CRM resolves
 * the actor, enforces role permissions in CrmToolbox, creates confirmation
 * proposals and executes only approved or non-destructive tools.
 */
class AssistantToolController extends Controller
{
    /** @var list<string> */
    private const WRITE_TOOLS = [
        'create_client',
        'create_task',
        'update_task_status',
        'apply_task_template',
        'create_task_template',
        'create_invoice',
        'mark_invoice_paid',
        'set_custom_field',
    ];

    public function profile(
        Request $request,
        CrmToolbox $toolbox,
        PromptFactory $prompts,
        AgentService $agent,
    ): JsonResponse {
        $user = $this->actor($request);

        return response()->json([
            'system' => $prompts->system($user),
            'tools' => $toolbox->definitions($user),
            'suggested_next' => array_map(
                fn (string $text): array => ['text' => $text, 'type' => 'lead_request'],
                $agent->suggestions($user),
            ),
        ]);
    }

    public function tool(
        Request $request,
        string $tool,
        CrmToolbox $toolbox,
    ): JsonResponse {
        $data = $request->validate([
            'conversation_id' => ['required', 'uuid'],
            'actor' => ['required', 'array'],
            'actor.id' => ['required', 'string', 'max:128'],
            'actor.role' => ['nullable', 'string', 'max:64'],
            'arguments' => ['nullable', 'array'],
        ]);

        $user = $this->actor($request);
        $conversation = AiConversation::query()
            ->where('user_id', $user->id)
            ->find($data['conversation_id']);

        if (! $conversation instanceof AiConversation) {
            return response()->json(['result' => ['error' => 'Диалог не найден.']], 404);
        }

        $arguments = is_array($data['arguments'] ?? null) ? $data['arguments'] : [];

        if ($toolbox->requiresConfirmation($tool)) {
            $action = AiAction::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'tool_name' => $tool,
                'arguments' => $arguments,
                'status' => AiActionStatus::Proposed,
                'requires_confirmation' => true,
            ]);

            return response()->json([
                'result' => [
                    'status' => 'awaiting_confirmation',
                    'action_id' => $action->id,
                ],
                'pending_action' => [
                    'id' => $action->id,
                    'tool_name' => $action->tool_name,
                    'summary' => $toolbox->summarize($action->tool_name, $action->arguments),
                    'arguments' => $action->arguments,
                ],
            ]);
        }

        try {
            $result = $toolbox->execute($tool, $user, $arguments);
        } catch (\Throwable $exception) {
            Log::warning('Kernel tool failed', [
                'tool' => $tool,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['result' => ['error' => $exception->getMessage()]], 200);
        }

        if (in_array($tool, self::WRITE_TOOLS, true)) {
            AiAction::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'tool_name' => $tool,
                'arguments' => $arguments,
                'result' => $result,
                'status' => AiActionStatus::Executed,
                'requires_confirmation' => false,
                'executed_at' => now(),
            ]);
        }

        return response()->json(['result' => $result]);
    }

    private function actor(Request $request): User
    {
        $actorId = (string) $request->input('actor.id', $request->query('actor_id', ''));
        $user = $actorId !== '' ? User::query()->find($actorId) : null;

        if (! $user instanceof User) {
            abort(401, 'kernel actor is not a CRM user');
        }

        return $user;
    }
}
