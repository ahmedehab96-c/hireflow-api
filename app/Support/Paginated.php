<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;

/** {items, meta} shape shared by every paginated endpoint. */
final class Paginated
{
    /** @param class-string<JsonResource> $resource */
    public static function make(LengthAwarePaginator $page, string $resource): array
    {
        return [
            'items' => $resource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ];
    }
}
