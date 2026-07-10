<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\TrainingCertificate;
use App\Models\TrainingParticipant;
use App\Models\TrainingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalTrainingController extends BaseController
{
    /**
     * Çalışanın kayıtlı olduğu eğitimleri listele
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = TrainingParticipant::where('user_id', $user->id)
            ->with([
                'session.training' => function ($q) use ($user) {
                    $q->where('company_id', $user->company_id);
                },
                'certificate',
            ])
            ->whereHas('session.training', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });

        // Durum filtresi
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Tamamlanma durumu
        if ($request->boolean('completed_only')) {
            $query->whereNotNull('completed_at');
        }

        // Yaklaşan eğitimler
        if ($request->boolean('upcoming_only')) {
            $query->whereHas('session', function ($q) {
                $q->where('start_date', '>', now())
                    ->where('status', 'scheduled');
            });
        }

        $participants = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        // Response'u formatla
        $data = $participants->getCollection()->map(function ($participant) {
            $training = $participant->session->training ?? null;
            if (! $training) {
                return null;
            }

            return [
                'id' => $participant->id,
                'training' => [
                    'id' => $training->id,
                    'title' => $training->title,
                    'description' => $training->description,
                    'category' => $training->category,
                    'type' => $training->type,
                    'duration_hours' => $training->duration_hours,
                    'is_mandatory' => $training->is_mandatory,
                ],
                'session' => [
                    'id' => $participant->session->id,
                    'start_date' => $participant->session->start_date?->format('Y-m-d H:i'),
                    'end_date' => $participant->session->end_date?->format('Y-m-d H:i'),
                    'location' => $participant->session->location,
                    'status' => $participant->session->status,
                ],
                'status' => $participant->status,
                'score' => $participant->score,
                'passed' => $participant->passed,
                'registered_at' => $participant->registered_at?->format('Y-m-d H:i'),
                'completed_at' => $participant->completed_at?->format('Y-m-d H:i'),
                'has_certificate' => $participant->certificate !== null,
                'certificate_id' => $participant->certificate?->id,
            ];
        })->filter();

        return $this->paginated($data, 'Eğitimler listelendi', $participants);
    }

    /**
     * Eğitim detayı (çalışanın kayıtlı olduğu eğitim)
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $participant = TrainingParticipant::where('user_id', $user->id)
            ->where('id', $id)
            ->with([
                'session.training' => function ($q) use ($user) {
                    $q->where('company_id', $user->company_id);
                },
                'certificate',
            ])
            ->whereHas('session.training', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            })
            ->first();

        if (! $participant || ! $participant->session->training) {
            return $this->error('Eğitim bulunamadı', null, 404);
        }

        $training = $participant->session->training;
        $session = $participant->session;

        return $this->success([
            'id' => $participant->id,
            'training' => [
                'id' => $training->id,
                'title' => $training->title,
                'description' => $training->description,
                'category' => $training->category,
                'type' => $training->type,
                'instructor' => $training->instructor,
                'duration_hours' => $training->duration_hours,
                'is_mandatory' => $training->is_mandatory,
            ],
            'session' => [
                'id' => $session->id,
                'start_date' => $session->start_date?->format('Y-m-d H:i'),
                'end_date' => $session->end_date?->format('Y-m-d H:i'),
                'location' => $session->location,
                'instructor' => $session->instructor,
                'status' => $session->status,
                'notes' => $session->notes,
            ],
            'participant' => [
                'status' => $participant->status,
                'score' => $participant->score,
                'passed' => $participant->passed,
                'feedback' => $participant->feedback,
                'registered_at' => $participant->registered_at?->format('Y-m-d H:i'),
                'completed_at' => $participant->completed_at?->format('Y-m-d H:i'),
            ],
            'certificate' => $participant->certificate ? [
                'id' => $participant->certificate->id,
                'issue_date' => $participant->certificate->issue_date?->format('Y-m-d'),
                'expiry_date' => $participant->certificate->expiry_date?->format('Y-m-d'),
                'certificate_number' => $participant->certificate->certificate_number,
            ] : null,
        ]);
    }

    /**
     * Mevcut eğitimler listesi (kayıt olunabilir)
     */
    public function available(Request $request): JsonResponse
    {
        $user = $request->user();

        // Çalışanın zaten kayıtlı olduğu oturum ID'lerini al
        $registeredSessionIds = TrainingParticipant::where('user_id', $user->id)
            ->pluck('session_id');

        $query = TrainingSession::whereHas('training', function ($q) use ($user) {
            $q->where('company_id', $user->company_id)
                ->where('is_active', true);
        })
            ->whereNotIn('id', $registeredSessionIds)
            ->where('status', 'scheduled')
            ->where('start_date', '>', now())
            ->with('training:id,title,description,category,type,duration_hours,is_mandatory,max_participants');

        $sessions = $query->orderBy('start_date')
            ->paginate($request->get('per_page', 15));

        return $this->paginated($sessions, 'Mevcut eğitimler listelendi');
    }

    /**
     * Çalışanın sertifikaları
     */
    public function certificates(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = TrainingCertificate::whereHas('participant', function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->whereHas('session.training', function ($q2) use ($user) {
                    $q2->where('company_id', $user->company_id);
                });
        })
            ->with([
            'participant.session.training' => function ($q) use ($user) {
                $q->where('company_id', $user->company_id)
                    ->select('id', 'title', 'category');
            },
        ]);

        $certificates = $query->orderByDesc('issue_date')
            ->paginate($request->get('per_page', 15));

        return $this->paginated($certificates, 'Sertifikalar listelendi');
    }

    /**
     * Sertifika detayı
     */
    public function certificate(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $certificate = TrainingCertificate::where('id', $id)
            ->whereHas('participant', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->whereHas('session.training', function ($q2) use ($user) {
                        $q2->where('company_id', $user->company_id);
                    });
            })
            ->with([
                'participant.session.training' => function ($q) use ($user) {
                    $q->where('company_id', $user->company_id);
                },
            ])
            ->first();

        if (! $certificate) {
            return $this->error('Sertifika bulunamadı', null, 404);
        }

        return $this->success([
            'id' => $certificate->id,
            'certificate_number' => $certificate->certificate_number,
            'issue_date' => $certificate->issue_date?->format('Y-m-d'),
            'expiry_date' => $certificate->expiry_date?->format('Y-m-d'),
            'training' => [
                'id' => $certificate->participant->session->training->id,
                'title' => $certificate->participant->session->training->title,
                'category' => $certificate->participant->session->training->category,
            ],
            'score' => $certificate->participant->score,
            'passed' => $certificate->participant->passed,
        ]);
    }
}
