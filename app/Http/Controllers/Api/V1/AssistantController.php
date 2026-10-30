<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Ai\AgentService;
use App\Services\Ai\AiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function latest(Request $request, AgentService $agent): JsonResponse
    {
        return response()->json($agent->latest($request->user()) ?? [
            'messages' => [],
            'pending_actions' => [],
            'suggestions' => $agent->suggestions($request->user()),
        ]);
    }

    public function chat(Request $request, AgentService $agent): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:4000'],
            'conversation_id' => ['nullable', 'uuid'],
            'context' => ['nullable', 'array'],
        ]);

        try {
            return response()->json($agent->chat(
                $request->user(),
                $data['conversation_id'] ?? null,
                $data['message'],
                $data['context'] ?? [],
            ));
        } catch (AiException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'assistant_unavailable',
            ], $exception->status);
        }
    }
}
