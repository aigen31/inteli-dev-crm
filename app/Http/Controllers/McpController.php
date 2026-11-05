<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskReport;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class McpController extends Controller
{
    public function handle(Request $request, ReportService $reports): JsonResponse
    {
        if (! $this->authorized($request)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32001, 'message' => 'Unauthorized'],
            ], 401);
        }

        $payload = $request->json()->all();
        $id = $payload['id'] ?? null;
        $method = (string) ($payload['method'] ?? '');
        $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];

        if ($method === 'notifications/initialized' || $method === 'notifications/cancelled') {
            return response()->json(null, 202);
        }

        if ($method === 'initialize') {
            return $this->result($id, [
                'protocolVersion' => (string) ($params['protocolVersion'] ?? '2024-11-05'),
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => 'inteli-dev-crm', 'version' => '1.0.0'],
            ]);
        }

        if ($method === 'ping') {
            return $this->result($id, (object) []);
        }

        if ($method === 'tools/list') {
            return $this->result($id, ['tools' => $this->tools()]);
        }

        if ($method === 'tools/call') {
            return $this->callTool($id, $params, $reports);
        }

        return $this->error($id, -32601, 'Method not found: '.$method);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function callTool(mixed $id, array $params, ReportService $reports): JsonResponse
    {
        $name = (string) ($params['name'] ?? '');
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        try {
            $result = match ($name) {
                'create_task_report' => $this->createReport($arguments, $reports),
                'list_users' => $this->listUsers(),
                'list_tasks' => $this->listTasks($arguments),
                'list_reports' => $this->listReports(),
                default => throw new \InvalidArgumentException('Unknown tool: '.$name),
            };

            return $this->result($id, [
                'content' => [['type' => 'text', 'text' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]],
                'structuredContent' => $result,
                'isError' => false,
            ]);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? $exception->getMessage();

            return $this->result($id, [
                'content' => [['type' => 'text', 'text' => (string) $message]],
                'isError' => true,
            ]);
        } catch (\Throwable $exception) {
            return $this->result($id, [
                'content' => [['type' => 'text', 'text' => $exception->getMessage()]],
                'isError' => true,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createReport(array $arguments, ReportService $reports): array
    {
        $author = $this->actor();
        $report = $reports->create($author, [
            'task_id' => $arguments['task_id'] ?? null,
            'title' => $arguments['title'] ?? null,
            'recipient_id' => $arguments['recipient_id'] ?? null,
            'recipient_email' => $arguments['recipient_email'] ?? null,
            'items' => $arguments['items'] ?? null,
            'summary' => isset($arguments['summary']) ? (string) $arguments['summary'] : null,
            'test_url' => $arguments['test_url'] ?? null,
            'screenshot_base64' => $arguments['screenshot_base64'] ?? null,
            'screenshot_mime' => $arguments['screenshot_mime'] ?? null,
            'screenshot_name' => $arguments['screenshot_name'] ?? null,
        ]);

        return [
            'id' => $report->id,
            'title' => $report->title,
            'summary' => $report->content,
            'test_url' => $report->test_url,
            'items' => $report->items->map(fn ($item): array => [
                'summary' => $item->content,
                'test_url' => $item->test_url,
                'screenshot' => $item->screenshot_id !== null,
            ])->values()->all(),
            'screenshot' => $report->screenshot_id !== null,
            'task' => $report->task ? [
                'id' => $report->task->id,
                'title' => $report->task->title,
            ] : null,
            'recipient' => $report->recipient ? [
                'id' => $report->recipient->id,
                'name' => $report->recipient->name,
                'email' => $report->recipient->email,
            ] : null,
            'url' => route('reports.show', $report),
            'sent_at' => $report->sent_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listUsers(): array
    {
        return [
            'users' => User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(100)
                ->get(['id', 'name', 'email', 'role'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function listTasks(array $arguments): array
    {
        $query = Task::query()->with('project:id,key,name')->latest();
        $limit = min(max((int) ($arguments['limit'] ?? 20), 1), 50);

        if (filled($arguments['query'] ?? null)) {
            $query->where('title', 'like', '%'.(string) $arguments['query'].'%');
        }

        if (filled($arguments['project_id'] ?? null)) {
            $query->where('project_id', $arguments['project_id']);
        }

        return [
            'tasks' => $query->limit($limit)->get()->map(fn (Task $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'reference' => $task->project ? $task->project->key.'-'.$task->number : null,
                'status' => $task->status->value,
                'project' => $task->project?->name,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listReports(): array
    {
        return [
            'reports' => TaskReport::query()
                ->with(['recipient:id,name', 'task:id,title'])
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (TaskReport $report): array => [
                    'id' => $report->id,
                    'summary' => mb_substr($report->content, 0, 300),
                    'recipient' => $report->recipient?->name,
                    'task' => $report->task?->title,
                    'sent_at' => $report->sent_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    private function actor(): User
    {
        $id = (string) (config('mcp.actor_id') ?? '');

        if ($id !== '') {
            $user = User::query()->where('is_active', true)->find($id);

            if ($user !== null) {
                return $user;
            }
        }

        return User::query()->where('role', 'admin')->orderBy('created_at')->firstOrFail();
    }

    private function authorized(Request $request): bool
    {
        $token = (string) (config('mcp.token') ?? '');

        if ($token === '') {
            return false;
        }

        $header = (string) $request->header('Authorization', '');
        $provided = str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : (string) $request->header('X-Mcp-Token', '');

        return $provided !== '' && hash_equals($token, $provided);
    }

    private function result(mixed $id, array $result): JsonResponse
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    private function error(mixed $id, int $code, string $message): JsonResponse
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tools(): array
    {
        return [
            [
                'name' => 'create_task_report',
                'description' => 'Create a task completion report in the CRM and send it to a user. Optional title; repeatable groups of short explanation, optional test URL, optional screenshot.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['items'],
                    'properties' => [
                        'items' => [
                            'type' => 'array',
                            'description' => 'Повторяемые группы отчёта: объяснение + ссылка + скриншот. Минимум одна.',
                            'items' => [
                                'type' => 'object',
                                'required' => ['summary'],
                                'properties' => [
                                    'summary' => ['type' => 'string', 'description' => 'Краткое объяснение выполненной задачи.'],
                                    'test_url' => ['type' => 'string', 'description' => 'Ссылка на страницу сайта для тестирования.'],
                                    'screenshot_base64' => ['type' => 'string'],
                                    'screenshot_mime' => ['type' => 'string', 'enum' => ['image/png', 'image/jpeg', 'image/webp', 'image/gif']],
                                ],
                            ],
                        ],
                        'title' => ['type' => 'string', 'description' => 'Заголовок отчёта (необязательно).'],
                        'summary' => ['type' => 'string', 'description' => 'Одна группа без массива (устаревший короткий формат).'],
                        'recipient_id' => ['type' => 'string', 'description' => 'UUID получателя. Узнать через list_users.'],
                        'recipient_email' => ['type' => 'string', 'description' => 'Email получателя, если нет UUID.'],
                        'task_id' => ['type' => 'string', 'description' => 'UUID задачи (необязательно).'],
                        'test_url' => ['type' => 'string', 'description' => 'Ссылка на страницу сайта для тестирования (необязательно).'],
                        'screenshot_base64' => ['type' => 'string', 'description' => 'Скриншот в base64 (необязательно).'],
                        'screenshot_mime' => ['type' => 'string', 'enum' => ['image/png', 'image/jpeg', 'image/webp', 'image/gif']],
                    ],
                ],
            ],
            [
                'name' => 'list_users',
                'description' => 'Active CRM users with ids, emails and roles. Use it to pick a report recipient.',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'list_tasks',
                'description' => 'Recent tasks with references; optionally filter by query or project_id.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'project_id' => ['type' => 'string'],
                        'limit' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'name' => 'list_reports',
                'description' => 'Recent reports delivered from the CRM.',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
        ];
    }
}
