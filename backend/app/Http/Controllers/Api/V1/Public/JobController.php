<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\JobPosition;
use Illuminate\Http\JsonResponse;

class JobController extends Controller
{
    /**
     * Firma bazlı açık pozisyonlar
     */
    public function index(string $companySlug): JsonResponse
    {
        $company = Company::where('slug', $companySlug)->first();

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Firma bulunamadı',
            ], 404);
        }

        $positions = JobPosition::where('company_id', $company->id)
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('deadline')
                    ->orWhere('deadline', '>=', now());
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($position) use ($company) {
                return [
                    'id' => $position->id,
                    'slug' => $position->slug,
                    'title' => $position->title,
                    'department' => $position->department,
                    'location' => $position->location,
                    'employment_type' => $position->employment_type,
                    'description' => $position->description,
                    'company' => [
                        'name' => $company->name,
                        'logo' => $company->logo ? asset('storage/'.$company->logo) : null,
                    ],
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $positions,
        ]);
    }

    /**
     * Pozisyon detayı (public)
     */
    public function show(string $positionSlug): JsonResponse
    {
        $position = JobPosition::with(['company', 'form'])
            ->where('slug', $positionSlug)
            ->where('status', 'published')
            ->first();

        if (! $position) {
            return response()->json([
                'success' => false,
                'message' => 'Pozisyon bulunamadı veya başvurular kapatılmış',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $position->id,
                'slug' => $position->slug,
                'title' => $position->title,
                'description' => $position->description,
                'requirements' => $position->requirements,
                'benefits' => $position->benefits,
                'department' => $position->department,
                'location' => $position->location,
                'employment_type' => $position->employment_type,
                'salary_range' => $position->salary_range,
                'company' => [
                    'name' => $position->company->name,
                    'logo' => $position->company->logo ? asset('storage/'.$position->company->logo) : null,
                ],
                'form' => $position->form ? [
                    'id' => $position->form->id,
                    'name' => $position->form->name,
                    'fields' => $position->form->fields ?? [],
                ] : null,
            ],
        ]);
    }
}
