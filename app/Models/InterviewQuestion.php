<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['question', 'category', 'order'])]
class InterviewQuestion extends Model
{
    protected function casts(): array
    {
        return ['order' => 'integer'];
    }

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    public function answer(): HasOne
    {
        return $this->hasOne(InterviewAnswer::class);
    }
}
