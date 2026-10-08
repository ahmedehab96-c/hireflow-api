<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\JobIndexRequest;
use App\Http\Resources\JobResource;
use App\Models\Job;
use App\Support\ApiResponse;
use App\Support\Paginated;
use Illuminate\Http\JsonResponse;

/** Public job board: only active postings are visible. */
class JobController extends Controller
{
    public function index(JobIndexRequest $request): JsonResponse
    {
        $jobs = Job::query()
            ->active()
            ->filter($request->safe()->except(['status', 'per_page']))
            ->with(['company', 'skills'])
            ->latest()
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success(Paginated::make($jobs, JobResource::class));
    }

    public function show(Job $job): JsonResponse
    {
        abort_unless($job->isActive(), 404);

        return ApiResponse::success(new JobResource($job->load(['company', 'skills'])));
    }
}
