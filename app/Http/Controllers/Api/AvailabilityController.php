<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    public function __construct(private readonly AvailabilityService $availability) {}

    public function __invoke(): JsonResponse
    {
        return response()->json($this->availability->snapshot())
            ->header('Cache-Control', 'public, max-age=60');
    }
}
