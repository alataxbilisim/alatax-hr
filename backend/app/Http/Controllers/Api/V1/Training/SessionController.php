<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\Training;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\LookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends BaseController
{
    public function __construct(
        protected LookupService $lookups,
    ) {}

    /**
     * Eğitim oturumlarını listele
     */
    public function index(Request $request): JsonResponse
    {
        $query = TrainingSession::whereHas('training', function ($q) {
            $q->where('company_id', $this->getCompanyId());
        })->with(['training:id,title,type,duration_hours']);

        if ($request->has('training_id')) {
            $query->where('training_id', $request->training_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('upcoming')) {
            $query->upcoming();
        }

        $sessions = $query->withCount('participants')
            ->orderBy('start_date', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->success($sessions, 'Oturumlar listelendi');
    }

    /**
     * Yeni oturum oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'training_id' => 'required|exists:trainings,id',
            'start_date' => 'required|date|after:now',
            'end_date' => 'required|date|after:start_date',
            'location' => 'nullable|string|max:255',
            'instructor' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        // Eğitim bu firmaya ait mi?
        $training = Training::where('company_id', $this->getCompanyId())
            ->findOrFail($validated['training_id']);

        $session = TrainingSession::create([
            ...$validated,
            'status' => 'scheduled',
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('create', $session, 'Eğitim oturumu oluşturuldu: '.$training->title);

        return $this->success($session->load('training'), 'Oturum oluşturuldu', 201);
    }

    /**
     * Oturum detayı
     */
    public function show(int $id): JsonResponse
    {
        $session = TrainingSession::whereHas('training', function ($q) {
            $q->where('company_id', $this->getCompanyId());
        })
            ->with(['training', 'participants.user:id,name,email'])
            ->findOrFail($id);

        return $this->success($session);
    }

    /**
     * Oturum güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $session = TrainingSession::whereHas('training', function ($q) {
            $q->where('company_id', $this->getCompanyId());
        })->findOrFail($id);

        $validated = $request->validate([
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'location' => 'nullable|string|max:255',
            'instructor' => 'nullable|string|max:255',
            'status' => 'sometimes|nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $this->lookups->assertValid(
            LookupService::TYPE_TRAINING_SESSION_STATUS,
            $validated['status'] ?? null,
            $this->getCompanyId(),
            'status'
        );

        $oldValues = $session->getOriginal();
        $session->update($validated);

        ActivityLog::log('update', $session, 'Eğitim oturumu güncellendi', $oldValues, $session->fresh()->toArray());

        return $this->success($session, 'Oturum güncellendi');
    }

    /**
     * Oturum sil
     */
    public function destroy(int $id): JsonResponse
    {
        $session = TrainingSession::whereHas('training', function ($q) {
            $q->where('company_id', $this->getCompanyId());
        })->findOrFail($id);

        if ($session->participants()->exists()) {
            return $this->error('Bu oturuma kayıtlı katılımcılar var, silinemez.', 422);
        }

        ActivityLog::log('delete', null, 'Eğitim oturumu silindi');

        $session->delete();

        return $this->success(null, 'Oturum silindi');
    }

    /**
     * Katılımcı ekle
     */
    public function addParticipant(Request $request, int $id): JsonResponse
    {
        $session = TrainingSession::whereHas('training', function ($q) {
            $q->where('company_id', $this->getCompanyId());
        })->findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        // Zaten kayıtlı mı?
        if ($session->participants()->where('user_id', $validated['user_id'])->exists()) {
            return $this->error('Bu kullanıcı zaten kayıtlı.', 422);
        }

        // Kontenjan var mı?
        if (! $session->hasAvailableSlots()) {
            return $this->error('Bu oturumda boş yer yok.', 422);
        }

        $session->participants()->create([
            'user_id' => $validated['user_id'],
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        $user = User::find($validated['user_id']);
        ActivityLog::log('update', $session, "Katılımcı eklendi: {$user->name}");

        return $this->success($session->load('participants.user'), 'Katılımcı eklendi');
    }

    /**
     * Katılımcı çıkar
     */
    public function removeParticipant(int $id, int $userId): JsonResponse
    {
        $session = TrainingSession::whereHas('training', function ($q) {
            $q->where('company_id', $this->getCompanyId());
        })->findOrFail($id);

        $participant = $session->participants()->where('user_id', $userId)->firstOrFail();
        $user = User::find($userId);
        $participant->delete();

        ActivityLog::log('update', $session, "Katılımcı çıkarıldı: {$user->name}");

        return $this->success(null, 'Katılımcı çıkarıldı');
    }

    /**
     * Katılım durumu güncelle
     */
    public function updateAttendance(Request $request, int $id): JsonResponse
    {
        $session = TrainingSession::whereHas('training', function ($q) {
            $q->where('company_id', $this->getCompanyId());
        })->findOrFail($id);

        $validated = $request->validate([
            'attendances' => 'required|array',
            'attendances.*.user_id' => 'required|exists:users,id',
            'attendances.*.status' => 'required|in:registered,attended,absent,excused',
            'attendances.*.score' => 'nullable|integer|min:0|max:100',
            'attendances.*.passed' => 'nullable|boolean',
        ]);

        foreach ($validated['attendances'] as $attendance) {
            $session->participants()
                ->where('user_id', $attendance['user_id'])
                ->update([
                    'status' => $attendance['status'],
                    'score' => $attendance['score'] ?? null,
                    'passed' => $attendance['passed'] ?? null,
                    'completed_at' => $attendance['status'] === 'attended' ? now() : null,
                ]);
        }

        ActivityLog::log('update', $session, 'Katılım güncellendi');

        return $this->success($session->load('participants'), 'Katılım durumları güncellendi');
    }
}
