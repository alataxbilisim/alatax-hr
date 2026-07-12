<?php

namespace App\Http\Controllers\Api\V1\Recruitment;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Services\LookupService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterviewController extends BaseController
{
    public function __construct(
        protected LookupService $lookups,
    ) {}
    /**
     * Mülakat listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = Interview::with(['application.position', 'interviewer:id,name'])
            ->where('company_id', $this->getCompanyId());

        // Durum filtresi
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Tarih aralığı
        if ($request->filled('start_date')) {
            $query->where('scheduled_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('scheduled_at', '<=', $request->end_date.' 23:59:59');
        }

        // Pozisyon filtresi
        if ($request->filled('position_id')) {
            $query->where('job_position_id', $request->position_id);
        }

        // Arama
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('application', function ($aq) use ($search) {
                        $aq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $interviews = $query->orderBy('scheduled_at', 'desc')
            ->paginate($request->per_page ?? 20);

        $data = $interviews->through(function ($interview) {
            return $this->formatInterview($interview);
        });

        return $this->success($data);
    }

    /**
     * Takvim görünümü için mülakatlar
     */
    public function calendar(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $interviews = Interview::with(['application', 'application.position', 'interviewer:id,name'])
            ->where('company_id', $this->getCompanyId())
            ->whereBetween('scheduled_at', [$startDate, $endDate.' 23:59:59'])
            ->get()
            ->map(function ($interview) {
                return [
                    'id' => $interview->id,
                    'title' => $interview->title,
                    'start' => $interview->scheduled_at->toIso8601String(),
                    'end' => $interview->scheduled_at->addMinutes($interview->duration_minutes)->toIso8601String(),
                    'applicant_name' => $interview->application
                        ? $interview->application->first_name.' '.$interview->application->last_name
                        : 'Bilinmiyor',
                    'position' => $interview->application?->position?->title,
                    'type' => $interview->type,
                    'status' => $interview->status,
                    'interviewer' => $interview->interviewer?->name,
                    'location' => $interview->location,
                    'meeting_link' => $interview->meeting_link,
                ];
            });

        return $this->success($interviews);
    }

    /**
     * Mülakat detayı
     */
    public function show(int $id): JsonResponse
    {
        $interview = Interview::with([
            'application.position',
            'application.statusLogs.user',
            'interviewer:id,name,email',
            'scorecards',
        ])
            ->where('company_id', $this->getCompanyId())
            ->findOrFail($id);

        return $this->success($this->formatInterview($interview, true));
    }

