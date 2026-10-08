<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records what a candidate did, for their activity timeline.
 * Logging is best-effort: a failure here never breaks the action being logged.
 */
class ActivityLogger
{
    public const APPLICATION_CREATED = 'application_created';

    public const APPLICATION_STATUS_CHANGED = 'application_status_changed';

    public const CV_UPLOADED = 'cv_uploaded';

    public const CV_ANALYZED = 'cv_analyzed';

    public const JOB_MATCHED = 'job_match_completed';

    public const INTERVIEW_STARTED = 'interview_started';

    public const INTERVIEW_COMPLETED = 'interview_completed';

    /** @param  array<string, mixed>  $properties  small, non-sensitive display data only */
    public function log(User $user, string $type, string $description, ?Model $subject = null, array $properties = []): void
    {
        try {
            $activity = new Activity([
                'type' => $type,
                'description' => mb_substr($description, 0, 255),
                'properties' => $properties ?: null,
            ]);
            $activity->user()->associate($user);
            if ($subject) {
                $activity->subject()->associate($subject);
            }
            $activity->save();
        } catch (Throwable $e) {
            Log::warning('Activity logging failed', ['type' => $type, 'error' => $e->getMessage()]);
        }
    }
}
