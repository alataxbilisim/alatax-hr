# MENU + PUANTAJ TEŞHİS

**Branch:** `faz4-form-engine`  
**Tarih:** 15 Temmuz 2026  
**Kapsam:** Salt okuma — uygulama / kod değişikliği yok.

**Kaynaklar:**
- `frontend/apps/company/src/components/layout/moduleNav.ts` (tek menü kaynağı)
- `ModuleRail.tsx` / `ContextSidebar.tsx` / `App.tsx`
- `backend/database/seeders/ModuleSeeder.php`
- `frontend/packages/shared/src/constants/modules.ts`
- `backend/database/migrations/2024_12_25_000001_create_timesheet_tables.php`
- `backend/app/Http/Controllers/Api/V1/Timesheet/AttendanceController.php`
- `backend/app/Http/Controllers/Api/V1/Portal/PortalTimesheetController.php`
- `frontend/apps/company/src/pages/attendance/AttendancePage.tsx`
- `docs/AKIS_ENVANTERI.md` §1.4 (karşılaştırma)

---

## 1. MENÜ ENVANTERİ (Company app)

### 1.1 ModuleRail — ana modül ikonları (sıra)

Veri: `operationalModuleGroups` (üst) + `pinnedModuleGroups` (alt).  
Görünürlük: `moduleKey` varsa `activeModules` (lisans) içinde olmalı; `permissionModule` varsa kullanıcıda ilgili izin; en az bir ContextSidebar öğesi görünür olmalı. `company_admin` / `super_admin` → permission bypass.

| # | id | labelKey | basePath | moduleKey (lisans) | permissionModule |
|---|-----|----------|----------|--------------------|------------------|
| 1 | `dashboard` | `nav.dashboard` | `/dashboard` | — | — |
| 2 | `employees` | `nav.employees` | `/employees` | — | `employees` |
| 3 | `recruitment` | `nav.recruitment` | `/recruitment` | `job-applications` | `recruitment` |
| 4 | `leaves` | `nav.leaves` | `/leaves` | `leave-management` | `leaves` |
| 5 | `expenses` | `nav.expenses` | `/expenses` | `expense-management` | `expenses` |
| 6 | `timesheet` | `nav.timesheet` | `/attendance` | `timesheet` | `timesheet` |
| 7 | `documents` | `nav.documents` | `/documents` | `document-management` | `documents` |
| 8 | `onboarding` | `nav.onboarding` | `/onboarding` | `onboarding` | `onboarding` |
| 9 | `performance` | `nav.performance` | `/performance` | `performance` | `performance` |
| 10 | `training` | `nav.training` | `/training` | `training` | `training` |
| 11 | `assets` | `nav.assets` | `/assets` | `asset-management` | `assets` |
| 12 | `surveys` | `nav.surveys` | `/surveys` | `surveys` | `surveys` |
| 13 | `analytics` | `nav.analytics` | `/analytics` | `hr-analytics` | `analytics` |
| 14 | `account` *(pinned)* | `nav.account` | `/account` | — | — (giriş yeterli) |
| 15 | `management` *(pinned)* | `nav.management` | — | — | `management` |

### 1.2 ContextSidebar — tüm alt menü linkleri + route

Tüm path’ler `App.tsx` içinde tanımlı → **ölü menü linki yok**.

#### dashboard
| labelKey | path | Route |
|----------|------|-------|
| `nav.dashboardOverview` | `/dashboard` | VAR |

#### employees
| labelKey | path | permission | Route |
|----------|------|------------|-------|
| `nav.employeesList` | `/employees` | `employees.list` | VAR |
| `nav.employeesDepartments` | `/employees/departments` | `employees.departments` | VAR |
| `nav.employeesPositions` | `/employees/positions` | `employees.positions` | VAR |
| `nav.employeesOrganization` | `/employees/organization` | `employees.organization` | VAR |
| `nav.employeesCustomFields` | `/employees/custom-fields` | `employees.custom_fields` | VAR |
| `nav.employeesReports` | `/employees/reports` | `employees.reports` | VAR |

