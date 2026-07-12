<?php

namespace App\Http\Controllers\Api\V1\Recruitment;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\JobPosition;
use App\Services\LookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class JobPositionController extends BaseController
{
    public function __construct(
        private readonly LookupService $lookups
    ) {}

    /**
     * İş pozisyonları listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = JobPosition::with(['form:id,name'])
            ->withCount('applications');

        // Arama
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        // Durum filtresi
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Departman filtresi
        if ($request->has('department')) {
            $query->where('department', $request->department);
        }

        // Sıralama
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $positions = $query->paginate($request->get('per_page', 15));

        return $this->paginated($positions, 'İş pozisyonları listelendi');
    }

    /**
     * Pozisyon detay
     */
    public function show(JobPosition $position): JsonResponse
    {
        $position->load(['form', 'createdBy:id,name', 'updatedBy:id,name']);
        $position->loadCount(['applications', 'applications as new_applications_count' => function ($query) {
            $query->where('status', 'new');
        }]);

        return $this->success($position);
    }

    /**
     * Pozisyon oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'responsibilities' => 'nullable|string',
            'department' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:100',
            'employment_type' => 'sometimes|nullable|string|max:100',
            'experience_level' => 'sometimes|in:entry,mid,senior,lead,manager',
            'salary_min' => 'nullable|numeric|min:0',
            'salary_max' => 'nullable|numeric|min:0|gte:salary_min',
            'salary_visible' => 'sometimes|boolean',
            'form_id' => 'nullable|exists:application_forms,id',
            'positions_count' => 'sometimes|integer|min:1',
            'application_deadline' => 'nullable|date|after:today',
        ]);

        // Company ID kontrolü
        $companyId = $this->getCompanyId();
        if (! $companyId) {
            return $this->error('Bu işlem için bir firmaya bağlı olmanız gerekiyor.', 403);
        }

        $this->lookups->assertValid(
            LookupService::TYPE_WORK_TYPE,
            $validated['employment_type'] ?? null,
            $companyId,
            'employment_type'
        );

        $validated['slug'] = Str::slug($validated['title']).'-'.uniqid();
        $validated['company_id'] = $companyId;
        $validated['status'] = 'draft';

        $position = JobPosition::create($validated);

        ActivityLog::log('create', $position, 'İş pozisyonu oluşturuldu: '.$position->title);

        return $this->created($position, 'İş pozisyonu oluşturuldu');
    }

    /**
     * Pozisyon güncelle
     */
    public function update(Request $request, JobPosition $position): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'requirements' => 'sometimes|nullable|string',
            'responsibilities' => 'sometimes|nullable|string',
            'department' => 'sometimes|nullable|string|max:100',
            'location' => 'sometimes|nullable|string|max:100',
            'employment_type' => 'sometimes|nullable|string|max:100',
            'experience_level' => 'sometimes|in:entry,mid,senior,lead,manager',
            'salary_min' => 'sometimes|nullable|numeric|min:0',
            'salary_max' => 'sometimes|nullable|numeric|min:0',
            'salary_visible' => 'sometimes|boolean',
            'form_id' => 'sometimes|nullable|exists:application_forms,id',
            'status' => 'sometimes|in:draft,active,paused,closed',
            'positions_count' => 'sometimes|integer|min:1',
            'application_deadline' => 'sometimes|nullable|date',
        ]);

        $this->lookups->assertValid(
            LookupService::TYPE_WORK_TYPE,
            $validated['employment_type'] ?? null,
            $this->getCompanyId(),
            'employment_type'
        );

        // Yayınlama
        if (isset($validated['status']) && $validated['status'] === 'active' && $position->status !== 'active') {
            $validated['published_at'] = now();
        }

        $position->update($validated);

        ActivityLog::log('update', $position, 'İş pozisyonu güncellendi: '.$position->title);

        return $this->success($position->fresh(), 'İş pozisyonu güncellendi');
    }

    /**
     * Pozisyon sil
     */
    public function destroy(JobPosition $position): JsonResponse
    {
        // Başvurusu olan pozisyon silinemez
        if ($position->applications()->count() > 0) {
            return $this->error('Bu pozisyona başvurular var. Önce pozisyonu kapatın.', 400);
        }

        $title = $position->title;
        $position->delete();

        ActivityLog::log('delete', null, 'İş pozisyonu silindi: '.$title);

        return $this->success(null, 'İş pozisyonu silindi');
    }
}
