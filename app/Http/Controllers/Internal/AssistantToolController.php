<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Enums\AiActionStatus;
use App\Http\Controllers\Controller;
use App\Models\AiAction;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\FileAttachment;
use App\Models\User;
use App\Services\Ai\AgentService;
use App\Services\Ai\CrmToolbox;
use App\Services\Ai\PromptFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-to-server contract used by the Kiki assistant kernel.
 *
 * The kernel sends the actor, conversation and tool arguments. CRM resolves
 * the actor, enforces role permissions in CrmToolbox, creates confirmation
 * proposals and executes only approved or non-destructive tools.
 *
 * The kernel also pulls images from `/files/{file}` so domain storage and
 * access checks stay here instead of moving to the kernel.
 */
class AssistantToolController extends Controller
{
    /** @var list<string> */
    private const WRITE_TOOLS = [
        'create_client',
        'create_task',
        'create_task_from_form',
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
        $project = null;
        $conversationId = (string) $request->query('conversation_id', '');

        if ($conversationId !== '') {
            $conversation = AiConversation::query()
                ->where('user_id', $user->id)
                ->with('project')
                ->find($conversationId);

            $project = $conversation?->project;

            if ($project !== null && ! $project->isVisibleTo($user)) {
                $project = null;
            }
        }

        return response()->json([
            'system' => $prompts->system($user, $project),
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

        // The project chosen in the chat is the default for plans and studies,
        // so the model does not have to repeat it in every tool call.
        if ($conversation->project_id !== null && empty($arguments['project_id'])) {
            $arguments['project_id'] = $conversation->project_id;
        }

        if ($toolbox->requiresConfirmation($tool)) {
            // A form proposal needs a live template and a normalized schema.
            // If that fails, return the error to the model so it can correct
            // its arguments instead of rendering a broken confirmation card.
            try {
                $form = $toolbox->describesForm($tool)
                    ? $toolbox->formPayload($user, $arguments)
                    : null;
                $plan = $toolbox->describesPlan($tool)
                    ? $toolbox->planPayload($user, $arguments)
                    : null;
            } catch (\Throwable $exception) {
                return response()->json(['result' => ['error' => $exception->getMessage()]], 200);
            }

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
                    'type' => $toolbox->isFormTool($tool) ? 'form' : ($toolbox->isPlanTool($tool) ? 'plan' : 'action'),
                    'form' => $form,
                    'plan' => $plan,
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

    /**
     * Stream one attached image to the kernel.
     *
     * Authorization is deliberately narrow: the actor owns the file, the file
     * is an image, and the file is referenced by a message of that actor's
     * conversation. The kernel never gets a path or a storage credential.
     */
    public function file(Request $request, string $file): StreamedResponse
    {
        $data = $request->validate([
            'conversation_id' => ['required', 'uuid'],
            'actor_id' => ['required', 'string', 'max:128'],
        ]);

        $actor = User::query()->find($data['actor_id']);
        abort_unless($actor instanceof User, 401);

        $attachment = FileAttachment::query()->find($file);
        abort_unless($attachment instanceof FileAttachment, 404);
        abort_unless($attachment->uploaded_by === $actor->id, 403);
        abort_unless(str_starts_with((string) $attachment->mime_type, 'image/'), 415);

        $conversation = AiConversation::query()
            ->where('user_id', $actor->id)
            ->find($data['conversation_id']);
        abort_unless($conversation instanceof AiConversation, 404);

        $attached = AiMessage::query()
            ->where('conversation_id', $conversation->id)
            ->get()
            ->contains(fn (AiMessage $message): bool => collect($message->attachments ?? [])
                ->contains(fn ($item): bool => is_array($item) && ($item['id'] ?? null) === $attachment->id));

        abort_unless($attached, 404);

        return Storage::disk('local')->response($attachment->stored_path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, no-store',
        ]);
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
