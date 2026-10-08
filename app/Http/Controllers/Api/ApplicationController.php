<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApplicationRequest;
use App\Http\Requests\UpdateApplicationRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Notifications\ApplicationCreated;
use App\Notifications\ApplicationStatusChanged;
use App\Services\ActivityLogger;
use App\Support\ApiResponse;
use App\Support\Paginated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** A candidate's personal application tracker. Every query is scoped to the current user. */
class ApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(ApplicationStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $applications = $request->user()->applications()
            ->when($validated['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->with(['job.company'])
            ->latest()
            ->latest('id')
            ->paginate($validated['per_page'] ?? 15);

        return ApiResponse::success(Paginated::make($applications, ApplicationResource::class));
    }

    public function store(StoreApplicationRequest $request, ActivityLogger $activity): JsonResponse
    {
        $user = $request->user();
        $application = $user->applications()->create($request->validated());
        $application->refresh()->load(['job.company']);

        $user->notify(new ApplicationCreated($application));
        $activity->log(
            $user,
            ActivityLogger::APPLICATION_CREATED,
            ($application->status->isSubmitted() ? 'Applied to ' : 'Saved ').$application->job->title,
            $application,
            ['company' => $application->job->company?->name, 'status' => $application->status->value],
        );

        return ApiResponse::success(
            new ApplicationResource($application),
            'Application created.',
            201,
        );
    }

    public function show(Application $application): JsonResponse
    {
        Gate::authorize('view', $application);

        return ApiResponse::success(new ApplicationResource($application->load(['job.company', 'job.skills'])));
    }

    public function update(UpdateApplicationRequest $request, Application $application, ActivityLogger $activity): JsonResponse
    {
        Gate::authorize('update', $application);

        $previous = $application->status;
        $application->update($request->validated());

        if ($application->status !== $previous) {
            $application->loadMissing('job.company');
            $request->user()->notify(new ApplicationStatusChanged($application, $previous));
            $activity->log(
                $request->user(),
                ActivityLogger::APPLICATION_STATUS_CHANGED,
                "{$application->job->title}: {$previous->label()} → {$application->status->label()}",
                $application,
                ['from' => $previous->value, 'to' => $application->status->value],
            );
        }

        return ApiResponse::success(
            new ApplicationResource($application->load(['job.company'])),
            'Application updated.',
        );
    }

    public function destroy(Application $application): JsonResponse
    {
        Gate::authorize('delete', $application);

        $application->delete();

        return ApiResponse::success(message: 'Application deleted.');
    }
}
