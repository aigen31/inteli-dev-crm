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
            'message' => ['nullable', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'uuid'],
            'context' => ['nullable', 'array'],
            'attachments' => ['nullable', 'array', 'max:4'],
            'attachments.*' => ['uuid'],
        ]);

        try {
            return response()->json($agent->chat(
                $request->user(),
                $data['conversation_id'] ?? null,
                (string) ($data['message'] ?? ''),
                $data['context'] ?? [],
                $data['attachments'] ?? [],
            ));
        } catch (AiException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'assistant_unavailable',
            ], $exception->status);
        }
    }
}
