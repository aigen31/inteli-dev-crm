<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

Route::get('/v1/ping', fn () => response()->json([
    'data' => ['status' => 'ok', 'version' => 'v1'],
]));

Route::prefix('v1')
    ->middleware(['api.token', 'throttle:api'])
    ->group(function (): void {
        Route::get('/health', [V1\MetaController::class, 'health']);
        Route::get('/me', [V1\MeController::class, 'show']);
        Route::get('/meta/overview', [V1\MetaController::class, 'overview']);

        Route::get('/custom-fields', [V1\CustomFieldController::class, 'index']);
        Route::get('/custom-fields/{entity}/{id}/values', [V1\CustomFieldController::class, 'values'])
            ->whereIn('entity', ['client', 'task', 'invoice']);

        Route::middleware('api.ability:read')->group(function (): void {
            Route::get('/assistant/latest', [V1\AssistantController::class, 'latest']);
            Route::get('/clients', [V1\ClientController::class, 'index']);
            Route::get('/clients/{client}', [V1\ClientController::class, 'show']);
            Route::get('/tasks', [V1\TaskController::class, 'index']);
            Route::get('/tasks/{task}', [V1\TaskController::class, 'show']);
            Route::get('/task-templates', [V1\TaskTemplateController::class, 'index']);
            Route::get('/task-templates/{taskTemplate}', [V1\TaskTemplateController::class, 'show']);
            Route::get('/invoices', [V1\InvoiceController::class, 'index']);
            Route::get('/invoices/{invoice}', [V1\InvoiceController::class, 'show']);
        });

        Route::middleware('api.ability:write')->group(function (): void {
            Route::post('/assistant/chat', [V1\AssistantController::class, 'chat'])->middleware('throttle:assistant');
            Route::post('/clients', [V1\ClientController::class, 'store']);
            Route::put('/clients/{client}', [V1\ClientController::class, 'update']);

            Route::post('/tasks', [V1\TaskController::class, 'store']);
            Route::put('/tasks/{task}', [V1\TaskController::class, 'update']);
            Route::patch('/tasks/{task}/status', [V1\TaskController::class, 'status']);

            Route::post('/task-templates', [V1\TaskTemplateController::class, 'store']);
            Route::put('/task-templates/{taskTemplate}', [V1\TaskTemplateController::class, 'update']);
            Route::post('/task-templates/{taskTemplate}/apply', [V1\TaskTemplateController::class, 'apply']);

            Route::post('/invoices', [V1\InvoiceController::class, 'store']);
            Route::patch('/invoices/{invoice}/mark-paid', [V1\InvoiceController::class, 'markPaid']);

            Route::put('/custom-fields/{entity}/{id}/values', [V1\CustomFieldController::class, 'updateValues'])
                ->whereIn('entity', ['client', 'task', 'invoice']);
        });
    });
