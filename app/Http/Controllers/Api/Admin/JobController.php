<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\JobStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\JobRequest;
use App\Http\Requests\JobIndexRequest;
use App\Http\Resources\JobResource;
use App\Models\Application;
use App\Models\Job;
use App\Notifications\JobClosed;
use App\Support\ApiResponse;
use App\Support\Paginated;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Admin view of all postings, whatever their status. */
class JobController extends Controller
{
    public function index(JobIndexRequest $request): JsonResponse
    {
        $jobs = Job::query()
            ->filter($request->safe()->except('per_page'))
            ->with(['company', 'skills'])
            ->withCount('applications')
            ->latest()
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success(Paginated::make($jobs, JobResource::class));
    }

    public function store(JobRequest $request): JsonResponse
    {
        $job = DB::transaction(function () use ($request) {
            $job = Job::create($request->safe()->except('skill_ids'));
            $job->skills()->sync($request->validated('skill_ids', []));

            return $job;
        });

        return ApiResponse::success($this->resource($job->refresh()), 'Job created.', 201);
    }

    public function show(Job $job): JsonResponse
    {
        return ApiResponse::success($this->resource($job));
    }

    public function update(JobRequest $request, Job $job): JsonResponse
    {
        $wasActive = $job->isActive();

        DB::transaction(function () use ($request, $job) {
            $job->update($request->safe()->except('skill_ids'));

            if ($request->has('skill_ids')) {
                $job->skills()->sync($request->validated('skill_ids', []));
            }
        });

        // Let candidates still in the process know the job has stopped accepting applications.
        if ($wasActive && $job->status === JobStatus::Closed) {
            $job->applications()
                ->whereNotIn('status', [ApplicationStatus::Rejected, ApplicationStatus::Hired])
                ->with(['user', 'job.company'])
                ->each(fn (Application $application) => $application->user->notify(new JobClosed($application)));
        }

        return ApiResponse::success($this->resource($job), 'Job updated.');
    }

    public function destroy(Job $job): JsonResponse
    {
        abort_if($job->applications()->exists(), 409, 'This job has candidate applications. Close it instead of deleting it.');

        $job->delete();

        return ApiResponse::success(message: 'Job deleted.');
    }

    private function resource(Job $job): JobResource
    {
        return new JobResource($job->load(['company', 'skills'])->loadCount('applications'));
    }
}
