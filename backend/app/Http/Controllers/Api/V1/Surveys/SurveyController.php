<?php

namespace App\Http\Controllers\Api\V1\Surveys;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySubmission;
use App\Services\LookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SurveyController extends BaseController
{
    public function __construct(
        protected LookupService $lookups,
    ) {}
    /**
     * Anket listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = Survey::where('company_id', $this->getCompanyId())
            ->withCount(['questions', 'submissions']);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $surveys = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return $this->paginated($surveys, 'Anketler listelendi');
    }

    /**
     * Anket detayı
     */
    public function show(int $id): JsonResponse
    {
        $survey = Survey::where('company_id', $this->getCompanyId())
            ->with('questions')
            ->withCount('submissions')
            ->findOrFail($id);

        return $this->success($survey, 'Anket detayı');
    }

    /**
     * Yeni anket oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|max:100',
            'is_anonymous' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'recurrence' => 'nullable|in:none,weekly,monthly,quarterly,yearly',
            'audience' => 'nullable|in:all,department,position,custom',
            'audience_filter' => 'nullable|array',
            'questions' => 'required|array|min:1',
            'questions.*.question_text' => 'required|string',
            'questions.*.question_type' => 'required|string|max:100',
            'questions.*.options' => 'nullable|array',
            'questions.*.min_value' => 'nullable|integer',
            'questions.*.max_value' => 'nullable|integer',
            'questions.*.is_required' => 'boolean',
            'questions.*.category' => 'nullable|string',
        ]);

        $companyId = $this->getCompanyId();
        $this->lookups->assertValid(
            LookupService::TYPE_SURVEY_TYPE,
            $validated['type'],
            $companyId,
            'type'
        );
        foreach ($validated['questions'] as $index => $questionData) {
            $this->lookups->assertValid(
                LookupService::TYPE_SURVEY_QUESTION_TYPE,
                $questionData['question_type'] ?? null,
                $companyId,
                "questions.{$index}.question_type"
            );
        }

        DB::beginTransaction();
        try {
            $survey = Survey::create([
                'company_id' => $companyId,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'is_anonymous' => $validated['is_anonymous'] ?? true,
                'is_active' => true,
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'recurrence' => $validated['recurrence'] ?? 'none',
                'audience' => $validated['audience'] ?? 'all',
                'audience_filter' => $validated['audience_filter'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Soruları oluştur
            foreach ($validated['questions'] as $index => $questionData) {
                SurveyQuestion::create([
                    'survey_id' => $survey->id,
                    'order_number' => $index + 1,
                    'question_text' => $questionData['question_text'],
                    'question_type' => $questionData['question_type'],
                    'options' => $questionData['options'] ?? null,
                    'min_value' => $questionData['min_value'] ?? null,
                    'max_value' => $questionData['max_value'] ?? null,
                    'is_required' => $questionData['is_required'] ?? true,
                    'category' => $questionData['category'] ?? null,
                ]);
            }

            DB::commit();

            ActivityLog::log('create', $survey, 'Yeni anket oluşturuldu: '.$survey->title);

            return $this->created($survey->load('questions'), 'Anket oluşturuldu');

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Anket oluşturulamadı: '.$e->getMessage(), 500);
        }
    }

    /**
     * Anket güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $survey = Survey::where('company_id', $this->getCompanyId())->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $oldValues = $survey->toArray();
        $survey->update(array_merge($validated, ['updated_by' => auth()->id()]));

        ActivityLog::log('update', $survey, 'Anket güncellendi', $oldValues, $survey->toArray());

        return $this->success($survey, 'Anket güncellendi');
    }

    /**
     * Anket sil
     */
    public function destroy(int $id): JsonResponse
    {
        $survey = Survey::where('company_id', $this->getCompanyId())->findOrFail($id);

        $survey->delete();

        ActivityLog::log('delete', $survey, 'Anket silindi: '.$survey->title);

        return $this->success(null, 'Anket silindi');
    }

    /**
     * Anket yanıtla
     */
    public function submit(Request $request, int $surveyId): JsonResponse
    {
        $survey = Survey::where('company_id', $this->getCompanyId())
            ->with('questions')
            ->findOrFail($surveyId);

        if (! $survey->isOpen()) {
            return $this->error('Bu anket artık yanıtlanamaz', 400);
        }

        $validated = $request->validate([
            'responses' => 'required|array',
            'responses.*.question_id' => 'required|exists:survey_questions,id',
            'responses.*.answer_text' => 'nullable|string',
            'responses.*.answer_numeric' => 'nullable|integer',
            'responses.*.answer_array' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $submission = SurveySubmission::create([
                'survey_id' => $survey->id,
                'user_id' => $survey->is_anonymous ? null : auth()->id(),
                'anonymous_id' => $survey->is_anonymous ? Str::uuid() : null,
                'status' => 'completed',
                'started_at' => now(),
                'completed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            foreach ($validated['responses'] as $response) {
                SurveyResponse::create([
                    'survey_submission_id' => $submission->id,
                    'survey_question_id' => $response['question_id'],
                    'answer_text' => $response['answer_text'] ?? null,
                    'answer_numeric' => $response['answer_numeric'] ?? null,
                    'answer_array' => $response['answer_array'] ?? null,
                ]);
            }

            DB::commit();

            ActivityLog::log('create', $submission, 'Anket yanıtlandı');

            return $this->success(['submission_id' => $submission->id], 'Anket yanıtınız kaydedildi');

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Yanıt kaydedilemedi: '.$e->getMessage(), 500);
        }
    }

    /**
     * Anket sonuçları
     */
    public function results(int $id): JsonResponse
    {
        $survey = Survey::where('company_id', $this->getCompanyId())
            ->with(['questions.responses'])
            ->withCount(['submissions' => fn ($q) => $q->where('status', 'completed')])
            ->findOrFail($id);

        $results = [];
        foreach ($survey->questions as $question) {
            $questionResult = [
                'question' => $question,
                'total_responses' => $question->responses->count(),
            ];

            switch ($question->question_type) {
                case 'rating':
                case 'nps':
                case 'scale':
                    $questionResult['average'] = $question->getAverageScore();
                    $questionResult['distribution'] = $question->responses
                        ->groupBy('answer_numeric')
                        ->map(fn ($group) => $group->count());
                    break;

                case 'single_choice':
                case 'multiple_choice':
                    $questionResult['distribution'] = $question->responses
                        ->groupBy('answer_text')
                        ->map(fn ($group) => $group->count());
                    break;

                case 'text':
                    $questionResult['responses'] = $question->responses
                        ->pluck('answer_text')
                        ->filter();
                    break;
            }

            $results[] = $questionResult;
        }

        return $this->success([
            'survey' => $survey,
            'total_submissions' => $survey->submissions_count,
            'completion_rate' => $survey->getCompletionRate(),
            'results' => $results,
        ], 'Anket sonuçları');
    }

    /**
     * Anket tiplerini getir
     */
    public function getTypes(): JsonResponse
    {
        return $this->success([
            'survey_types' => Survey::getTypeLabels(),
            'question_types' => SurveyQuestion::getTypeLabels(),
        ], 'Anket tipleri');
    }
}
