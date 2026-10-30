<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiAction;
use App\Services\Ai\AgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function show(Request $request, AgentService $agent): JsonResponse
    {
        $payload = $agent->latest($request->user()) ?? [
            'messages' => [],
            'pending_actions' => [],
            'suggestions' => $agent->suggestions($request->user()),
        ];

        return response()->json($payload);
    }

    public function chat(Request $request, AgentService $agent): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:4000'],
            'conversation_id' => ['nullable', 'uuid'],
            'context' => ['nullable', 'array'],
            'context.url' => ['nullable', 'string', 'max:500'],
            'context.page' => ['nullable', 'string', 'max:100'],
        ]);

        $payload = $agent->chat(
            $request->user(),
            $data['conversation_id'] ?? null,
            $data['message'],
            $data['context'] ?? [],
        );

        return response()->json($payload);
    }

    public function confirm(Request $request, AiAction $action, AgentService $agent): JsonResponse
    {
        return response()->json($agent->confirm($request->user(), $action));
    }

    public function reject(Request $request, AiAction $action, AgentService $agent): JsonResponse
    {
        return response()->json($agent->reject($request->user(), $action));
    }

    public function clear(Request $request, AgentService $agent): JsonResponse
    {
        $agent->clear($request->user());

        return response()->json(['ok' => true]);
    }

    public function suggestions(Request $request, AgentService $agent): JsonResponse
    {
        return response()->json(['suggestions' => $agent->suggestions($request->user())]);
    }
}
