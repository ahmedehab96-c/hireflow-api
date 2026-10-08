<?php

namespace App\Models;

use App\Enums\InterviewStatus;
use App\Enums\InterviewType;
use Database\Factories\InterviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

// user_id is always set from the authenticated user, never from input.
#[Fillable(['job_id', 'cv_id', 'type', 'status', 'total_score', 'feedback_json', 'started_at', 'completed_at'])]
class Interview extends Model
{
    /** @use HasFactory<InterviewFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'type' => InterviewType::class,
            'status' => InterviewStatus::class,
            'total_score' => 'integer',
            'feedback_json' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function cv(): BelongsTo
    {
        return $this->belongsTo(Cv::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(InterviewQuestion::class)->orderBy('order');
    }

    public function answers(): HasManyThrough
    {
        return $this->hasManyThrough(InterviewAnswer::class, InterviewQuestion::class);
    }

    public function isPending(): bool
    {
        return $this->status === InterviewStatus::Pending;
    }

    public function isInProgress(): bool
    {
        return $this->status === InterviewStatus::InProgress;
    }

    public function isCompleted(): bool
    {
        return $this->status === InterviewStatus::Completed;
    }

    /** The first unanswered question: the only one the candidate may answer next. */
    public function currentQuestion(): ?InterviewQuestion
    {
        return $this->questions()->whereDoesntHave('answer')->first();
    }
}
