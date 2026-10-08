<?php

namespace App\Http\Resources;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin Activity */
class ActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'description' => $this->description,
            // Short subject reference (e.g. {"type": "interview", "id": 3}) so the SPA can link to it.
            'subject' => $this->subject_type ? [
                'type' => Str::snake(class_basename($this->subject_type)),
                'id' => $this->subject_id,
            ] : null,
            'properties' => $this->properties ?? (object) [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
