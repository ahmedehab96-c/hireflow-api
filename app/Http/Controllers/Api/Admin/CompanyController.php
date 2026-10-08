<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Support\ApiResponse;
use App\Support\Paginated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $companies = Company::query()
            ->when($validated['search'] ?? null, fn ($q, string $s) => $q->where('name', 'like', "%{$s}%"))
            ->withCount('jobs')
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 15);

        return ApiResponse::success(Paginated::make($companies, CompanyResource::class));
    }

    public function store(CompanyRequest $request): JsonResponse
    {
        $company = Company::create($request->validated());

        return ApiResponse::success(new CompanyResource($company->loadCount('jobs')), 'Company created.', 201);
    }

    public function show(Company $company): JsonResponse
    {
        return ApiResponse::success(new CompanyResource($company->loadCount('jobs')));
    }

    public function update(CompanyRequest $request, Company $company): JsonResponse
    {
        $company->update($request->validated());

        return ApiResponse::success(new CompanyResource($company->loadCount('jobs')), 'Company updated.');
    }

    public function destroy(Company $company): JsonResponse
    {
        abort_if($company->jobs()->exists(), 409, 'Delete or reassign this company\'s jobs first.');

        $company->delete();

        return ApiResponse::success(message: 'Company deleted.');
    }
}
