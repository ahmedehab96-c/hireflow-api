<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Support\ApiResponse;
use App\Support\Paginated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The candidate's own activity timeline. */
class ActivityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);

        $activities = $request->user()->activities()
            ->latest()
            ->latest('id')
            ->paginate($validated['per_page'] ?? 15);

        return ApiResponse::success(Paginated::make($activities, ActivityResource::class));
    }
}