#### recruitment (`job-applications`)
| labelKey | path | permission | Route |
|----------|------|------------|-------|
| `nav.recruitmentPositions` | `/recruitment/positions` | `recruitment.positions` | VAR |
| `nav.recruitmentApplications` | `/recruitment/applications` | `recruitment.applications` | VAR |
| `nav.recruitmentInterviews` | `/recruitment/interviews` | `recruitment.applications` | VAR |
| `nav.recruitmentCvPool` | `/recruitment/cv-pool` | `recruitment.cv_pool` | VAR |
| `nav.recruitmentReports` | `/recruitment/reports` | `recruitment.applications` | VAR |
| `nav.recruitmentCustomFields` | `/recruitment/custom-fields` | `recruitment.custom_fields` | VAR |

#### leaves (`leave-management`)
| labelKey | path | permission | Route |
|----------|------|------------|-------|
| `nav.leavesRequests` | `/leaves` | `leaves.requests` | VAR |
| `nav.leavesTypes` | `/leaves/types` | `leaves.types` | VAR |
| `nav.leavesBalances` | `/leaves/balances` | `leaves.balances` | VAR |
| `nav.leavesCalendar` | `/leaves/calendar` | `leaves.calendar` | VAR |
| `nav.leavesHolidays` | `/leaves/holidays` | `leaves.holidays` | VAR |
| `nav.leavesPolicies` | `/leaves/policies` | `leaves.accrual_policies` | VAR |
| `nav.leavesReports` | `/leaves/reports` | `leaves.requests` | VAR |
| `nav.leavesCustomFields` | `/leaves/custom-fields` | `leaves.custom_fields` | VAR |

#### expenses (`expense-management`)
| labelKey | path | permission | Route |
|----------|------|------------|-------|
| `nav.expensesQueue` | `/expenses` | `expenses.claims` | VAR |
| `nav.expensesAll` | `/expenses/all` | `expenses.claims` | VAR |
| `nav.expensesCategories` | `/expenses/categories` | `expenses.categories` | VAR |

#### timesheet (`timesheet`)
| labelKey | path | permission | Route |
|----------|------|------------|-------|
| `nav.timesheetAttendance` | `/attendance` | `timesheet.attendance` | VAR |

#### documents (`document-management`)
| labelKey | path | permission | Route |
|----------|------|------------|-------|
| `nav.documentsList` | `/documents` | `documents.list` | VAR |
| `nav.documentsCategories` | `/documents/categories` | `documents.categories` | VAR |
| `nav.documentsReports` | `/documents/reports` | `documents.reports` | VAR |
| `nav.documentsCustomFields` | `/documents/custom-fields` | `documents.custom_fields` | VAR |

#### onboarding
| labelKey | path | permission | Route |
|----------|------|------------|-------|
| `nav.onboardingProcesses` | `/onboarding` | `onboarding.processes` | VAR |
| `nav.onboardingTemplates` | `/onboarding/templates` | `onboarding.templates` | VAR |

#### performance
| labelKey | path | permission | Route |
|----------|------|------------|-------|
| `nav.performanceReviews` | `/performance` | `performance.reviews` | VAR |
| `nav.performancePeriods` | `/performance/periods` | `performance.periods` | VAR |
| `nav.performanceCriteria` | `/performance/criteria` | `performance.criteria` | VAR |
| `nav.performanceCustomFields` | `/performance/custom-fields` | `performance.custom_fields` | VAR |

#### training
| labelKey | path | permission | Route |
|----------|------|------------|-------|
| `nav.trainingList` | `/training` | `training.list` | VAR |
| `nav.trainingSessions` | `/training/sessions` | `training.sessions` | VAR |
| `nav.trainingCustomFields` | `/training/custom-fields` | `training.custom_fields` | VAR |

