<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\JobStatus;
use Database\Factories\JobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'title', 'description', 'location', 'employment_type',
    'salary_min', 'salary_max', 'currency', 'status', 'application_url',
])]
class Job extends Model
{
    /** @use HasFactory<JobFactory> */
    use HasFactory;

    protected $attributes = [
        'currency' => 'AED',
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'status' => JobStatus::class,
            'salary_min' => 'integer',
            'salary_max' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function isActive(): bool
    {
        return $this->status === JobStatus::Active;
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', JobStatus::Active);
    }

    /**
     * Shared listing filters (public and admin).
     *
     * @param  array<string, mixed>  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, function (Builder $q, string $search) {
                $q->where(fn (Builder $w) => $w
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%"));
            })
            ->when($filters['location'] ?? null, fn (Builder $q, string $v) => $q->where('location', 'like', "%{$v}%"))
            ->when($filters['employment_type'] ?? null, fn (Builder $q, string $v) => $q->where('employment_type', $v))
            ->when($filters['company_id'] ?? null, fn (Builder $q, $v) => $q->where('company_id', $v))
            ->when($filters['status'] ?? null, fn (Builder $q, string $v) => $q->where('status', $v))
            ->when($filters['skill'] ?? null, fn (Builder $q, string $v) => $q->whereHas(
                'skills',
                fn (Builder $s) => $s->where('name', $v),
            ));
    }
}
