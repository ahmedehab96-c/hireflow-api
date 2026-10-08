<?php

namespace App\Http\Resources;

use App\Models\Cv;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never exposes the storage path or the raw extracted text.
 * The full analysis is included on single-CV responses only.
 *
 * @mixin Cv
 */
class CvResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_filename' => $this->original_filename,
            'score' => $this->score,
            'is_analyzed' => $this->isAnalyzed(),
            'text_length' => mb_strlen((string) $this->extracted_text),
            'analysis' => $this->when(! $request->routeIs('cvs.index'), fn () => $this->analysis_json),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