#### assets (`asset-management`)
| labelKey | path | permission | Route |
|----------|------|------------|-------|
| `nav.assetsList` | `/assets` | `assets.list` | VAR |
| `nav.assetsCategories` | `/assets/categories` | `assets.categories` | VAR |
| `nav.assetsCustomFields` | `/assets/custom-fields` | `assets.custom_fields` | VAR |

*Not: i18n’de `nav.assetsAssignments` var; menüde yok.*

#### surveys / analytics
| Modül | labelKey | path | Route |
|-------|----------|------|-------|
| surveys | `nav.surveysList` | `/surveys` | VAR |
| analytics | `nav.analyticsReports` | `/analytics` | VAR |

#### account (pinned)
| labelKey | path | Route |
|----------|------|-------|
| `account.profile` | `/account/profile` | VAR |
| `account.security` | `/account/security` | VAR |
| `account.preferences` | `/account/preferences` | VAR |

#### management (pinned) — gruplu
| Grup | labelKey | path | permission | Route |
|------|----------|------|------------|-------|
| company | `studio.companySettings` | `/settings` | `management.settings` | VAR |
| company | `studio.webhooks` | `/webhooks` | `management.webhooks` | VAR |
| users | `studio.users` | `/users` | `management.users` | VAR |
| users | `studio.roles` | `/roles` | `management.roles` | VAR |
| users | `studio.branches` | `/branches` | `management.branches` | VAR |
| users | `studio.auditLogs` | `/audit-logs` | `management.audit_logs` | VAR |
| customize | `studio.lookups` | `/lookups` | `management.lookups` | VAR |
| customize | `studio.customFields` | `/settings/custom-fields` | `management.custom_fields` | VAR |
| customize | `studio.formLayoutEmployee` | `/settings/forms/employee` | `management.forms` | VAR |
| customize | `studio.formLayoutLeave` | `/settings/forms/leave_request` | `management.forms` | VAR |
| customize | `studio.formLayoutJobApplication` | `/settings/forms/job_application` | `management.forms` | VAR |
| modules | `studio.leaveTypes` | `/leaves/types` | `leaves.types` | VAR |
| modules | `studio.documentCategories` | `/documents/categories` | `documents.categories` | VAR |
| modules | `studio.assetCategories` | `/assets/categories` | `assets.categories` | VAR |
| modules | `studio.cfEmployees` … `studio.cfAssets` | ilgili `*/custom-fields` | ilgili modül | VAR |

### 1.3 Route var, menüde yok (ulaşılamayan / detay rotaları)

Nested router yok; hepsi `App.tsx`. Menüde olmayan kayıtlı path’ler:

| Path | Tür | Not |
|------|-----|-----|
| `/login`, `/forgot-password`, `/reset-password`, `/invite/:token`, `/force-password-change` | Auth | Beklenen |
| `/careers/:companySlug/:positionSlug` | Public | Beklenen |
| `/`, `*` | Redirect → `/dashboard` | |
| `/account` | Redirect → profile | |
| `/users/:id`, `/roles/:id`, `/branches/:id` | Detail | Liste üzerinden |
| `/employees/new`, `/employees/:id`, `/employees/:id/edit` | Form/detail | |
| `/employees/form-engine/new`, `/employees/form-engine/:id/edit` | Form Engine | |
| `/leaves/form-engine/new` | Form Engine | |
| `/documents/:id` | Detail | |
| `/onboarding/processes/:id` | Detail | |
| `/performance/reviews/:id` | Detail | |
| `/assets/:id` | Detail | |

**Özet:** Sidebar’dan ulaşılamayan “gizli liste sayfası” yok; eksikler bilinçli detay/form rotaları.

### 1.4 Lisanslı modüller (seed) vs menü

