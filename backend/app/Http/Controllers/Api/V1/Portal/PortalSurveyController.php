<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\SurveySubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalSurveyController extends BaseController
{
    /**
     * Çalışana atanan aktif anketleri listele
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Survey::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->open()
            ->with(['questions:id,survey_id,question_text,question_type,order_number'])
            ->withCount(['submissions' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }]);

        // Sadece tamamlanmamış anketler
        if ($request->boolean('pending_only')) {
            $query->whereDoesntHave('submissions', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->where('status', 'completed');
            });
        }

        // Tamamlanan anketler
        if ($request->boolean('completed_only')) {
            $query->whereHas('submissions', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->where('status', 'completed');
            });
        }

        $surveys = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        // Her anket için kullanıcının durumunu ekle
        $data = $surveys->getCollection()->map(function ($survey) use ($user) {
            $submission = SurveySubmission::where('survey_id', $survey->id)
                ->where('user_id', $user->id)
                ->first();

            return [
                'id' => $survey->id,
                'title' => $survey->title,
                'description' => $survey->description,
                'type' => $survey->type,
                'is_anonymous' => $survey->is_anonymous,
                'start_date' => $survey->start_date?->format('Y-m-d H:i'),
                'end_date' => $survey->end_date?->format('Y-m-d H:i'),
                'questions_count' => $survey->questions->count(),
                'submission_status' => $submission ? $submission->status : 'not_started',
                'started_at' => $submission?->started_at?->format('Y-m-d H:i'),
                'completed_at' => $submission?->completed_at?->format('Y-m-d H:i'),
                'is_completed' => $submission?->status === 'completed',
            ];
        });

        return $this->paginated($data, 'Anketler listelendi', $surveys);
    }

    /**
     * Anket detayı ve soruları
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $survey = Survey::where('company_id', $user->company_id)
            ->where('id', $id)
            ->where('is_active', true)
            ->with(['questions' => function ($q) {
                $q->orderBy('order_number');
            }])
            ->first();

        if (! $survey) {
            return $this->error('Anket bulunamadı', null, 404);
        }

        if (! $survey->isOpen()) {
            return $this->error('Anket henüz başlamadı veya sona erdi', null, 422);
        }

        // Kullanıcının mevcut submission'ını kontrol et
        $submission = SurveySubmission::where('survey_id', $survey->id)
            ->where('user_id', $user->id)
            ->with('responses.question')
            ->first();

        // Eğer tamamlanmışsa tekrar yanıt verilemez
        if ($submission && $submission->status === 'completed') {
            return $this->success([
                'survey' => [
                    'id' => $survey->id,
                    'title' => $survey->title,
                    'description' => $survey->description,
                    'type' => $survey->type,
                    'is_anonymous' => $survey->is_anonymous,
                ],
                'submission' => [
                    'id' => $submission->id,
                    'status' => $submission->status,
                    'completed_at' => $submission->completed_at?->format('Y-m-d H:i'),
                ],
                'is_completed' => true,
                'questions' => null, // Tamamlanmış anketlerde sorular gösterilmez
            ], 'Anket zaten tamamlanmış');
        }

        return $this->success([
            'survey' => [
                'id' => $survey->id,
                'title' => $survey->title,
                'description' => $survey->description,
                'type' => $survey->type,
                'is_anonymous' => $survey->is_anonymous,
                'start_date' => $survey->start_date?->format('Y-m-d H:i'),
                'end_date' => $survey->end_date?->format('Y-m-d H:i'),
            ],
            'questions' => $survey->questions->map(function ($question) {
                return [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type,
                    'options' => $question->options,
                    'is_required' => $question->is_required,
                    'order_number' => $question->order_number,
                ];
            }),
            'submission' => $submission ? [
                'id' => $submission->id,
                'status' => $submission->status,
                'started_at' => $submission->started_at?->format('Y-m-d H:i'),
            ] : null,
            'is_completed' => false,
        ]);
    }

    /**
     * Anket yanıtını başlat/başlatılmış anketi getir
     */
    public function start(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $survey = Survey::where('company_id', $user->company_id)
            ->where('id', $id)
            ->where('is_active', true)
            ->first();

        if (! $survey || ! $survey->isOpen()) {
            return $this->error('Anket bulunamadı veya açık değil', null, 404);
        }

        // Zaten bir submission var mı kontrol et
        $submission = SurveySubmission::where('survey_id', $survey->id)
            ->where('user_id', $user->id)
            ->first();

        if ($submission) {
            if ($submission->status === 'completed') {
                return $this->error('Anket zaten tamamlanmış', null, 422);
            }

            // Devam eden submission varsa onu döndür
            return $this->success($submission->load('responses.question'), 'Devam eden anket');
        }

        // Yeni submission oluştur
        $submission = SurveySubmission::create([
            'survey_id' => $survey->id,
            'user_id' => $survey->is_anonymous ? null : $user->id,
            'anonymous_id' => $survey->is_anonymous ? uniqid('anon_', true) : null,
            'status' => SurveySubmission::STATUS_STARTED,
            'started_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        \App\Models\ActivityLog::log('create', $submission, 'Anket başlatıldı');

        return $this->created($submission->load('responses.question'), 'Anket başlatıldı');
    }

    /**
     * Anket yanıtını gönder
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'submission_id' => 'required|exists:survey_submissions,id',
            'responses' => 'required|array',
            'responses.*.question_id' => 'required|exists:survey_questions,id',
            'responses.*.answer_text' => 'nullable|string|max:5000',
            'responses.*.answer_numeric' => 'nullable|numeric',
            'responses.*.answer_array' => 'nullable|array',
        ]);

        $submission = SurveySubmission::where('id', $validated['submission_id'])
            ->where(function ($q) use ($user, $request) {
                $q->where('user_id', $user->id)
                    ->orWhere('anonymous_id', $request->input('anonymous_id'));
            })
            ->where('survey_id', $id)
            ->where('status', '!=', 'completed')
            ->first();

        if (! $submission) {
            return $this->error('Yanıt bulunamadı veya gönderilemez', null, 404);
        }

        $survey = Survey::where('id', $id)
            ->with('questions')
            ->first();

        if (! $survey || ! $survey->isOpen()) {
            return $this->error('Anket bulunamadı veya açık değil', null, 404);
        }

        // Zorunlu sorular kontrolü
        $requiredQuestions = $survey->questions->where('is_required', true)->pluck('id');
        $answeredQuestionIds = collect($validated['responses'])->pluck('question_id');

        $missingQuestions = $requiredQuestions->diff($answeredQuestionIds);
        if ($missingQuestions->isNotEmpty()) {
            return $this->error('Zorunlu soruları yanıtlamanız gerekiyor', [
                'missing_questions' => $missingQuestions->toArray(),
            ], 422);
        }

        return DB::transaction(function () use ($validated, $submission) {
            // Mevcut yanıtları sil
            SurveyResponse::where('survey_submission_id', $submission->id)->delete();

            // Yeni yanıtları kaydet
            foreach ($validated['responses'] as $responseData) {
                SurveyResponse::create([
                    'survey_submission_id' => $submission->id,
                    'survey_question_id' => $responseData['question_id'],
                    'answer_text' => $responseData['answer_text'] ?? null,
                    'answer_numeric' => $responseData['answer_numeric'] ?? null,
                    'answer_array' => $responseData['answer_array'] ?? null,
                ]);
            }

            // Submission'ı tamamla
            $submission->update([
                'status' => SurveySubmission::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            \App\Models\ActivityLog::log('update', $submission, 'Anket tamamlandı');

            return $this->success($submission->load('responses.question'), 'Anket başarıyla tamamlandı');
        });
    }

    /**
     * Çalışanın tamamladığı anketleri listele
     */
    public function completed(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = SurveySubmission::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereHas('survey', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            })
            ->with('survey:id,title,description,type')
            ->orderByDesc('completed_at');

        $submissions = $query->paginate($request->get('per_page', 15));

        return $this->paginated($submissions, 'Tamamlanan anketler listelendi');
    }
}
