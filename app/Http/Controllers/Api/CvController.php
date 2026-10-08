<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCvRequest;
use App\Http\Resources\CvResource;
use App\Models\Cv;
use App\Services\ActivityLogger;
use App\Services\AI\CareerAIService;
use App\Services\Cv\CvUploader;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** A candidate's CVs. Every query is scoped to the current user; others' CVs return 404. */
class CvController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cvs = $request->user()->cvs()->latest()->latest('id')->get();

        return ApiResponse::success(CvResource::collection($cvs));
    }

    public function store(StoreCvRequest $request, CvUploader $uploader, ActivityLogger $activity): JsonResponse
    {
        $cv = $uploader->upload($request->user(), $request->file('file'));
        $activity->log($request->user(), ActivityLogger::CV_UPLOADED, "Uploaded {$cv->original_filename}", $cv);

        return ApiResponse::success(new CvResource($cv), 'CV uploaded.', 201);
    }

    public function show(Cv $cv): JsonResponse
    {
        Gate::authorize('view', $cv);

        return ApiResponse::success(new CvResource($cv));
    }

    public function destroy(Cv $cv): JsonResponse
    {
        Gate::authorize('delete', $cv);

        $cv->delete();

        return ApiResponse::success(message: 'CV deleted.');
    }

    public function analyze(Cv $cv, CareerAIService $ai, ActivityLogger $activity): JsonResponse
    {
        Gate::authorize('analyze', $cv);

        abort_if(blank($cv->extracted_text), 422, 'This CV has no readable text to analyze. Please upload it again.');

        // A failed AI call throws before anything is saved, so a previous analysis is kept intact.
        $analysis = $ai->analyzeCv($cv->extracted_text);

        $cv->update(['analysis_json' => $analysis, 'score' => $analysis['score']]);
        $activity->log($cv->user, ActivityLogger::CV_ANALYZED, "Analyzed {$cv->original_filename}", $cv, ['score' => $cv->score]);

        return ApiResponse::success(new CvResource($cv), 'CV analyzed.');
    }
}