| slug | ModuleSeeder? | ModuleRail? | ContextSidebar? | Not |
|------|---------------|-------------|-----------------|-----|
| `user-management` | Evet (core) | Hayır | Hayır | Users/Yönetim üzerinden |
| `company-management` | Evet (core) | Hayır | Hayır | Settings/branches |
| `audit-logs` | Evet (core) | Hayır (ayrı ikon yok) | Evet (`/audit-logs`) | Yönetim altında |
| `job-applications` | Evet | Evet | Evet | |
| `document-management` | Evet | Evet | Evet | |
| `onboarding` | Evet | Evet | Evet | |
| `leave-management` | Evet | Evet | Evet | |
| `performance` | Evet | Evet | Evet | |
| `training` | Evet | Evet | Evet | |
| `asset-management` | Evet | Evet | Evet | |
| `hr-analytics` | Evet | Evet (`analytics`) | Evet | |
| `surveys` | Evet | Evet | Evet | |
| `expense-management` | **Hayır** | Evet | Evet | `MODULE_KEYS` + menü var; **seed eksik** → lisans filtresi rail’den düşürebilir |
| `timesheet` | **Hayır** | Evet | Evet (yalnız attendance) | Aynı seed boşluğu |
| *(yok)* employees / dashboard / account / management | — | Evet | Evet | Lisans `moduleKey` yok; izin/login ile |

**Kritik:** `expense-management` ve `timesheet` FE menü + guard’da var, `ModuleSeeder`’da yok.

---

## 2. PUANTAJ / VARDİYA MEVCUT DURUM

### 2.1 `attendance_records` şeması

**Migration:** `2024_12_25_000001_create_timesheet_tables.php` (alter yok)  
**Model:** `AttendanceRecord` (`BelongsToCompany`; softDeletes yok)

| Kolon | Tip / not |
|--------|-----------|
| `id` | PK |
| `company_id` | FK companies |
| `user_id` | FK users; unique `(user_id, date)` |
| `date` | date |
| `clock_in` / `clock_out` | time, nullable |
| `break_start` / `break_end` | time, nullable |
| `total_hours` | decimal(5,2), nullable |
| `overtime_hours` | decimal(5,2), default 0 |
| `clock_in_method` / `clock_out_method` | string (manual, qr, nfc, gps, face, mobile…) |
| `clock_in_latitude` / `longitude` | decimal(10,7) |
| `clock_out_latitude` / `longitude` | decimal(10,7) |
| `clock_in_ip` / `clock_out_ip` | string, nullable |
| `status` | present / absent / late / early_leave / holiday / leave |
| `notes` | text |
| `is_approved`, `approved_by`, `approved_at` | onay |
| `created_at` / `updated_at` | timestamps |

**Yok:** late_minutes, early_leave_minutes, missing_hours, device fingerprint, geofence radius.

### 2.2 shifts + employee_shifts + work_schedules

| Varlık | Migration | Model | CRUD API | FE |
|--------|-----------|-------|----------|-----|
| `work_schedules` | ✅ | ❌ yok | ❌ | ❌ |
| `shifts` | ✅ | ✅ `Shift.php` | ❌ | ❌ |
| `employee_shifts` | ✅ | ✅ `EmployeeShift.php` | Yazma ❌; portal **GET** `/portal/timesheet/shifts` ✅ | UI yok |

**AKIS_ENVANTERI §1.4 ile güncel:** Vardiya CRUD hâlâ yok; portal vardiya API var, FE çağırmıyor — envanter notu geçerli.

İzin seed’de `timesheet.shifts.*` tanımlı; backend route bağlı değil.

### 2.3 AttendancePage (B-3)

| | |
|--|--|
| Path | `pages/attendance/AttendancePage.tsx` |
| Route | `/attendance` + `ModuleProtectedRoute(TIMESHEET)` |
| Nav | tek öğe: `nav.timesheetAttendance` |

**Gösterir:** tarih filtresi; günlük özet (present / absent / late / on_leave); tablo (personel, tarih, clock in/out, total_hours, status, onaylı mı).

**Yapar:** tekil onay, toplu onay, sayfalama.

