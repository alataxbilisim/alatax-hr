<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Payslip;
use App\Services\DataScopeService;
use App\Services\Payroll\PayslipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Company bordro yükleme / yayınlama (C5).
 */
class PayslipController extends BaseController
{
    public function __construct(
        protected PayslipService $payslips,
        protected DataScopeService $dataScope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Payslip::query()
            ->where('company_id', $this->getCompanyId())
            ->with(['employee:id,user_id,employee_code,department_id,branch_id', 'employee.user:id,name'])
            ->orderByDesc('period')
            ->orderByDesc('id');

        $user = $request->user();
        if ($user) {
            $employeeQuery = Employee::query()->where('company_id', $this->getCompanyId());
            $this->dataScope->scopeForEmployee($employeeQuery, $user);
            $allowedEmployeeIds = $employeeQuery->pluck('id');
            $query->whereIn('employee_id', $allowedEmployeeIds);
        }

        if ($request->filled('period')) {
            $query->where('period', $request->string('period')->toString());
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }
        if ($request->filled('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        $page = $query->paginate($request->integer('per_page', 20));
        $page->getCollection()->transform(function (Payslip $p) {
            return $this->serialize($p);
        });

        return $this->paginated($page, 'Bordrolar listelendi');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $payslip = $this->findScoped($request, $id);
        ActivityLog::log('view_sensitive', $payslip, 'bordro HR görüntülendi');

        return $this->success($this->serialize($payslip, true));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'gross_salary' => 'nullable|numeric|min:0',
            'net_salary' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
            'publish' => 'sometimes|boolean',
            'file' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $employee = Employee::query()
            ->where('company_id', $this->getCompanyId())
            ->whereKey((int) $validated['employee_id'])
            ->firstOrFail();

        if ($employee->user_id && ! $this->dataScope->allowsUserId($request->user(), (int) $employee->user_id)) {
            return $this->error('Bu personele erişim yetkiniz yok', 403);
        }

        try {
            $payslip = $this->payslips->createOrReplace(
                (int) $this->getCompanyId(),
                $validated,
                $request->file('file'),
                auth()->id()
            );
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        ActivityLog::log('create', $payslip, 'Bordro yüklendi: '.$payslip->period);

        return $this->success($this->serialize($payslip, true), 'Bordro kaydedildi', 201);
    }

    public function publish(Request $request, int $id): JsonResponse
    {
        $payslip = $this->findScoped($request, $id);
        $payslip = $this->payslips->publish($payslip, auth()->id());
        ActivityLog::log('publish', $payslip, 'Bordro yayınlandı: '.$payslip->period);

        return $this->success($this->serialize($payslip, true), 'Bordro yayınlandı');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $payslip = $this->findScoped($request, $id);

        if ($payslip->file_path && Storage::disk('private')->exists($payslip->file_path)) {
            Storage::disk('private')->delete($payslip->file_path);
        }

        ActivityLog::log('delete', $payslip, 'Bordro silindi: '.$payslip->period);
        $payslip->delete();

        return $this->success(null, 'Bordro silindi');
    }

    public function download(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $payslip = $this->findScoped($request, $id);

        if (! $payslip->file_path) {
            return $this->error('Bordro dosyası yok', 404);
        }

        $disk = Storage::disk('private')->exists($payslip->file_path)
            ? 'private'
            : (Storage::disk('public')->exists($payslip->file_path) ? 'public' : null);

        if ($disk === null) {
            return $this->error('Bordro dosyası bulunamadı', 404);
        }

        ActivityLog::log('export', $payslip, 'bordro HR indirildi');

        return Storage::disk($disk)->download(
            $payslip->file_path,
            "Bordro_{$payslip->year}_{$payslip->month}.pdf"
        );
    }

    private function findScoped(Request $request, int $id): Payslip
    {
        $payslip = Payslip::query()
            ->where('company_id', $this->getCompanyId())
            ->with(['employee.user:id,name'])
            ->findOrFail($id);

        $userId = $payslip->employee?->user_id;
        if ($userId && ! $this->dataScope->allowsUserId($request->user(), (int) $userId)) {
            abort(403, 'Bu bordroya erişim yetkiniz yok');
        }

        return $payslip;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Payslip $p, bool $detail = false): array
    {
        $base = [
            'id' => $p->id,
            'employee_id' => $p->employee_id,
            'employee_name' => $p->employee?->user?->name,
            'employee_code' => $p->employee?->employee_code,
            'period' => $p->period,
            'period_label' => $p->period_label,
            'year' => $p->year,
            'month' => $p->month,
            'is_published' => $p->is_published,
            'published_at' => $p->published_at,
            'has_file' => ! empty($p->file_path),
            'net_salary' => $p->net_salary,
            'gross_salary' => $p->gross_salary,
        ];

        if ($detail) {
            $base['notes'] = $p->notes;
            $base['deductions'] = $p->deductions;
            $base['bonuses'] = $p->bonuses;
        }

        return $base;
    }
}