    /**
     * Yeni mülakat oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'job_application_id' => 'required|exists:job_applications,id',
            'title' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'scheduled_at' => 'required|date|after:now',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
            'location' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|url',
            'notes' => 'nullable|string',
            'interviewer_id' => 'required|exists:users,id',
        ]);

        $companyId = $this->getCompanyId();
        $this->lookups->assertValid(
            LookupService::TYPE_INTERVIEW_TYPE,
            $validated['type'],
            $companyId,
            'type'
        );

        $application = JobApplication::where('company_id', $companyId)
            ->findOrFail($validated['job_application_id']);

        $interview = Interview::create([
            'company_id' => $companyId,
            'job_application_id' => $validated['job_application_id'],
            'job_position_id' => $application->job_position_id,
            'title' => $validated['title'],
            'type' => $validated['type'],
            'scheduled_at' => $validated['scheduled_at'],
            'duration_minutes' => $validated['duration_minutes'] ?? 60,
            'location' => $validated['location'],
            'meeting_link' => $validated['meeting_link'],
            'notes' => $validated['notes'],
            'interviewer_id' => $validated['interviewer_id'],
            'status' => 'scheduled',
            'created_by' => auth()->id(),
        ]);

        // Başvuru durumunu güncelle
        if ($application->status === \App\Enums\JobApplicationStatus::New || $application->status === \App\Enums\JobApplicationStatus::Reviewing) {
            $application->update(['status' => \App\Enums\JobApplicationStatus::InterviewScheduled]);
        }

        ActivityLog::log('create', $interview, 'Mülakat planlandı: '.$interview->title);

        return $this->created($this->formatInterview($interview->load(['application.position', 'interviewer'])), 'Mülakat başarıyla oluşturuldu');
    }

    /**
     * Mülakat güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $interview = Interview::where('company_id', $this->getCompanyId())
            ->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|max:100',
            'scheduled_at' => 'sometimes|date',
            'duration_minutes' => 'sometimes|integer|min:15|max:480',
            'location' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|url',
            'notes' => 'nullable|string',
            'interviewer_id' => 'sometimes|exists:users,id',
        ]);

        if (array_key_exists('type', $validated)) {
            $this->lookups->assertValid(
                LookupService::TYPE_INTERVIEW_TYPE,
                $validated['type'] ?? null,
                $this->getCompanyId(),
                'type'
            );
        }

        $interview->update($validated);

        ActivityLog::log('update', $interview, 'Mülakat güncellendi: '.$interview->title);

        return $this->success($this->formatInterview($interview->load(['application.position', 'interviewer'])), 'Mülakat güncellendi');
    }

    /**
     * Mülakatı tamamla ve değerlendirme gir
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $interview = Interview::where('company_id', $this->getCompanyId())
            ->findOrFail($id);

        $validated = $request->validate([
            'overall_rating' => 'required|integer|min:1|max:5',
            'recommendation' => 'required|string|max:100',
            'feedback' => 'nullable|string',
            'scorecards' => 'nullable|array',
            'scorecards.*.criteria_name' => 'required_with:scorecards|string',
            'scorecards.*.score' => 'required_with:scorecards|integer|min:1|max:5',
            'scorecards.*.notes' => 'nullable|string',
        ]);

        $this->lookups->assertValid(
            LookupService::TYPE_INTERVIEW_RECOMMENDATION,
            $validated['recommendation'],
            $this->getCompanyId(),
            'recommendation'
        );

        $interview->update([
            'status' => 'completed',
            'overall_rating' => $validated['overall_rating'],
            'recommendation' => $validated['recommendation'],
            'feedback' => $validated['feedback'],
        ]);

        // Scorecard kaydet
        if (! empty($validated['scorecards'])) {
            foreach ($validated['scorecards'] as $scorecard) {
                $interview->scorecards()->create($scorecard);
            }
        }

        // Başvuru durumunu güncelle
        $interview->application->update(['status' => 'interviewed']);

        ActivityLog::log('update', $interview, 'Mülakat tamamlandı: '.$interview->title.' - Öneri: '.$validated['recommendation']);

        return $this->success($this->formatInterview($interview->load(['application.position', 'interviewer', 'scorecards'])), 'Mülakat tamamlandı');
    }

    /**
     * Mülakatı iptal et
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $interview = Interview::where('company_id', $this->getCompanyId())
            ->findOrFail($id);

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $interview->update([
            'status' => 'cancelled',
            'notes' => $validated['notes'] ?? $interview->notes,
        ]);

        ActivityLog::log('update', $interview, 'Mülakat iptal edildi: '.$interview->title);

        return $this->success(null, 'Mülakat iptal edildi');
    }

    /**
     * Mülakatı sil
     */
    public function destroy(int $id): JsonResponse
    {
        $interview = Interview::where('company_id', $this->getCompanyId())
            ->findOrFail($id);

        $title = $interview->title;
        $interview->delete();

        ActivityLog::log('delete', null, 'Mülakat silindi: '.$title);

        return $this->success(null, 'Mülakat silindi');
    }

    /**
     * Mülakat tiplerini getir
     */
    public function getTypes(): JsonResponse
    {
        return $this->success(Interview::getTypeLabels());
    }

    /**
     * Format interview data
     */
    private function formatInterview(Interview $interview, bool $detailed = false): array
    {
        $data = [
            'id' => $interview->id,
            'title' => $interview->title,
            'type' => $interview->type,
            'type_label' => Interview::getTypeLabels()[$interview->type] ?? $interview->type,
            'scheduled_at' => $interview->scheduled_at?->toDateTimeString(),
            'scheduled_at_formatted' => $interview->scheduled_at?->format('d.m.Y H:i'),
            'duration_minutes' => $interview->duration_minutes,
            'location' => $interview->location,
            'meeting_link' => $interview->meeting_link,
            'status' => $interview->status,
            'overall_rating' => $interview->overall_rating,
            'recommendation' => $interview->recommendation,
            'recommendation_label' => $interview->recommendation
                ? (Interview::getRecommendationLabels()[$interview->recommendation] ?? $interview->recommendation)
                : null,
            'notes' => $interview->notes,
            'feedback' => $interview->feedback,
            'application' => $interview->application ? [
                'id' => $interview->application->id,
                'applicant_name' => $interview->application->first_name.' '.$interview->application->last_name,
                'email' => $interview->application->email,
                'phone' => $interview->application->phone,
                'position' => $interview->application->position ? [
                    'id' => $interview->application->position->id,
                    'title' => $interview->application->position->title,
                ] : null,
            ] : null,
            'interviewer' => $interview->interviewer ? [
                'id' => $interview->interviewer->id,
                'name' => $interview->interviewer->name,
            ] : null,
            'created_at' => $interview->created_at->toDateTimeString(),
        ];

        if ($detailed && $interview->relationLoaded('scorecards')) {
            $data['scorecards'] = $interview->scorecards->map(fn ($s) => [
                'id' => $s->id,
                'criteria_name' => $s->criteria_name,
                'score' => $s->score,
                'notes' => $s->notes,
            ]);
        }

        return $data;
    }
}
