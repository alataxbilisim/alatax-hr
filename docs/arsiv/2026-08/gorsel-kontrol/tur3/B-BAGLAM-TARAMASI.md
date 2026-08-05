# Tur3 B — CompanyContext taraması

**Branch:** `faz4-form-engine` · **Durulan seviye:** P1 + P2 bitti · P3/P4 sıradaki (başlanmadı)

## P1 — KVKK (bitti)

| Dosya | Satırlar (eski) | Eski kaynak | Yeni kaynak | Test |
|-------|-----------------|-------------|-------------|------|
| `Kvkk/DestructionController.php` | 28,52,67,75,98,116,131,141 | `$request->user()->company_id` | `getCompanyId()` (BaseController) | `CompanyContextKvkkPdksTest::test_p1_destruction_candidates_follow_active_context_not_home` |
| `Kvkk/LegalHoldController.php` | 21,38,52 | `$request->user()->company_id` | `getCompanyId()` | `…::test_p1_legal_hold_follows_active_context_not_home` |
| `Kvkk/DataBreachController.php` | 21,27,57,65,91 | `$request->user()->company_id` | `getCompanyId()` | `…::test_p1_data_breach_follows_active_context_not_home` |
| `Kvkk/RetentionPolicyController.php` | 20,43,51,71 | `$request->user()->company_id` | `getCompanyId()` | `…::test_p1_retention_policy_follows_active_context_not_home` |
| `Kvkk/DataSubjectRequestController.php` | 218 | `$user->company_id` (employee match) | `(int) $this->getCompanyId()` | `…::test_p1_data_subject_download_employee_match_uses_active_context` |

## P2 — PDKS (bitti)

| Dosya | Satırlar | Eski kaynak | Yeni kaynak | Test |
|-------|----------|-------------|-------------|------|
| `Services/Timesheet/AttendanceClockService.php` | 41,54,100,151 | `$user->company_id` | `resolveCompanyId()`: açık param → CompanyContext → home | `…::test_p2_attendance_clock_writes_to_active_company_context` |
| `Portal/PortalTimesheetController.php` | clockIn/Out | (dolaylı home) | `(int) $this->getCompanyId()` iletir | (servis testi + çağıran) |
| `Portal/PortalAttendanceQrController.php` | punch | (dolaylı home) | `(int) $this->getCompanyId()` iletir | (servis testi + çağıran) |

## P3 / P4 — sıradaki (bu turda dokunulmadı)

- `Services/Settings/SettingsResolver.php` (~245–250) — home `company_id`
- `Settings/SettingsRegistryController.php` — `buildScope()` home/request
- `Services/Reports/Datasets/*Dataset.php` — Payslips, EmployeeDocuments, TrainingParticipants, SurveyResponses

## B3 — Registry izolasyon paketi

`ApprovalEntityIsolationTest::test_active_context_a_cannot_access_company_b_approval_entity` — data provider, `ApprovalEntityRegistry::all()` ile senkron (yeni entity eklenince provider güncellenmezse fail).

| Entity | Probe | Sonuç |
|--------|-------|--------|
| leave_request | GET `/leaves/requests/{id}` | 403/404 |
| expense_request | GET `/expenses/claims/{id}` | 403/404 |
| employee_request | BelongsToCompany scope (panel show yok) | 404 eşdeğeri |
| salary_review | GET `/salary-reviews/{id}` | 403/404 |
| data_subject_request | GET `/kvkk/data-subject-requests/{id}` | 403/404 |

## Bilinçli fallback (dokunulmadı — teyit)

| Yer | Gerekçe |
|-----|---------|
| `Traits/BelongsToCompany:59` | Context yokken SuperAdmin dışı kullanıcıda home `company_id` — job/auth yok senaryosu için tasarım fallback. |
| `Policies/ApprovalWorkflowPolicy:54` | Context bağlı değilse home; policy aktif şirket ile workflow.company_id eşler. |
| `Services/BranchContextService:38` | Şube listesi aktif context'ten; yoksa home — seçici bootstrap. |
| `EmployeeDashboardController` | Zaten `CompanyContext::id() ?? user.company_id` — kısmen çevrilmiş, bilinçli. |
| `UserController` | `getCompanyId()` + home izolasyon kontrolleri — home alanı kullanıcı kaydının sahibi; operasyonel liste context'ten. |
| `*/Portal/*` | Portal tek-firma personel yüzü; çoğu endpoint home personel kaydına bağlı (DSR portal index hâlâ home — ayrı borç). |

## php artisan test (Docker `alatax-hr-app`)

```
Filter: CompanyContextKvkkPdksTest|ApprovalEntityIsolationTest
Tests: 11 passed (44 assertions)

Full suite: Tests: 668 passed (2950 assertions)
```
