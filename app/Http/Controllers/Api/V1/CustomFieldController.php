<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CustomFieldEntity;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomFieldResource;
use App\Models\CustomField;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\User;
use App\Services\CustomFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomFieldController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $entity = $request->input('entity_type')
            ? CustomFieldEntity::tryFrom((string) $request->input('entity_type'))
            : null;

        $fields = CustomField::query()
            ->when($entity !== null, fn ($query) => $query->forEntity($entity))
            ->orderBy('entity_type')
            ->orderBy('sort_order')
            ->get();

        return CustomFieldResource::collection($fields);
    }

    public function values(
        Request $request,
        string $entity,
        string $id,
        CustomFieldService $service,
    ): JsonResponse {
        $model = $this->resolveModel($request, $entity, $id);

        return response()->json([
            'data' => [
                'entity_type' => $entity,
                'entity_id' => $model->getKey(),
                'fields' => $service->serializedValues($model),
            ],
        ]);
    }

    public function updateValues(
        Request $request,
        string $entity,
        string $id,
        CustomFieldService $service,
    ): JsonResponse {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'fields' => ['required', 'array'],
            'fields.*' => ['nullable'],
        ]);

        $model = $this->resolveModel($request, $entity, $id);
        $service->sync($model, $data['fields'], $request->user());

        return response()->json([
            'data' => [
                'entity_type' => $entity,
                'entity_id' => $model->getKey(),
                'fields' => $service->serializedValues($model),
            ],
        ]);
    }

    private function resolveModel(Request $request, string $entity, string $id): User|Task|Invoice
    {
        $type = CustomFieldEntity::tryFrom($entity);

        if ($type === null) {
            abort(404, 'Неизвестная сущность.');
        }

        $model = $type->modelClass()::query()->findOrFail($id);

        if ($request->user()->isClient()) {
            $ownerId = $model instanceof User ? $model->id : $model->client_id;

            if ($ownerId !== $request->user()->id) {
                abort(403);
            }
        }

        return $model;
    }
}
