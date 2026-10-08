<?php

namespace App\Models;

use Database\Factories\CvFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

// user_id is always set from the authenticated user, never from input.
#[Fillable(['original_filename', 'file_path', 'extracted_text', 'analysis_json', 'score'])]
#[Hidden(['file_path', 'extracted_text'])]
class Cv extends Model
{
    /** @use HasFactory<CvFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'analysis_json' => 'array',
            'score' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The stored file goes with the record.
        static::deleted(function (Cv $cv) {
            Storage::disk(config('cv.disk'))->delete($cv->file_path);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAnalyzed(): bool
    {
        return $this->analysis_json !== null;
    }
}
