<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PortalDocumentController extends BaseController
{
    /**
     * Belgelerimi listele
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return $this->error('Personel kaydı bulunamadı', 404);
        }

        $query = EmployeeDocument::where('employee_id', $employee->id)
            ->visibleToEmployee()
            ->active()
            ->orderByDesc('created_at');

        if ($request->has('category')) {
            $query->ofCategory($request->category);
        }

        $documents = $query->paginate($request->get('per_page', 15));

        return $this->paginated($documents);
    }

    /**
     * Belge detayı
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return $this->error('Personel kaydı bulunamadı', 404);
        }

        $document = EmployeeDocument::where('employee_id', $employee->id)
            ->where('id', $id)
            ->with('employee')
            ->first();

        if (! $document) {
            return $this->error('Belge bulunamadı', 404);
        }

        $this->authorize('view', $document);

        return $this->success($document);
    }

    /**
     * Belge indir
     */
    public function download(Request $request, int $id): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return $this->error('Personel kaydı bulunamadı', 404);
        }

        $document = EmployeeDocument::where('employee_id', $employee->id)
            ->where('id', $id)
            ->with('employee')
            ->first();

        if (! $document) {
            return $this->error('Belge bulunamadı', 404);
        }

        $this->authorize('view', $document);

        // HR private disk'e yazar — public ile uyumsuzluk düzeltildi
        if (! Storage::disk('private')->exists($document->file_path)) {
            return $this->error('Dosya bulunamadı', 404);
        }

        return Storage::disk('private')->download($document->file_path, $document->file_name);
    }

    /**
     * Kategori listesi
     */
    public function categories(): JsonResponse
    {
        $categories = [
            ['value' => 'id_card', 'label' => 'Kimlik'],
            ['value' => 'contract', 'label' => 'Sözleşme'],
            ['value' => 'certificate', 'label' => 'Sertifika'],
            ['value' => 'education', 'label' => 'Eğitim'],
            ['value' => 'health', 'label' => 'Sağlık'],
            ['value' => 'other', 'label' => 'Diğer'],
        ];

        return $this->success($categories);
    }
}
