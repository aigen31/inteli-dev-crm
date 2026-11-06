<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiAction;
use App\Models\AiConversation;
use App\Models\AiDocument;
use App\Services\Ai\AgentService;
use App\Services\Ai\SessionService;
use App\Services\Documents\DocumentException;
use App\Services\Documents\DocumentExtractor;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssistantController extends Controller
{
    /** Keep in sync with the kernel and AgentService. */
    private const MAX_ATTACHMENTS = 4;

    public function __construct(
        private readonly AgentService $agent,
        private readonly SessionService $sessions,
    ) {}

    /**
     * Full assistant workspace: sessions on the left, one thread on the right.
     */
    public function page(Request $request): Response
    {
        $user = $request->user();
        $activeId = $request->query('c');
        $active = null;

        if (is_string($activeId) && $activeId !== '') {
            $conversation = AiConversation::query()
                ->where('user_id', $user->id)
                ->find($activeId);

            if ($conversation instanceof AiConversation) {
                $active = $this->agent->sessionPayload($user, $conversation);
            }
        }

        return Inertia::render('Assistant/Chat', [
            'sessions' => $this->sessions->list($user),
            'active' => $active,
            'suggestions' => $this->agent->suggestions($user),
            'help' => $this->agent->help($user),
            'limits' => [
                'attachments' => self::MAX_ATTACHMENTS,
            ],
        ]);
    }

    /**
     * Latest thread for the floating panel.
     */
    public function state(Request $request): JsonResponse
    {
        $payload = $this->agent->latest($request->user()) ?? [
            'conversation_id' => null,
            'session' => null,
            'messages' => [],
            'message' => null,
            'pending_actions' => [],
            'pending_action' => null,
            'suggestions' => $this->agent->suggestions($request->user()),
            'help' => $this->agent->help($request->user()),
            'generating' => false,
        ];

        return response()->json($payload);
    }

    public function sessions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'archived' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'sessions' => $this->sessions->list(
                $request->user(),
                $data['q'] ?? null,
                (bool) ($data['archived'] ?? false),
            ),
        ]);
    }

    public function createSession(): JsonResponse
    {
        return response()->json([
            'session' => [
                'id' => $this->sessions->reserveId(),
                'title' => 'Новый диалог',
                'pinned' => false,
                'archived' => false,
                'message_count' => 0,
                'pending_actions' => 0,
            ],
        ], 201);
    }

    public function showSession(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        return response()->json($this->agent->sessionPayload($request->user(), $conversation));
    }

    public function updateSession(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'min:1', 'max:80'],
            'pinned' => ['nullable', 'boolean'],
            'archived' => ['nullable', 'boolean'],
        ]);

        $patch = array_filter(
            [
                'title' => $data['title'] ?? null,
                'pinned' => $data['pinned'] ?? null,
                'archived' => $data['archived'] ?? null,
            ],
            fn ($value) => $value !== null,
        );

        if ($patch === []) {
            return response()->json(['message' => 'Нечего менять.'], 422);
        }

        return response()->json([
            'session' => $this->sessions->update($request->user(), $conversation, $patch),
        ]);
    }

    public function destroySession(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $this->sessions->delete($request->user(), $conversation);

        return response()->json(['ok' => true]);
    }

    /**
     * Accept any text-bearing document, extract its text once and hand the
     * assistant only a manifest. The full text is read on demand.
     */
    public function uploadDocument(
        Request $request,
        DocumentExtractor $extractor,
        FileUploadService $files,
    ): JsonResponse {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('file');
        abort_unless($file !== null, 422, 'Файл не получен.');

        $name = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = [
            'xlsx', 'docx', 'pptx', 'pdf', 'csv', 'tsv', 'txt', 'md', 'markdown',
            'json', 'xml', 'html', 'htm', 'yaml', 'yml', 'log', 'sql', 'ini',
            'conf', 'rtf', 'tex',
        ];

        if (! in_array($extension, $allowed, true)) {
            return response()->json([
                'message' => 'Этот формат не поддержан. Пришлите DOCX, XLSX, PPTX, PDF, CSV или текст.',
            ], 422);
        }

        try {
            $result = $extractor->extract((string) $file->getRealPath(), $name);
            $attachment = $files->upload($file, $request->user());
        } catch (DocumentException|\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $document = AiDocument::create([
            'user_id' => $request->user()->id,
            'file_attachment_id' => $attachment->id,
            'name' => $name,
            'mime_type' => $attachment->mime_type,
            'text' => $result['text'],
            'char_count' => $result['chars'],
        ]);

        return response()->json([
            'id' => $document->id,
            'name' => $document->name,
            'mime' => $document->mime_type,
            'chars' => $document->char_count,
            'preview' => $document->preview(),
            'download_url' => route('files.download', $attachment),
        ], 201);
    }

    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'uuid'],
            'context' => ['nullable', 'array'],
            'context.url' => ['nullable', 'string', 'max:500'],
            'context.page' => ['nullable', 'string', 'max:100'],
            'attachments' => ['nullable', 'array', 'max:'.self::MAX_ATTACHMENTS],
            'attachments.*' => ['uuid'],
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*' => ['uuid'],
            'project_id' => ['nullable', 'uuid'],
        ]);

        $payload = $this->agent->chat(
            $request->user(),
            $data['conversation_id'] ?? null,
            (string) ($data['message'] ?? ''),
            $data['context'] ?? [],
            $data['attachments'] ?? [],
            $data['documents'] ?? [],
            $data['project_id'] ?? null,
        );

        return response()->json($payload);
    }

    public function regenerate(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        return response()->json(
            $this->agent->regenerate($request->user(), $conversation, ['url' => (string) $request->input('url', '')]),
        );
    }

    public function confirm(Request $request, AiAction $action): JsonResponse
    {
        $data = $request->validate([
            'arguments' => ['nullable', 'array'],
            'arguments.form_data' => ['nullable', 'array'],
            'arguments.title' => ['nullable', 'string', 'max:255'],
            'arguments.priority' => ['nullable', 'string', 'max:32'],
            'arguments.deadline' => ['nullable', 'string', 'max:32'],
            'arguments.form_template_id' => ['nullable', 'uuid'],
            'arguments.client_id' => ['nullable', 'string', 'max:128'],
            'arguments.summary' => ['nullable', 'string', 'max:300'],
            'arguments.questions' => ['nullable', 'array'],
            'arguments.tasks' => ['nullable', 'array', 'max:40'],
        ]);

        return response()->json(
            $this->agent->confirm($request->user(), $action, $data['arguments'] ?? []),
        );
    }

    public function reject(Request $request, AiAction $action): JsonResponse
    {
        return response()->json($this->agent->reject($request->user(), $action));
    }

    public function clear(Request $request): JsonResponse
    {
        $this->agent->clear($request->user());

        return response()->json(['ok' => true]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        return response()->json(['suggestions' => $this->agent->suggestions($request->user())]);
    }

    private function authorizeConversation(Request $request, AiConversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
    }
}
