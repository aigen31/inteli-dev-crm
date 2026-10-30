<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiSettingsService;
use App\Services\Ai\OpenAiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AiSettingsController extends Controller
{
    public function update(Request $request, AiSettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'model' => ['nullable', 'string', 'max:150'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['nullable', 'integer', 'min:100', 'max:8000'],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'clear_key' => ['sometimes', 'boolean'],
        ]);

        $settings->save($data);

        return redirect()
            ->route('admin.settings.edit', ['tab' => 'ai'])
            ->with('success', 'Настройки ИИ сохранены.');
    }

    public function test(OpenAiClient $client): JsonResponse
    {
        return response()->json($client->test());
    }
}
