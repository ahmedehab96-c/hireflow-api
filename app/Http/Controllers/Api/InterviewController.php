<?php

namespace App\Http\Controllers\Api;

use App\Enums\InterviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnswerInterviewQuestionRequest;
use App\Http\Requests\StoreInterviewRequest;
use App\Http\Resources\InterviewQuestionResource;
use App\Http\Resources\InterviewResource;
use App\Models\Interview;
use App\Models\InterviewQuestion;
use App\Notifications\InterviewCompleted;
use App\Services\ActivityLogger;
use App\Services\AI\CareerAIService;
use App\Support\ApiResponse;
use App\Support\Paginated;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * AI interview coach. Lifecycle: pending → (start) in_progress → (answer × N) → (complete) completed.
 * AI calls happen before any write, so a failed call leaves the interview unchanged and retryable.
 */
class InterviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(InterviewStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $interviews = $request->user()->interviews()
            ->when($validated['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->with(['job.company', 'cv'])
            ->withCount(['questions', 'answers'])
            ->latest()
            ->latest('id')
            ->paginate($validated['per_page'] ?? 15);

        return ApiResponse::success(Paginated::make($interviews, InterviewResource::class));
    }

    public function store(StoreInterviewRequest $request): JsonResponse
    {
        $interview = new Interview($request->safe()->only(['job_id', 'cv_id', 'type']));
        $interview->user()->associate($request->user());
        $interview->save();

        return ApiResponse::success($this->detail($interview), 'Interview created.', 201);
    }

    public function show(Interview $interview): JsonResponse
    {
        Gate::authorize('view', $interview);

        return ApiResponse::success($this->detail($interview));
    }

    public function start(Interview $interview, CareerAIService $ai, ActivityLogger $activity): JsonResponse
    {
        Gate::authorize('update', $interview);
        abort_unless($interview->isPending(), 409, 'This interview has already been started.');

        $questions = $ai->generateInterviewQuestions($interview->type, $interview->job, $interview->cv);

        DB::transaction(function () use ($interview, $questions) {
            // Re-check under a row lock so two concurrent starts can't both add questions.
            $locked = Interview::whereKey($interview->id)->lockForUpdate()->first();
            abort_unless($locked->isPending(), 409, 'This interview has already been started.');

            $interview->questions()->createMany($questions);
            $interview->update(['status' => InterviewStatus::InProgress, 'started_at' => now()]);
        });

        $activity->log($interview->user, ActivityLogger::INTERVIEW_STARTED, "Started a {$interview->type->label()} interview".$this->forJob($interview), $interview);

        return ApiResponse::success($this->detail($interview), 'Interview started.');
    }

    public function answer(AnswerInterviewQuestionRequest $request, Interview $interview, InterviewQuestion $question, CareerAIService $ai): JsonResponse
    {
        Gate::authorize('update', $interview);
        abort_unless($interview->isInProgress(), 409, 'This interview is not in progress.');
        abort_if($question->answer()->exists(), 409, 'This question has already been answered.');
        abort_unless($interview->currentQuestion()?->is($question), 409, 'Answer the questions in order.');

        $evaluation = $ai->evaluateInterviewAnswer($question, $request->validated('answer'), $interview->job);

        try {
            $question->answer()->create([
                'answer' => $request->validated('answer'),
                'score' => $evaluation['score'],
                'feedback' => $evaluation['feedback'],
                'strengths_json' => $evaluation['strengths'],
                'weaknesses_json' => $evaluation['weaknesses'],
                'improved_answer' => $evaluation['improved_answer'],
            ]);
        } catch (UniqueConstraintViolationException) {
            abort(409, 'This question has already been answered.');
        }

        return ApiResponse::success([
            'question' => new InterviewQuestionResource($question->load('answer')),
            'interview' => $this->detail($interview),
        ], 'Answer evaluated.');
    }

    public function complete(Interview $interview, CareerAIService $ai, ActivityLogger $activity): JsonResponse
    {
        Gate::authorize('update', $interview);
        abort_unless($interview->isInProgress(), 409, $interview->isCompleted() ? 'This interview is already completed.' : 'Start the interview first.');

        $interview->load(['job', 'questions.answer']);
        $answered = $interview->questions->filter(fn (InterviewQuestion $q) => $q->answer !== null);
        abort_if($answered->isEmpty(), 422, 'Answer at least one question before finishing the interview.');

        $feedback = $ai->generateFinalInterviewFeedback($interview);

        // Overall score: average of every question's 0-10 score, scaled to 100. Unanswered questions count as 0.
        $points = $answered->sum(fn (InterviewQuestion $q) => $q->answer->score ?? 0);
        $totalScore = (int) round($points / ($interview->questions->count() * 10) * 100);

        $interview->update([
            'status' => InterviewStatus::Completed,
            'completed_at' => now(),
            'total_score' => $totalScore,
            'feedback_json' => [...$feedback, 'answered' => $answered->count(), 'total_questions' => $interview->questions->count()],
        ]);

        $interview->user->notify(new InterviewCompleted($interview));
        $activity->log($interview->user, ActivityLogger::INTERVIEW_COMPLETED, "Completed a {$interview->type->label()} interview".$this->forJob($interview), $interview, ['score' => $totalScore]);

        return ApiResponse::success($this->detail($interview), 'Interview completed.');
    }

    public function destroy(Interview $interview): JsonResponse
    {
        Gate::authorize('delete', $interview);

        $interview->delete();

        return ApiResponse::success(message: 'Interview deleted.');
    }

    private function forJob(Interview $interview): string
    {
        return $interview->job ? " for {$interview->job->title}" : '';
    }

    private function detail(Interview $interview): InterviewResource
    {
        return new InterviewResource($interview->load(['job.company', 'cv', 'questions.answer']));
    }
}
