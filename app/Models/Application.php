<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// user_id is set from the authenticated user, never from input.
#[Fillable(['job_id', 'status', 'applied_at', 'interview_at', 'notes'])]
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'saved',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'applied_at' => 'datetime',
            'interview_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Moving past "saved" without a date records the submission time automatically.
        static::saving(function (Application $application) {
            if ($application->status->isSubmitted() && $application->applied_at === null) {
                $application->applied_at = now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
