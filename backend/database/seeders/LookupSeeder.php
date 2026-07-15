<?php

namespace Database\Seeders;

use App\Models\Lookup;
use App\Services\LookupService;
use Illuminate\Database\Seeder;

/**
 * Lookup Engine seed — idempotent.
 * Sistem: currency, city_tr, blood_type, country, document_file_type, survey_question_type.
 * Firma default: employee_*, contract, work_type, training_*, survey_type…
 * Hibrit: leave_request_status, expense_claim_status, employee_request_status,
 * performance_period_status, performance_review_status, onboarding_process_status,
 * onboarding_task_status, document_approval_status, employee_document_status,
 * training_session_status (meta.hybrid).
 */
class LookupSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedEmployeeStatus();
        $this->seedWorkType();
        $this->seedGender();
        $this->seedMaritalStatus();
        $this->seedEducationLevel();
        $this->seedEmergencyRelation();
        $this->seedContractType();
        $this->seedEmployeeDocumentCategory();
        $this->seedLeaveRequestStatus();
        $this->seedLeaveGenderRestriction();
        $this->seedHolidayType();
        $this->seedApplicationStage();
        $this->seedOvertimeType();
        $this->seedExperienceLevel();
        $this->seedJobPositionStatus();
        $this->seedInterviewType();
        $this->seedInterviewStatus();
        $this->seedInterviewRecommendation();
        $this->seedAssetStatus();
        $this->seedAssetCondition();
        $this->seedExpenseClaimStatus();
        $this->seedEmployeeRequestPriority();
        $this->seedEmployeeRequestStatus();
        $this->seedPerformancePeriodStatus();
        $this->seedPerformanceReviewStatus();
        $this->seedContinuousFeedbackType();
        $this->seedOnboardingProcessStatus();
        $this->seedOnboardingTaskStatus();
        $this->seedTerminationReason();
        $this->seedSalaryChangeReason();
        $this->seedSalaryReviewStatus();
        $this->seedDocumentApprovalStatus();
        $this->seedDocumentFileType();
        $this->seedEmployeeDocumentStatus();
        $this->seedTrainingType();
        $this->seedTrainingSessionStatus();
        $this->seedTrainingCategory();
        $this->seedSurveyType();
        $this->seedSurveyQuestionType();
        $this->seedCurrency();
        $this->seedBloodType();
        $this->seedCountries();
        $this->seedCitiesTr();
    }

    private function seedEmployeeStatus(): void
    {
        foreach ([
            ['value' => 'active', 'label' => 'Aktif', 'color' => '#10b981', 'sort_order' => 10],
            ['value' => 'on_leave', 'label' => 'İzinli', 'color' => '#f59e0b', 'sort_order' => 20],
            ['value' => 'suspended', 'label' => 'Askıda', 'color' => '#ef4444', 'sort_order' => 30],
            ['value' => 'terminated', 'label' => 'İşten Çıkmış', 'color' => '#64748b', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_EMPLOYEE_STATUS, $item, false);
        }
    }

    private function seedWorkType(): void
    {
        foreach ([
            ['value' => 'full_time', 'label' => 'Tam Zamanlı', 'sort_order' => 10],
            ['value' => 'part_time', 'label' => 'Yarı Zamanlı', 'sort_order' => 20],
            ['value' => 'remote', 'label' => 'Uzaktan', 'sort_order' => 30],
            ['value' => 'hybrid', 'label' => 'Hibrit', 'sort_order' => 40],
            ['value' => 'contract', 'label' => 'Sözleşmeli', 'sort_order' => 50],
            ['value' => 'internship', 'label' => 'Stajyer', 'sort_order' => 60],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_WORK_TYPE, $item, false);
        }
    }

    private function seedGender(): void
    {
        foreach ([
            ['value' => 'male', 'label' => 'Erkek', 'sort_order' => 10],
            ['value' => 'female', 'label' => 'Kadın', 'sort_order' => 20],
            ['value' => 'other', 'label' => 'Diğer', 'sort_order' => 30],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_GENDER, $item, false);
        }
    }

    private function seedMaritalStatus(): void
    {
        foreach ([
            ['value' => 'single', 'label' => 'Bekar', 'sort_order' => 10],
            ['value' => 'married', 'label' => 'Evli', 'sort_order' => 20],
            ['value' => 'divorced', 'label' => 'Boşanmış', 'sort_order' => 30],
            ['value' => 'widowed', 'label' => 'Dul', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_MARITAL_STATUS, $item, false);
        }
    }

    private function seedEducationLevel(): void
    {
        // Mevcut employee kayıtları TR etiketi value olarak tutuyor — K-A uyumu
        foreach ([
            ['value' => 'İlkokul', 'label' => 'İlkokul', 'sort_order' => 10],
            ['value' => 'Ortaokul', 'label' => 'Ortaokul', 'sort_order' => 20],
            ['value' => 'Lise', 'label' => 'Lise', 'sort_order' => 30],
            ['value' => 'Önlisans', 'label' => 'Önlisans', 'sort_order' => 40],
            ['value' => 'Lisans', 'label' => 'Lisans', 'sort_order' => 50],
            ['value' => 'Yüksek Lisans', 'label' => 'Yüksek Lisans', 'sort_order' => 60],
            ['value' => 'Doktora', 'label' => 'Doktora', 'sort_order' => 70],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_EDUCATION_LEVEL, $item, false);
        }
    }

    private function seedEmergencyRelation(): void
    {
        foreach ([
            ['value' => 'Eş', 'label' => 'Eş', 'sort_order' => 10],
            ['value' => 'Anne', 'label' => 'Anne', 'sort_order' => 20],
            ['value' => 'Baba', 'label' => 'Baba', 'sort_order' => 30],
            ['value' => 'Kardeş', 'label' => 'Kardeş', 'sort_order' => 40],
            ['value' => 'Çocuk', 'label' => 'Çocuk', 'sort_order' => 50],
            ['value' => 'Arkadaş', 'label' => 'Arkadaş', 'sort_order' => 60],
            ['value' => 'Diğer', 'label' => 'Diğer', 'sort_order' => 70],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_EMERGENCY_RELATION, $item, false);
        }
    }

    private function seedContractType(): void
    {
        foreach ([
            ['value' => 'permanent', 'label' => 'Süresiz (Belirsiz Süreli)', 'sort_order' => 10],
            ['value' => 'temporary', 'label' => 'Süreli (Belirli Süreli)', 'sort_order' => 20],
            ['value' => 'intern', 'label' => 'Stajyer', 'sort_order' => 30],
            ['value' => 'contract', 'label' => 'Sözleşmeli', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_CONTRACT_TYPE, $item, false);
        }
    }

    private function seedEmployeeDocumentCategory(): void
    {
        foreach ([
            ['value' => 'id_card', 'label' => 'Kimlik', 'sort_order' => 10],
            ['value' => 'contract', 'label' => 'Sözleşme', 'sort_order' => 20],
            ['value' => 'certificate', 'label' => 'Sertifika', 'sort_order' => 30],
            ['value' => 'education', 'label' => 'Eğitim', 'sort_order' => 40],
            ['value' => 'health', 'label' => 'Sağlık', 'sort_order' => 50],
            ['value' => 'other', 'label' => 'Diğer', 'sort_order' => 60],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_EMPLOYEE_DOCUMENT_CATEGORY, $item, false);
        }
    }

    private function seedLeaveRequestStatus(): void
    {
        $meta = ['hybrid' => true];
        foreach ([
            ['value' => 'pending', 'label' => 'Bekleyen', 'color' => '#f59e0b', 'sort_order' => 10],
            ['value' => 'approved', 'label' => 'Onaylanan', 'color' => '#10b981', 'sort_order' => 20],
            ['value' => 'rejected', 'label' => 'Reddedilen', 'color' => '#ef4444', 'sort_order' => 30],
            ['value' => 'cancelled', 'label' => 'İptal', 'color' => '#64748b', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_LEAVE_REQUEST_STATUS, $item, false, $meta);
        }
    }

    private function seedLeaveGenderRestriction(): void
    {
        $meta = ['hybrid' => true];
        foreach ([
            ['value' => 'all', 'label' => 'Herkes', 'sort_order' => 10],
            ['value' => 'male', 'label' => 'Erkek', 'sort_order' => 20],
            ['value' => 'female', 'label' => 'Kadın', 'sort_order' => 30],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_LEAVE_GENDER_RESTRICTION, $item, false, $meta);
        }
    }

    private function seedHolidayType(): void
    {
        foreach ([
            ['value' => 'national', 'label' => 'Resmi Tatil', 'sort_order' => 10],
            ['value' => 'religious', 'label' => 'Dini Tatil', 'sort_order' => 20],
            ['value' => 'company', 'label' => 'Şirket Tatili', 'sort_order' => 30],
            ['value' => 'regional', 'label' => 'Bölgesel', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_HOLIDAY_TYPE, $item, false);
        }
    }

    /** JobApplicationStatus enum ile birebir — kanban hibrit */
    private function seedApplicationStage(): void
    {
        $meta = ['hybrid' => true];
        foreach ([
            ['value' => 'new', 'label' => 'Yeni', 'color' => '#94a3b8', 'sort_order' => 10],
            ['value' => 'reviewing', 'label' => 'İnceleniyor', 'color' => '#f59e0b', 'sort_order' => 20],
            ['value' => 'shortlisted', 'label' => 'Ön Seçim', 'color' => '#8b5cf6', 'sort_order' => 30],
            ['value' => 'interview_scheduled', 'label' => 'Mülakat Planlandı', 'color' => '#3b82f6', 'sort_order' => 40],
            ['value' => 'interviewed', 'label' => 'Mülakat Yapıldı', 'color' => '#0ea5e9', 'sort_order' => 50],
            ['value' => 'offer_sent', 'label' => 'Teklif Gönderildi', 'color' => '#6366f1', 'sort_order' => 60],
            ['value' => 'hired', 'label' => 'İşe Alındı', 'color' => '#10b981', 'sort_order' => 70],
            ['value' => 'rejected', 'label' => 'Reddedildi', 'color' => '#ef4444', 'sort_order' => 80],
            ['value' => 'withdrawn', 'label' => 'Çekildi', 'color' => '#6b7280', 'sort_order' => 90],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_APPLICATION_STAGE, $item, false, $meta);
        }
    }

    /** Mesai / vardiya kavramları — FAZ A1 (yoksa ekle) */
    private function seedOvertimeType(): void
    {
        foreach ([
            ['value' => 'normal', 'label' => 'Normal Mesai', 'sort_order' => 10],
            ['value' => 'overtime', 'label' => 'Fazla Mesai', 'sort_order' => 20],
            ['value' => 'night', 'label' => 'Gece Mesaisi', 'sort_order' => 30],
            ['value' => 'weekend', 'label' => 'Hafta Tatili Çalışması', 'sort_order' => 40],
            ['value' => 'holiday', 'label' => 'Resmi Tatil Çalışması', 'sort_order' => 50],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_OVERTIME_TYPE, $item, false);
        }
    }

    private function seedExperienceLevel(): void
    {
        foreach ([
            ['value' => 'entry', 'label' => 'Başlangıç', 'sort_order' => 10],
            ['value' => 'mid', 'label' => 'Orta', 'sort_order' => 20],
            ['value' => 'senior', 'label' => 'Kıdemli', 'sort_order' => 30],
            ['value' => 'lead', 'label' => 'Lead', 'sort_order' => 40],
            ['value' => 'manager', 'label' => 'Yönetici', 'sort_order' => 50],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_EXPERIENCE_LEVEL, $item, false);
        }
    }

    private function seedJobPositionStatus(): void
    {
        foreach ([
            ['value' => 'draft', 'label' => 'Taslak', 'sort_order' => 10],
            ['value' => 'active', 'label' => 'Aktif', 'color' => '#10b981', 'sort_order' => 20],
            ['value' => 'paused', 'label' => 'Duraklatıldı', 'color' => '#f59e0b', 'sort_order' => 30],
            ['value' => 'closed', 'label' => 'Kapalı', 'color' => '#64748b', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_JOB_POSITION_STATUS, $item, false);
        }
    }

    private function seedInterviewType(): void
    {
        foreach ([
            ['value' => 'phone', 'label' => 'Telefon', 'sort_order' => 10],
            ['value' => 'video', 'label' => 'Video', 'sort_order' => 20],
            ['value' => 'onsite', 'label' => 'Yüz Yüze', 'sort_order' => 30],
            ['value' => 'technical', 'label' => 'Teknik', 'sort_order' => 40],
            ['value' => 'hr', 'label' => 'İK', 'sort_order' => 50],
            ['value' => 'panel', 'label' => 'Panel', 'sort_order' => 60],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_INTERVIEW_TYPE, $item, false);
        }
    }

    private function seedInterviewStatus(): void
    {
        $meta = ['hybrid' => true];
        foreach ([
            ['value' => 'scheduled', 'label' => 'Planlandı', 'color' => '#3b82f6', 'sort_order' => 10],
            ['value' => 'completed', 'label' => 'Tamamlandı', 'color' => '#10b981', 'sort_order' => 20],
            ['value' => 'cancelled', 'label' => 'İptal', 'color' => '#ef4444', 'sort_order' => 30],
            ['value' => 'no_show', 'label' => 'Gelmedi', 'color' => '#f59e0b', 'sort_order' => 40],
            ['value' => 'rescheduled', 'label' => 'Ertelendi', 'color' => '#8b5cf6', 'sort_order' => 50],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_INTERVIEW_STATUS, $item, false, $meta);
        }
    }

    private function seedInterviewRecommendation(): void
    {
        foreach ([
            ['value' => 'strong_hire', 'label' => 'Kesinlikle Alınsın', 'color' => '#10b981', 'sort_order' => 10],
            ['value' => 'hire', 'label' => 'Alınsın', 'color' => '#34d399', 'sort_order' => 20],
            ['value' => 'no_decision', 'label' => 'Kararsız', 'color' => '#94a3b8', 'sort_order' => 30],
            ['value' => 'no_hire', 'label' => 'Alınmasın', 'color' => '#f59e0b', 'sort_order' => 40],
            ['value' => 'strong_no_hire', 'label' => 'Kesinlikle Alınmasın', 'color' => '#ef4444', 'sort_order' => 50],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_INTERVIEW_RECOMMENDATION, $item, false);
        }
    }

    private function seedAssetStatus(): void
    {
        foreach ([
            ['value' => 'available', 'label' => 'Müsait', 'color' => '#10b981', 'sort_order' => 10],
            ['value' => 'assigned', 'label' => 'Zimmetli', 'color' => '#3b82f6', 'sort_order' => 20],
            ['value' => 'maintenance', 'label' => 'Bakımda', 'color' => '#f59e0b', 'sort_order' => 30],
            ['value' => 'disposed', 'label' => 'Elden Çıkarıldı', 'color' => '#64748b', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_ASSET_STATUS, $item, false);
        }
    }

    private function seedAssetCondition(): void
    {
        foreach ([
            ['value' => 'new', 'label' => 'Yeni', 'sort_order' => 10],
            ['value' => 'good', 'label' => 'İyi', 'sort_order' => 20],
            ['value' => 'fair', 'label' => 'Orta', 'sort_order' => 30],
            ['value' => 'poor', 'label' => 'Kötü', 'sort_order' => 40],
            ['value' => 'broken', 'label' => 'Bozuk', 'sort_order' => 50],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_ASSET_CONDITION, $item, false);
        }
    }

    /** ExpenseClaim::STATUS_* ile birebir — hibrit */
    private function seedExpenseClaimStatus(): void
    {
        $meta = ['hybrid' => true];
        foreach ([
            ['value' => 'draft', 'label' => 'Taslak', 'color' => '#64748b', 'sort_order' => 10],
            ['value' => 'submitted', 'label' => 'Gönderildi', 'color' => '#f59e0b', 'sort_order' => 20],
            ['value' => 'approved', 'label' => 'Onaylandı', 'color' => '#10b981', 'sort_order' => 30],
            ['value' => 'rejected', 'label' => 'Reddedildi', 'color' => '#ef4444', 'sort_order' => 40],
            ['value' => 'paid', 'label' => 'Ödendi', 'color' => '#3b82f6', 'sort_order' => 50],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_EXPENSE_CLAIM_STATUS, $item, false, $meta);
        }
    }

    private function seedEmployeeRequestPriority(): void
    {
        foreach ([
            ['value' => 'low', 'label' => 'Düşük', 'color' => '#94a3b8', 'sort_order' => 10],
            ['value' => 'normal', 'label' => 'Normal', 'color' => '#3b82f6', 'sort_order' => 20],
            ['value' => 'high', 'label' => 'Yüksek', 'color' => '#f59e0b', 'sort_order' => 30],
            ['value' => 'urgent', 'label' => 'Acil', 'color' => '#ef4444', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_EMPLOYEE_REQUEST_PRIORITY, $item, false);
        }
    }

    /** EmployeeRequest::STATUS_* ile birebir — hibrit */
    private function seedEmployeeRequestStatus(): void
    {
        $meta = ['hybrid' => true];
        foreach ([
            ['value' => 'pending', 'label' => 'Beklemede', 'color' => '#f59e0b', 'sort_order' => 10],
            ['value' => 'in_review', 'label' => 'İnceleniyor', 'color' => '#3b82f6', 'sort_order' => 20],
            ['value' => 'approved', 'label' => 'Onaylandı', 'color' => '#10b981', 'sort_order' => 30],
            ['value' => 'rejected', 'label' => 'Reddedildi', 'color' => '#ef4444', 'sort_order' => 40],
            ['value' => 'cancelled', 'label' => 'İptal Edildi', 'color' => '#64748b', 'sort_order' => 50],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_EMPLOYEE_REQUEST_STATUS, $item, false, $meta);
        }
    }

    /** PerformancePeriod status — hibrit */
    private function seedPerformancePeriodStatus(): void
    {
        $meta = ['hybrid' => true];
        foreach ([
            ['value' => 'draft', 'label' => 'Taslak', 'color' => '#64748b', 'sort_order' => 10],
            ['value' => 'active', 'label' => 'Aktif', 'color' => '#10b981', 'sort_order' => 20],
            ['value' => 'closed', 'label' => 'Kapalı', 'color' => '#94a3b8', 'sort_order' => 30],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_PERFORMANCE_PERIOD_STATUS, $item, false, $meta);
        }
    }

    /** PerformanceReview status — hibrit */
    private function seedPerformanceReviewStatus(): void
    {
        $meta = ['hybrid' => true];
        foreach ([
            ['value' => 'draft', 'label' => 'Taslak', 'color' => '#64748b', 'sort_order' => 10],
            ['value' => 'submitted', 'label' => 'Gönderildi', 'color' => '#f59e0b', 'sort_order' => 20],
            ['value' => 'approved', 'label' => 'Onaylandı', 'color' => '#10b981', 'sort_order' => 30],
            ['value' => 'rejected', 'label' => 'Reddedildi', 'color' => '#ef4444', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_PERFORMANCE_REVIEW_STATUS, $item, false, $meta);
        }
    }

    /** ContinuousFeedback::TYPE_* ile birebir — firma */
    private function seedContinuousFeedbackType(): void
    {
        foreach ([
            ['value' => 'praise', 'label' => 'Takdir/Övgü', 'color' => '#10b981', 'sort_order' => 10],
            ['value' => 'suggestion', 'label' => 'Öneri', 'color' => '#3b82f6', 'sort_order' => 20],
            ['value' => 'concern', 'label' => 'Endişe', 'color' => '#f59e0b', 'sort_order' => 30],
            ['value' => 'coaching', 'label' => 'Koçluk', 'color' => '#8b5cf6', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_CONTINUOUS_FEEDBACK_TYPE, $item, false);
        }
    }

    /** OnboardingProcess::STATUS_* ile birebir — hibrit */
    private function seedOnboardingProcessStatus(): void
    {
        $meta = ['hybrid' => true];
        foreach ([
            ['value' => 'pending', 'label' => 'Bekliyor', 'color' => '#f59e0b', 'sort_order' => 10],
            ['value' => 'in_progress', 'label' => 'Devam Ediyor', 'color' => '#3b82f6', 'sort_order' => 20],
            ['value' => 'completed', 'label' => 'Tamamlandı', 'color' => '#10b981', 'sort_order' => 30],
            ['value' => 'cancelled', 'label' => 'İptal Edildi', 'color' => '#64748b', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_ONBOARDING_PROCESS_STATUS, $item, false, $meta);
        }
    }

    /** OnboardingTask::STATUS_* ile birebir — hibrit */
    private function seedOnboardingTaskStatus(): void
    {
        $meta = ['hybrid' => true];
        foreach ([
            ['value' => 'pending', 'label' => 'Bekliyor', 'color' => '#94a3b8', 'sort_order' => 10],
            ['value' => 'in_progress', 'label' => 'Devam Ediyor', 'color' => '#f59e0b', 'sort_order' => 20],
            ['value' => 'completed', 'label' => 'Tamamlandı', 'color' => '#10b981', 'sort_order' => 30],
            ['value' => 'skipped', 'label' => 'Atlandı', 'color' => '#64748b', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_ONBOARDING_TASK_STATUS, $item, false, $meta);
        }
    }

    /** SGK işten çıkış kodları (yaygın) — hibrit */
    private function seedTerminationReason(): void
    {
        $meta = ['hybrid' => true];
        foreach ([
            ['value' => '01', 'label' => '01 — Deneme süreli sözleşmenin işverence feshi', 'sort_order' => 10],
            ['value' => '02', 'label' => '02 — Deneme süreli sözleşmenin işçi tarafından feshi', 'sort_order' => 20],
            ['value' => '03', 'label' => '03 — Belirsiz süreli sözleşmenin işçi tarafından feshi (istifa)', 'sort_order' => 30],
            ['value' => '04', 'label' => '04 — Belirsiz süreli sözleşmenin işverence haklı sebep bildirilmeden feshi', 'sort_order' => 40],
            ['value' => '05', 'label' => '05 — Belirsiz süreli sözleşmenin işverence haklı sebeple feshi', 'sort_order' => 50],
            ['value' => '08', 'label' => '08 — Vize süresinin bitimi', 'sort_order' => 60],
            ['value' => '09', 'label' => '09 — İşçinin ölümü', 'sort_order' => 70],
            ['value' => '11', 'label' => '11 — Emeklilik', 'sort_order' => 80],
            ['value' => '13', 'label' => '13 — Belirli süreli sözleşmenin sona ermesi', 'sort_order' => 90],
            ['value' => '17', 'label' => '17 — İşyerinin kapanması', 'sort_order' => 100],
            ['value' => '22', 'label' => '22 — Diğer nedenler', 'sort_order' => 110],
            ['value' => '25', 'label' => '25 — Karşılıklı anlaşma ile fesih', 'sort_order' => 120],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_TERMINATION_REASON, $item, false, $meta);
        }
    }

    private function seedSalaryChangeReason(): void
    {
        $meta = ['hybrid' => true];
        foreach ([
            ['value' => 'initial', 'label' => 'Başlangıç', 'sort_order' => 10],
            ['value' => 'annual_raise', 'label' => 'Yıllık zam', 'sort_order' => 20],
            ['value' => 'promotion', 'label' => 'Terfi', 'sort_order' => 30],
            ['value' => 'role_change', 'label' => 'Görev değişikliği', 'sort_order' => 40],
            ['value' => 'market_adjustment', 'label' => 'Piyasa düzeltmesi', 'sort_order' => 50],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_SALARY_CHANGE_REASON, $item, false, $meta);
        }
    }

    private function seedSalaryReviewStatus(): void
    {
        $meta = ['hybrid' => true];
        foreach ([
            ['value' => 'draft', 'label' => 'Taslak', 'color' => '#64748b', 'sort_order' => 10],
            ['value' => 'pending_approval', 'label' => 'Onayda', 'color' => '#f59e0b', 'sort_order' => 20],
            ['value' => 'approved', 'label' => 'Onaylandı', 'color' => '#10b981', 'sort_order' => 30],
            ['value' => 'rejected', 'label' => 'Reddedildi', 'color' => '#ef4444', 'sort_order' => 40],
            ['value' => 'cancelled', 'label' => 'İptal', 'color' => '#94a3b8', 'sort_order' => 50],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_SALARY_REVIEW_STATUS, $item, false, $meta);
        }
    }

    /** ApprovalStatusValue / documents.approval_status — hibrit */
    private function seedDocumentApprovalStatus(): void
    {
        $meta = ['hybrid' => true];
        foreach ([
            ['value' => 'draft', 'label' => 'Taslak', 'color' => '#64748b', 'sort_order' => 10],
            ['value' => 'pending', 'label' => 'Onay Bekliyor', 'color' => '#f59e0b', 'sort_order' => 20],
            ['value' => 'approved', 'label' => 'Onaylandı', 'color' => '#10b981', 'sort_order' => 30],
            ['value' => 'rejected', 'label' => 'Reddedildi', 'color' => '#ef4444', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_DOCUMENT_APPROVAL_STATUS, $item, false, $meta);
        }
    }

    /** DocumentController MIME kategori filtresi — sistem */
    private function seedDocumentFileType(): void
    {
        foreach ([
            ['value' => 'pdf', 'label' => 'PDF', 'sort_order' => 10],
            ['value' => 'image', 'label' => 'Resim', 'sort_order' => 20],
            ['value' => 'document', 'label' => 'Word', 'sort_order' => 30],
            ['value' => 'spreadsheet', 'label' => 'Excel', 'sort_order' => 40],
            ['value' => 'presentation', 'label' => 'PowerPoint', 'sort_order' => 50],
            ['value' => 'archive', 'label' => 'Arşiv (ZIP, RAR)', 'sort_order' => 60],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_DOCUMENT_FILE_TYPE, $item, true);
        }
    }

    /** EmployeeDocument status (active/archived/expired) — hibrit */
    private function seedEmployeeDocumentStatus(): void
    {
        $meta = ['hybrid' => true];
        foreach ([
            ['value' => 'active', 'label' => 'Aktif', 'color' => '#10b981', 'sort_order' => 10],
            ['value' => 'archived', 'label' => 'Arşivlendi', 'color' => '#64748b', 'sort_order' => 20],
            ['value' => 'expired', 'label' => 'Süresi Doldu', 'color' => '#ef4444', 'sort_order' => 30],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_EMPLOYEE_DOCUMENT_STATUS, $item, false, $meta);
        }
    }

    /** Training.type — firma */
    private function seedTrainingType(): void
    {
        foreach ([
            ['value' => 'online', 'label' => 'Online', 'sort_order' => 10],
            ['value' => 'classroom', 'label' => 'Sınıf İçi', 'sort_order' => 20],
            ['value' => 'hybrid', 'label' => 'Hibrit', 'sort_order' => 30],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_TRAINING_TYPE, $item, false);
        }
    }

    /** TrainingSession.status — hibrit */
    private function seedTrainingSessionStatus(): void
    {
        $meta = ['hybrid' => true];
        foreach ([
            ['value' => 'scheduled', 'label' => 'Planlandı', 'color' => '#3b82f6', 'sort_order' => 10],
            ['value' => 'in_progress', 'label' => 'Devam Ediyor', 'color' => '#f59e0b', 'sort_order' => 20],
            ['value' => 'completed', 'label' => 'Tamamlandı', 'color' => '#10b981', 'sort_order' => 30],
            ['value' => 'cancelled', 'label' => 'İptal Edildi', 'color' => '#ef4444', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_TRAINING_SESSION_STATUS, $item, false, $meta);
        }
    }

    /** Training.category — firma (TR varsayılanlar) */
    private function seedTrainingCategory(): void
    {
        foreach ([
            ['value' => 'general', 'label' => 'Genel', 'sort_order' => 10],
            ['value' => 'technical', 'label' => 'Teknik', 'sort_order' => 20],
            ['value' => 'soft_skills', 'label' => 'Yumuşak Beceri', 'sort_order' => 30],
            ['value' => 'compliance', 'label' => 'Uyum', 'sort_order' => 40],
            ['value' => 'leadership', 'label' => 'Liderlik', 'sort_order' => 50],
            ['value' => 'safety', 'label' => 'Güvenlik', 'sort_order' => 60],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_TRAINING_CATEGORY, $item, false);
        }
    }

    /** Survey::TYPE_* — firma */
    private function seedSurveyType(): void
    {
        foreach ([
            ['value' => 'engagement', 'label' => 'Çalışan Bağlılığı', 'sort_order' => 10],
            ['value' => 'satisfaction', 'label' => 'Memnuniyet', 'sort_order' => 20],
            ['value' => 'pulse', 'label' => 'Nabız Yoklaması', 'sort_order' => 30],
            ['value' => 'enps', 'label' => 'Employee NPS', 'sort_order' => 40],
            ['value' => 'onboarding', 'label' => 'Onboarding Deneyimi', 'sort_order' => 50],
            ['value' => 'exit', 'label' => 'Çıkış Mülakatı', 'sort_order' => 60],
            ['value' => 'custom', 'label' => 'Özel', 'sort_order' => 70],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_SURVEY_TYPE, $item, false);
        }
    }

    /** SurveyQuestion::TYPE_* — sistem */
    private function seedSurveyQuestionType(): void
    {
        foreach ([
            ['value' => 'single_choice', 'label' => 'Tek Seçim', 'sort_order' => 10],
            ['value' => 'multiple_choice', 'label' => 'Çoklu Seçim', 'sort_order' => 20],
            ['value' => 'rating', 'label' => 'Puanlama', 'sort_order' => 30],
            ['value' => 'nps', 'label' => 'NPS', 'sort_order' => 40],
            ['value' => 'text', 'label' => 'Açık Uçlu', 'sort_order' => 50],
            ['value' => 'scale', 'label' => 'Ölçek', 'sort_order' => 60],
            ['value' => 'matrix', 'label' => 'Matris', 'sort_order' => 70],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_SURVEY_QUESTION_TYPE, $item, true);
        }
    }

    private function seedCurrency(): void
    {
        foreach ([
            ['value' => 'TRY', 'label' => 'TRY (₺)', 'sort_order' => 10],
            ['value' => 'USD', 'label' => 'USD ($)', 'sort_order' => 20],
            ['value' => 'EUR', 'label' => 'EUR (€)', 'sort_order' => 30],
            ['value' => 'GBP', 'label' => 'GBP (£)', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_CURRENCY, $item, true);
        }
    }

    private function seedBloodType(): void
    {
        $types = ['A Rh+', 'A Rh-', 'B Rh+', 'B Rh-', 'AB Rh+', 'AB Rh-', '0 Rh+', '0 Rh-'];
        foreach ($types as $i => $type) {
            $this->upsertDefault(LookupService::TYPE_BLOOD_TYPE, [
                'value' => $type,
                'label' => $type,
                'sort_order' => ($i + 1) * 10,
            ], true);
        }
    }

    private function seedCountries(): void
    {
        foreach ([
            ['value' => 'TR', 'label' => 'Türkiye', 'sort_order' => 10],
            ['value' => 'DE', 'label' => 'Almanya', 'sort_order' => 20],
            ['value' => 'US', 'label' => 'Amerika Birleşik Devletleri', 'sort_order' => 30],
            ['value' => 'GB', 'label' => 'Birleşik Krallık', 'sort_order' => 40],
            ['value' => 'FR', 'label' => 'Fransa', 'sort_order' => 50],
            ['value' => 'NL', 'label' => 'Hollanda', 'sort_order' => 60],
            ['value' => 'AZ', 'label' => 'Azerbaycan', 'sort_order' => 70],
            ['value' => 'CY', 'label' => 'Kıbrıs', 'sort_order' => 80],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_COUNTRY, $item, true);
        }
    }

    private function seedCitiesTr(): void
    {
        $cities = [
            'Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Aksaray', 'Amasya', 'Ankara', 'Antalya',
            'Ardahan', 'Artvin', 'Aydın', 'Balıkesir', 'Bartın', 'Batman', 'Bayburt', 'Bilecik',
            'Bingöl', 'Bitlis', 'Bolu', 'Burdur', 'Bursa', 'Çanakkale', 'Çankırı', 'Çorum',
            'Denizli', 'Diyarbakır', 'Düzce', 'Edirne', 'Elazığ', 'Erzincan', 'Erzurum', 'Eskişehir',
            'Gaziantep', 'Giresun', 'Gümüşhane', 'Hakkari', 'Hatay', 'Iğdır', 'Isparta', 'İstanbul',
            'İzmir', 'Kahramanmaraş', 'Karabük', 'Karaman', 'Kars', 'Kastamonu', 'Kayseri', 'Kırıkkale',
            'Kırklareli', 'Kırşehir', 'Kilis', 'Kocaeli', 'Konya', 'Kütahya', 'Malatya', 'Manisa',
            'Mardin', 'Mersin', 'Muğla', 'Muş', 'Nevşehir', 'Niğde', 'Ordu', 'Osmaniye',
            'Rize', 'Sakarya', 'Samsun', 'Siirt', 'Sinop', 'Sivas', 'Şanlıurfa', 'Şırnak',
            'Tekirdağ', 'Tokat', 'Trabzon', 'Tunceli', 'Uşak', 'Van', 'Yalova', 'Yozgat', 'Zonguldak',
        ];

        foreach ($cities as $i => $city) {
            $this->upsertDefault(LookupService::TYPE_CITY_TR, [
                'value' => $city,
                'label' => $city,
                'sort_order' => ($i + 1) * 10,
            ], true);
        }
    }

    /**
     * @param  array{value: string, label: string, color?: ?string, sort_order: int}  $item
     * @param  array<string, mixed>|null  $meta
     */
    private function upsertDefault(string $type, array $item, bool $isSystem, ?array $meta = null): void
    {
        $row = Lookup::withTrashed()
            ->whereNull('company_id')
            ->where('lookup_type', $type)
            ->where('value', $item['value'])
            ->first();

        $payload = [
            'label' => $item['label'],
            'color' => $item['color'] ?? null,
            'sort_order' => $item['sort_order'],
            'is_active' => true,
            'is_system' => $isSystem,
            'parent_lookup_id' => null,
            'meta' => $meta,
            'deleted_at' => null,
        ];

        if ($row) {
            $row->fill($payload)->save();

            return;
        }

        Lookup::create(array_merge([
            'company_id' => null,
            'lookup_type' => $type,
            'value' => $item['value'],
        ], $payload));
    }
}
