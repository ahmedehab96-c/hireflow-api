<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\InterviewStatus;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Company;
use App\Models\Cv;
use App\Models\Interview;
use App\Models\Job;
use App\Models\User;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/** Aggregate platform numbers for the admin dashboard. Counts only — no personal data. */
class StatsController extends Controller
{
    private const TREND_MONTHS = 6;

    public function __invoke(): JsonResponse
    {
        $roles = User::query()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');
        $jobs = Job::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $applications = Application::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $interviews = Interview::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $byStatus = collect(ApplicationStatus::cases())
            ->mapWithKeys(fn (ApplicationStatus $s) => [$s->value => (int) ($applications[$s->value] ?? 0)]);

        return ApiResponse::success([
            'users' => [
                'total' => (int) $roles->sum(),
                'candidates' => (int) ($roles[UserRole::Candidate->value] ?? 0),
                'admins' => (int) ($roles[UserRole::Admin->value] ?? 0),
                'new_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'companies' => Company::count(),
            'jobs' => [
                'total' => (int) $jobs->sum(),
                ...collect(JobStatus::cases())->mapWithKeys(fn (JobStatus $s) => [$s->value => (int) ($jobs[$s->value] ?? 0)]),
            ],
            'applications' => [
                'total' => (int) $byStatus->sum(),
                'by_status' => $byStatus,
            ],
            'offers' => $byStatus[ApplicationStatus::Offer->value],
            'hired' => $byStatus[ApplicationStatus::Hired->value],
            'interviews' => [
                'total' => (int) $interviews->sum(),
                'completed' => (int) ($interviews[InterviewStatus::Completed->value] ?? 0),
                'average_score' => ($avg = Interview::where('status', InterviewStatus::Completed)->avg('total_score')) !== null ? (int) round((float) $avg) : null,
            ],
            'cvs' => [
                'total' => Cv::count(),
                'analyzed' => Cv::whereNotNull('analysis_json')->count(),
            ],
            'trend' => $this->trend(),
        ]);
    }

    /**
     * New applications and sign-ups per month for the last few months (oldest first).
     *
     * @return list<array{month:string, applications:int, users:int}>
     */
    private function trend(): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(self::TREND_MONTHS - 1);

        return collect(range(0, self::TREND_MONTHS - 1))->map(function (int $i) use ($start) {
            $from = $start->addMonths($i);
            $to = $from->endOfMonth();

            return [
                'month' => $from->format('Y-m'),
                'applications' => Application::whereBetween('created_at', [$from, $to])->count(),
                'users' => User::where('role', UserRole::Candidate)->whereBetween('created_at', [$from, $to])->count(),
            ];
        })->all();
    }
}
