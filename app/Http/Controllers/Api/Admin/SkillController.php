<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SkillRequest;
use App\Http\Resources\SkillResource;
use App\Models\Skill;
use App\Support\ApiResponse;
use App\Support\Paginated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $skills = Skill::query()
            ->when($validated['search'] ?? null, fn ($q, string $s) => $q->where('name', 'like', "%{$s}%"))
            ->withCount('jobs')
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 50);

        return ApiResponse::success(Paginated::make($skills, SkillResource::class));
    }

    public function store(SkillRequest $request): JsonResponse
    {
        $skill = Skill::create($request->validated());

        return ApiResponse::success(new SkillResource($skill), 'Skill created.', 201);
    }

    public function show(Skill $skill): JsonResponse
    {
        return ApiResponse::success(new SkillResource($skill->loadCount('jobs')));
    }

    public function update(SkillRequest $request, Skill $skill): JsonResponse
    {
        $skill->update($request->validated());

        return ApiResponse::success(new SkillResource($skill), 'Skill updated.');
    }

    /** Detaches the skill from any jobs (pivot rows cascade). */
    public function destroy(Skill $skill): JsonResponse
    {
        $skill->delete();

        return ApiResponse::success(message: 'Skill deleted.');
    }
}
