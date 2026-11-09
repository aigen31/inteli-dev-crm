<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Mcp\CrmMcpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class McpController extends Controller
{
    public function handle(Request $request, CrmMcpService $crm): JsonResponse
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
                'serverInfo' => ['name' => 'inteli-dev-crm', 'version' => '1.1.0'],
                'instructions' => 'CRM tools for the owner account. Use read tools first. Money and destructive actions require confirm=true. Reports use the description+link template.',
            ]);
        }

        if ($method === 'ping') {
            return $this->result($id, (object) []);
        }

        if ($method === 'tools/list') {
            return $this->result($id, ['tools' => $crm->definitions()]);
        }

        if ($method === 'tools/call') {
            return $this->callTool($id, $params, $crm, $request);
        }

        return $this->error($id, -32601, 'Method not found: '.$method);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function callTool(mixed $id, array $params, CrmMcpService $crm, Request $request): JsonResponse
    {
        $name = (string) ($params['name'] ?? '');
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        try {
            $actor = $this->actor();
            $request->setUserResolver(fn (): User => $actor);
            $result = $crm->call($name, $arguments, $actor, $request);

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
}