**Yapmaz:** manuel kayıt oluşturma/düzeltme, status düzenleme, vardiya yönetimi (API client’ta create/update var; sayfa kullanmıyor).

### 2.4 Geç / erken / mesai hesabı

| Soru | Cevap |
|------|--------|
| Otomatik late / early_leave / overtime motoru? | **Yok** |
| Ne var? | `AttendanceRecord::calculateTotalHours()` = clock farkı − mola (gece +1 gün); portal/HR store-update bunu yazar |
| `status` / `overtime_hours`? | Manuel veya sabit; portal clock-in her zaman `present`, overtime yazılmaz (0) |
| `Timesheet::calculateFromAttendance()`? | Kayıtlardaki mevcut alanları toplar; kendi hesaplamaz; HR Timesheet controller yok |

**Özet:** Ham giriş-çıkış + basit süre; vardiya/schedule’a göre PDKS iş kuralı yok.

### 2.5 Manuel saat düzeltme

Ayrı “correction” endpoint **yok**. Düzeltme = HR Attendance CRUD:

| Method | Route | Permission |
|--------|-------|------------|
| `POST` | `/api/v1/attendance` | `timesheet.attendance.create` |
| `PUT` | `/api/v1/attendance/{id}` | `timesheet.attendance.edit` |

Alanlar: `clock_in/out`, `break_*`, `status`, `notes` (+ store’da `user_id`, `date`). Store’da `clock_in_method = manual`.

Company UI bu uçları **kullanmıyor** → pratikte API-only. Seed: admin’de `*`; manager’da genelde view+approve (create/edit yok).

---

## 3. PORTAL GİRİŞ-ÇIKIŞ

**Controller:** `PortalTimesheetController`  
**Rotalar:** `/api/v1/portal/timesheet/clock-in|clock-out` (+ break, today, weekly, monthly, shifts)

### Clock-in yazar
| Alan | Değer |
|------|--------|
| `clock_in` | `now()->format('H:i')` (saniye yok) |
| `clock_in_method` | sabit **`mobile`** (cihaz fingerprint yok) |
| `latitude` / `longitude` | request’ten **nullable** (zorunlu değil; geofence yok) |
| `clock_in_ip` | `$request->ip()` |
| `status` | `present` |

### Clock-out yazar
| Alan | Değer |
|------|--------|
| `clock_out` | `now()->format('H:i')` |
| `clock_out_method` | `mobile` |
| lat/lon | nullable |
| `clock_out_ip` | IP |
| `total_hours` | `calculateTotalHours()` — **overtime/status güncellenmez** |

Portal FE mümkünse Geolocation gönderir; yoksa timestamp + IP ile devam.

### Portal diğer
- Mola start/end ✅  
- Bugün / haftalık / aylık özet ✅ (late_days = kayıtta `status=late` sayımı)  
- Vardiya haftalık GET ✅ — FE bağlı değil  

---

## Özet matrisi

| Konu | Durum |
|------|--------|
| Menü ↔ route | Sidebar path’lerinin hepsi App.tsx’te |
| Seed boşluğu | `expense-management`, `timesheet` menüde var / ModuleSeeder’da yok |
| Attendance şema | Zengin (GPS, IP, overtime kolonu, status enum) |
| Attendance FE | Liste + özet + onay (B-3) |
| Vardiya / schedule CRUD | Şema (+ kısmen model); API/FE yok |
| PDKS hesap (geç/erken/mesai) | Yok — ham saat + basit süre |
| Manuel düzeltme | API var (`create`/`edit`); UI yok |
| Portal clock | Timestamp + method=mobile + opsiyonel GPS + IP |

**Sonraki iş için boşluklar (uygulama değil, teşhis):** ModuleSeeder’a `timesheet` / `expense-management`; vardiya CRUD; geç/erken/mesai motoru; AttendancePage manuel düzeltme UI; portal vardiya FE bağlama.
