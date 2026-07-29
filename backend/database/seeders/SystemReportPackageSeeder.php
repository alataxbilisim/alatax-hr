<?php

namespace Database\Seeders;

use App\Models\Dashboard;
use App\Models\RoleDefaultDashboard;
use App\Models\SavedReport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * D1g — Hazır sistem rapor/pano paketi.
 *
 * Saklama: company_id NULL + is_system + system_key (global şablon).
 * Firma kopyaları system_key=null; seed onları ASLA ezmez.
 * Idempotent: updateOrCreate system_key üzerinde.
 */
class SystemReportPackageSeeder extends Seeder
{
    public function run(): void
    {
        $reportIds = [];

        foreach ($this->reportDefinitions() as $def) {
            $report = SavedReport::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => null,
                    'system_key' => $def['system_key'],
                    'is_system' => true,
                ],
                [
                    'user_id' => null,
                    'name' => $def['name'],
                    'description' => $def['description'] ?? null,
                    'dataset_key' => $def['dataset_key'],
                    'module_key' => $def['module_key'],
                    'config' => $def['config'],
                    'is_shared' => false,
                    'is_favorite' => false,
                    'sort_order' => $def['sort_order'] ?? 0,
                ]
            );
            $reportIds[$def['system_key']] = (int) $report->id;
        }

        foreach ($this->dashboardDefinitions($reportIds) as $def) {
            Dashboard::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => null,
                    'system_key' => $def['system_key'],
                    'is_system' => true,
                ],
                [
                    'owner_id' => null,
                    'created_by' => null,
                    'name' => $def['name'],
                    'description' => $def['description'] ?? null,
                    'module_key' => $def['module_key'],
                    'layout' => $def['layout'],
                    'global_filters' => ['fields' => []],
                ]
            );
        }

        foreach ([
            'company_admin' => 'hr-analytics.overview',
            'admin' => 'hr-analytics.overview',
            'hr' => 'hr-analytics.overview',
            'manager' => 'leave-management.overview',
        ] as $roleKey => $dashKey) {
            RoleDefaultDashboard::query()->updateOrCreate(
                ['role_key' => $roleKey],
                ['dashboard_system_key' => $dashKey]
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reportDefinitions(): array
    {
        $defs = [];

        // Personel (module_key: leave-management — ayrı personel slug yok; İK panosunda da görünür)
        $defs[] = $this->aggReport('employees.by_department', 'leave-management', 'employees', 'Departman dağılımı',
            ['department_id'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->fieldsReport('employees.headcount', 'leave-management', 'employees', 'Personel listesi (özet)',
            ['employee_code', 'status', 'department_id', 'hire_date']);
        $defs[] = $this->aggReport('employees.by_status', 'leave-management', 'employees', 'Durum dağılımı',
            ['status'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->aggReport('employees.by_gender', 'leave-management', 'employees', 'Cinsiyet dağılımı',
            ['gender'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->fieldsReport('employees.hire_dates', 'leave-management', 'employees', 'İşe giriş listesi',
            ['employee_code', 'hire_date', 'department_id', 'status']);

        // İzin
        $defs[] = $this->aggReport('leaves.by_status', 'leave-management', 'leave_requests', 'İzin durum dağılımı',
            ['status'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->aggReport('leaves.by_type', 'leave-management', 'leave_requests', 'İzin türü kullanımı',
            ['leave_type_name'], [['field' => 'total_days', 'fn' => 'sum', 'alias' => 'days']]);
        $defs[] = $this->fieldsReport('leaves.pending', 'leave-management', 'leave_requests', 'Onay bekleyen izinler',
            ['user_id', 'leave_type_name', 'status', 'start_date', 'end_date', 'total_days'],
            [['field' => 'status', 'op' => 'eq', 'value' => 'pending']]);
        $defs[] = $this->fieldsReport('leaves.balances', 'leave-management', 'leave_balances', 'İzin bakiye durumu',
            ['user_id', 'leave_type_id', 'year', 'total_days', 'used_days', 'pending_days', 'carried_over']);
        $defs[] = $this->aggReport('leaves.count_by_type', 'leave-management', 'leave_requests', 'İzin adet / tür',
            ['leave_type_name'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);

        // İşe alım
        $defs[] = $this->aggReport('recruitment.by_status', 'job-applications', 'job_applications', 'Başvuru durumu',
            ['status'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->aggReport('recruitment.by_source', 'job-applications', 'job_applications', 'Kaynak etkinliği',
            ['source'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->aggReport('recruitment.by_position', 'job-applications', 'job_applications', 'Pozisyon hunisi',
            ['job_position_id'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->fieldsReport('recruitment.recent', 'job-applications', 'job_applications', 'Son başvurular',
            ['first_name', 'last_name', 'status', 'source', 'created_at']);
        $defs[] = $this->aggReport('recruitment.by_assignee', 'job-applications', 'job_applications', 'Atanan dağılımı',
            ['assigned_to'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);

        // Masraf
        $defs[] = $this->aggReport('expenses.by_status', 'expense-management', 'expense_claims', 'Masraf durumu',
            ['status'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->aggReport('expenses.by_amount', 'expense-management', 'expense_claims', 'Masraf tutar toplamı',
            ['status'], [['field' => 'total_amount', 'fn' => 'sum', 'alias' => 'amount']]);
        $defs[] = $this->aggReport('expenses.by_currency', 'expense-management', 'expense_claims', 'Para birimi dağılımı',
            ['currency'], [['field' => 'total_amount', 'fn' => 'sum', 'alias' => 'amount']]);
        $defs[] = $this->aggReport('expenses.by_user', 'expense-management', 'expense_claims', 'Kişi bazlı masraf',
            ['user_id'], [['field' => 'total_amount', 'fn' => 'sum', 'alias' => 'amount']]);
        $defs[] = $this->fieldsReport('expenses.pending', 'expense-management', 'expense_claims', 'Onay bekleyen masraflar',
            ['claim_number', 'title', 'status', 'total_amount', 'expense_date'],
            [['field' => 'status', 'op' => 'eq', 'value' => 'pending']]);

        // Puantaj
        $defs[] = $this->aggReport('timesheet.by_status', 'timesheet', 'attendance_records', 'Devam durumu',
            ['status'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->aggReport('timesheet.late', 'timesheet', 'attendance_records', 'Geç giriş sıklığı',
            ['user_id'], [['field' => 'late_minutes', 'fn' => 'sum', 'alias' => 'late_sum']]);
        $defs[] = $this->aggReport('timesheet.overtime', 'timesheet', 'attendance_records', 'Fazla mesai dağılımı',
            ['user_id'], [['field' => 'overtime_hours', 'fn' => 'sum', 'alias' => 'ot']]);
        $defs[] = $this->aggReport('timesheet.early', 'timesheet', 'attendance_records', 'Erken çıkış',
            ['user_id'], [['field' => 'early_leave_minutes', 'fn' => 'sum', 'alias' => 'early_sum']]);
        $defs[] = $this->aggReport('timesheet.hours', 'timesheet', 'attendance_records', 'Toplam saat / kullanıcı',
            ['user_id'], [['field' => 'total_hours', 'fn' => 'sum', 'alias' => 'hours']]);
        $defs[] = $this->aggReport('timesheet.by_branch', 'timesheet', 'attendance_records', 'Şube devam',
            ['branch_id'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);

        // Varlık
        $defs[] = $this->aggReport('assets.by_status', 'asset-management', 'assets', 'Varlık durumu',
            ['status'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->aggReport('assets.by_category', 'asset-management', 'assets', 'Kategori dağılımı',
            ['category_name'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->aggReport('assets.by_condition', 'asset-management', 'assets', 'Kondisyon dağılımı',
            ['condition'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->aggReport('assets.by_location', 'asset-management', 'assets', 'Lokasyon dağılımı',
            ['location'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->fieldsReport('assets.warranty', 'asset-management', 'assets', 'Garanti bitiş listesi',
            ['asset_code', 'name', 'warranty_end_date', 'status']);

        // Eğitim
        $defs[] = $this->aggReport('training.by_status', 'training', 'training_participants', 'Katılım durumu',
            ['status'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->aggReport('training.completion', 'training', 'training_participants', 'Tamamlama / puan',
            ['training_title'], [
                ['field' => 'id', 'fn' => 'count', 'alias' => 'cnt'],
                ['field' => 'score', 'fn' => 'avg', 'alias' => 'avg_score'],
            ]);
        $defs[] = $this->aggReport('training.by_category', 'training', 'training_participants', 'Kategori dağılımı',
            ['training_category'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->aggReport('training.hours', 'training', 'training_participants', 'Kişi başı eğitim saati',
            ['user_id'], [['field' => 'duration_hours', 'fn' => 'sum', 'alias' => 'hours']]);
        $defs[] = $this->aggReport('training.passed', 'training', 'training_participants', 'Başarı oranı',
            ['passed'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);

        // Anket
        $defs[] = $this->aggReport('surveys.by_survey', 'surveys', 'survey_responses', 'Anket cevap özeti',
            ['survey_title'], [['field' => 'answer_numeric', 'fn' => 'avg', 'alias' => 'avg_answer']]);
        $defs[] = $this->aggReport('surveys.by_question', 'surveys', 'survey_responses', 'Soru bazlı ortalama',
            ['survey_question_id'], [['field' => 'answer_numeric', 'fn' => 'avg', 'alias' => 'avg_answer']]);
        $defs[] = $this->aggReport('surveys.response_count', 'surveys', 'survey_responses', 'Cevap adedi / anket',
            ['survey_title'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);

        // Belgeler
        $defs[] = $this->aggReport('documents.by_category', 'document-management', 'employee_documents', 'Belge kategori dağılımı',
            ['category'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->fieldsReport('documents.expiring', 'document-management', 'employee_documents', 'Süresi dolan belgeler',
            ['employee_id', 'title', 'category', 'expiry_date', 'is_expired']);
        $defs[] = $this->aggReport('documents.by_status', 'document-management', 'employee_documents', 'Belge durumu',
            ['status'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->fieldsReport('documents.expired', 'document-management', 'employee_documents', 'Süresi geçmiş belgeler',
            ['employee_id', 'title', 'category', 'expiry_date'],
            [['field' => 'is_expired', 'op' => 'eq', 'value' => true]]);
        $defs[] = $this->aggReport('documents.by_visibility', 'document-management', 'employee_documents', 'Personele görünürlük',
            ['is_visible_to_employee'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);

        // Bordro (hassas — alan izni)
        $defs[] = $this->aggReport('payslips.by_period', 'hr-analytics', 'payslips', 'Bordro dönem özeti',
            ['year', 'month'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->aggReport('payslips.published', 'hr-analytics', 'payslips', 'Yayın durumu',
            ['is_published'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);

        // Analytics özet raporları (motor)
        $defs[] = $this->aggReport('analytics.workforce_dept', 'hr-analytics', 'employees', 'İş gücü — departman',
            ['department_id'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->aggReport('analytics.leave_status', 'hr-analytics', 'leave_requests', 'Analitik — izin durumu',
            ['status'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->aggReport('analytics.training_status', 'hr-analytics', 'training_participants', 'Analitik — eğitim durumu',
            ['status'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);
        $defs[] = $this->aggReport('analytics.employees_status', 'hr-analytics', 'employees', 'Analitik — personel durumu',
            ['status'], [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']]);

        return $defs;
    }

    /**
     * @param  array<string, int>  $reportIds
     * @return list<array<string, mixed>>
     */
    private function dashboardDefinitions(array $reportIds): array
    {
        $mk = function (string $module, string $key, string $name, array $widgetReportKeys) use ($reportIds): array {
            $widgets = [];
            $x = 0;
            $y = 0;
            foreach ($widgetReportKeys as $i => $rk) {
                if (! isset($reportIds[$rk])) {
                    continue;
                }
                $widgets[] = [
                    'id' => (string) Str::uuid(),
                    'type' => $i % 2 === 0 ? 'kpi' : 'chart',
                    'title' => $rk,
                    'report_id' => $reportIds[$rk],
                    'grid' => ['x' => $x, 'y' => $y, 'w' => 6, 'h' => 4],
                    'visual' => ['chart_type' => 'bar'],
                    'refresh_interval' => 60,
                ];
                $x = $x === 0 ? 6 : 0;
                if ($x === 0) {
                    $y += 4;
                }
            }

            return [
                'system_key' => $key,
                'module_key' => $module,
                'name' => $name,
                'description' => 'Sistem panosu — kopyalayarak özelleştirin',
                'layout' => ['widgets' => $widgets],
            ];
        };

        return [
            $mk('hr-analytics', 'hr-analytics.overview', 'İK Analitik Özeti', [
                'analytics.workforce_dept', 'analytics.leave_status', 'analytics.training_status',
                'analytics.employees_status', 'payslips.by_period', 'payslips.published',
            ]),
            $mk('leave-management', 'leave-management.overview', 'İzin Panosu', [
                'leaves.by_status', 'leaves.by_type', 'leaves.pending', 'leaves.balances',
                'employees.by_department', 'employees.by_status',
            ]),
            $mk('timesheet', 'timesheet.overview', 'Puantaj Panosu', [
                'timesheet.by_status', 'timesheet.late', 'timesheet.overtime',
                'timesheet.early', 'timesheet.hours', 'timesheet.by_branch',
            ]),
            $mk('job-applications', 'job-applications.overview', 'İşe Alım Panosu', [
                'recruitment.by_status', 'recruitment.by_source', 'recruitment.by_position',
                'recruitment.recent', 'recruitment.by_assignee',
            ]),
            $mk('expense-management', 'expense-management.overview', 'Masraf Panosu', [
                'expenses.by_status', 'expenses.by_amount', 'expenses.by_currency',
                'expenses.by_user', 'expenses.pending',
            ]),
            $mk('training', 'training.overview', 'Eğitim Panosu', [
                'training.by_status', 'training.completion', 'training.by_category',
                'training.hours', 'training.passed',
            ]),
            $mk('asset-management', 'asset-management.overview', 'Varlık Panosu', [
                'assets.by_status', 'assets.by_category', 'assets.by_condition',
                'assets.by_location', 'assets.warranty',
            ]),
            $mk('surveys', 'surveys.overview', 'Anket Panosu', [
                'surveys.by_survey', 'surveys.by_question', 'surveys.response_count',
            ]),
            $mk('document-management', 'document-management.overview', 'Belge Panosu', [
                'documents.by_category', 'documents.expiring', 'documents.by_status',
                'documents.expired', 'documents.by_visibility',
            ]),
        ];
    }

    /**
     * @param  list<string>  $groupBy
     * @param  list<array<string, mixed>>  $aggregations
     * @return array<string, mixed>
     */
    private function aggReport(
        string $systemKey,
        string $moduleKey,
        string $dataset,
        string $name,
        array $groupBy,
        array $aggregations,
    ): array {
        return [
            'system_key' => $systemKey,
            'module_key' => $moduleKey,
            'dataset_key' => $dataset,
            'name' => $name,
            'description' => 'Sistem raporu',
            'config' => [
                'dataset' => $dataset,
                'fields' => array_values(array_unique(array_merge(
                    $groupBy,
                    array_map(fn ($a) => $a['field'], $aggregations)
                ))),
                'group_by' => $groupBy,
                'aggregations' => $aggregations,
                'filters' => [],
                // Aggregasyonda defaultSort (örn. employee_code) GROUP BY ile çakışmasın
                'sorts' => array_map(
                    static fn (string $f): array => ['field' => $f, 'dir' => 'asc'],
                    $groupBy
                ),
                'limit' => 500,
            ],
        ];
    }

    /**
     * @param  list<string>  $fields
     * @param  list<array<string, mixed>>  $filters
     * @return array<string, mixed>
     */
    private function fieldsReport(
        string $systemKey,
        string $moduleKey,
        string $dataset,
        string $name,
        array $fields,
        array $filters = [],
    ): array {
        return [
            'system_key' => $systemKey,
            'module_key' => $moduleKey,
            'dataset_key' => $dataset,
            'name' => $name,
            'description' => 'Sistem raporu',
            'config' => [
                'dataset' => $dataset,
                'fields' => $fields,
                'filters' => $filters,
                'group_by' => [],
                'aggregations' => [],
                'sorts' => [],
                'limit' => 500,
            ],
        ];
    }
}
