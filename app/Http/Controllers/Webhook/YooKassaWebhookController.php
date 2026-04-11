<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Actions\Payment\HandleWebhookAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YooKassaWebhookController extends Controller
{
    public function handle(Request $request, HandleWebhookAction $action): JsonResponse
    {
        $action->execute($request->all());

        return response()->json(['status' => 'ok']);
    }
}
