<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Operations\Support\Readiness;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/admin/readiness (admin). Never calls the model. 503 when a
 * required dependency (database, cache, or a required worker) is not ready.
 */
class ReadinessController extends Controller
{
    public function __invoke(Readiness $readiness): JsonResponse
    {
        $result = $readiness->handle();

        return ApiResponse::success($result, status: $result['ready'] ? 200 : 503);
    }
}
