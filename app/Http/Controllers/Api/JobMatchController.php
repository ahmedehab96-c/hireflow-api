<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MatchJobRequest;
use App\Models\Job;
use App\Services\ActivityLogger;
use App\Services\AI\CareerAIService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class JobMatchController extends Controller
{
    public function __invoke(MatchJobRequest $request, Job $job, CareerAIService $ai, ActivityLogger $activity): JsonResponse
    {
        abort_unless($job->isActive(), 404);

        // cv_id is validated against the current user's CVs only.
        $cv = $request->user()->cvs()->findOrFail($request->validated('cv_id'));

        abort_if(blank($cv->extracted_text), 422, 'This CV has no readable text to compare. Please upload it again.');

        $match = $ai->matchCvToJob($cv, $job);

        // The match itself isn't stored; the timeline keeps a record that it happened.
        $activity->log($request->user(), ActivityLogger::JOB_MATCHED, "Checked match for {$job->title}", $job, ['score' => $match['match_score']]);

        return ApiResponse::success([
            ...$match,
            'job' => ['id' => $job->id, 'title' => $job->title],
            'cv' => ['id' => $cv->id, 'original_filename' => $cv->original_filename],
        ], 'Match analysis complete.');
    }
}
