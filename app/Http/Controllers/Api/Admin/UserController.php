<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Paginated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Read-only user directory for admins: identity, role and activity counts.
 * CV contents, application notes and interview answers are never exposed here.
 */
class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $users = User::query()
            ->when($validated['search'] ?? null, fn ($q, string $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")))
            ->when($validated['role'] ?? null, fn ($q, string $role) => $q->where('role', $role))
            ->withCount(['applications', 'cvs', 'interviews'])
            ->latest()
            ->latest('id')
            ->paginate($validated['per_page'] ?? 15);

        return ApiResponse::success(Paginated::make($users, AdminUserResource::class));
    }
}
