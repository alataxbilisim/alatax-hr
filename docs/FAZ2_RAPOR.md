# Faz 2 — PHPUnit Factory Temeli (İlerleme Raporu)

**Branch:** `faz2-rbac-audit`  
**Başlangıç / kapanış (bu adım):** 11 Temmuz 2026  
**Kapsam:** Faz 0/1 PHPUnit borcu — factory + `users.type` hizalama; CI `continue-on-error` kaldırma.

---

## ADIM 1 — Başlangıç teşhisi (`php artisan test`, Docker pgsql)

**Özet:** `41 failed`, `3 passed` (13 assertion).

### (a) Eksik factory

| Model | Belirti | Etkilenen testler |
|-------|---------|-------------------|
| `Company` → `CompanyFactory` yok | `Class "Database\Factories\CompanyFactory" not found` | Expense / Survey / Timesheet ≈ **23** |

### (b) Enum / `users.type`

| Kaynak | Değer | Gerçek şema |
|--------|-------|-------------|
| Route*Test setUp | `type => 'employee'` | `UserType`: `super_admin \| company_admin \| user` |
| Hata | `ValueError` | ≈ **18** |

**Karar:** Enum'a `employee` **eklenmedi**. Personel = `UserType::User` + `employees` satırı. Testler hizalandı.

### (c) Diğer (factory sonrası ortaya çıkan)

| Konu | Tür | Çözüm |
|------|-----|--------|
| PortalAccess → Employee | altyapı | `EmployeeFactory` + test setUp |
| Surveys modülü | altyapı | firmaya `surveys` modülü |
| `SuperAdminOnly` / `CompanyAdminOnly` string vs enum | **gerçek bug (Faz 1 cast)** | `UserType` karşılaştırması |
| `ApiResponse::paginated` 3. arg | **gerçek bug** | metaSource desteği |
| `AttendanceRecord::calculateTotalHours` çift tarih | **gerçek bug** | `H:i` format |
| `CvPoolController` `applicant_*` kolonları | **gerçek bug** | şema: `email`/`first_name`/`last_name` |
| PortalSurvey `$request` use eksik | **gerçek bug** | closure `use ($user, $request)` |
| RateLimiter clear key | test altyapı | `md5('auth'.$ip)` (Laravel hash) |
| `Sanctum::actingAs(null)` | test | `auth()->forgetGuards()` |

---

## ADIM 2 — Eklenen factory'ler

- `CompanyFactory` — `CompanyStatus` / `CompanyPackageType`
- `EmployeeFactory` — `forUser()`, `company_id` tenant-aware
- `UserFactory` — `superAdmin()`, `companyAdmin()`, `regularUser()` + `UserType`
- Modellere `HasFactory`: `ExpenseCategory`, `ExpenseClaim`, `AttendanceRecord`
- `SurveyQuestionFactory`: `order` → `order_number`

---

## ADIM 3–4 — Sonuç skor kartı

| Metrik | Önce | Sonra |
|--------|------|-------|
| Failed | 41 | **0** |
| Passed | 3 | **43** (+1 risky: `RouteComprehensiveTest` çıktı üretir, fail değil) |
| Assertions | 13 | **173** |
| `users.type` kararı | — | testler → `user`; enum değişmedi |
| `continue-on-error` kaldırıldı mı? | hayır | **evet** (CI PHPUnit blocking) |
| CI tam yeşil mi? | — | **evet** — [run 29148350204](https://github.com/alataxbilisim/alatax-hr/actions/runs/29148350204) (Backend+Frontend+PHPUnit) |

### Commit özeti (bloklar)

1. `docs(faz2):` teşhis raporu  
2. `test(faz2):` factory'ler  
3. `test(faz2):` enum/portal hizalama  
4. `fix(faz2):` middleware enum + ApiResponse + clock hours + CvPool + survey submit  
5. `ci(faz2):` continue-on-error kaldır  

---

## Permission Enforcement Haritası (ADIM 1 — teşhis + strateji)

**Tarih:** 11 Temmuz 2026 · **Branch:** `faz2-rbac-audit`  
**Kapsam:** Harita + strateji + risk. **Route'a `permission:` EKLENMEDİ.**

### Özet teşhis

| Bulgu | Durum |
|-------|--------|
| `permission:` / `role:` middleware kullanımı (`api.php`) | **0** — Spatie alias kayıtlı, enforce yok |
| Backend wildcard (`employees.*` → `employees.list.view`) | **YOK** (`Gate::before` / custom checker yok) |
| Frontend wildcard | **VAR** (`usePermission` + `matchesPermission`) |
| Frontend `company_admin` / `super_admin` type bypass | **VAR** (izin listesine bakmadan `true`) |
| Çift kimlik | `UserType` (`super_admin\|company_admin\|user`) **ve** Spatie rol (`admin\|hr_manager\|…\|employee`) |

---

### 1) Mevcut middleware katmanları (`/api/v1`)

#### Public (permission yok — DOKUNULMAYACAK)

| Grup | Route örnekleri | Middleware |
|------|-----------------|------------|
| Auth public | `POST auth/login\|register\|forgot-password\|reset-password` | `throttle:auth` |
| Kariyer public | `GET public/companies/{slug}/jobs`, `GET/POST public/jobs/…` | `throttle:public` |
| Health | `GET /up` (web) | Laravel health |

#### SuperAdmin

| Grup | Middleware | Permission? |
|------|------------|-------------|
| `admin/*` (companies, ledger, packages, modules, users, logs, dashboard) | `auth:sanctum` + `super_admin` | Yok (type bypass yeterli — öneri: Spatie permission **zorunlu değil**) |

#### Company (iç) — dış sarmalayıcı: `auth:sanctum` + `company.active`

| Route grubu | Ek middleware ŞU AN | Rol/izin? |
|-------------|---------------------|-----------|
| `auth/logout\|me\|profile\|password` | — | Yok (self — OK) |
| `GET /dashboard` | — | **Açık kapı** |
| `users/*`, `roles/*`, `permissions` | `company_admin` | Type only |
| `webhooks/*`, `company/*`, `api-keys/*`, `branches/*` | `company_admin` | Type only |
| `employees/*` (+ reports, dashboards, docs) | `company_admin` | Type only |
| `employee-documents/*`, `departments/*`, `custom-fields/*` | `company_admin` | Type only |
| `workflows/*` | `company_admin` | Type only |
| `activity-logs/*` (+ export) | — | **Açık kapı — kritik** |
| `notifications/*` | — | Self — düşük risk |
| `approvals/*` (+ delegations) | — | **Açık kapı — orta** |
| `attendance/*` (HR puantaj) | — | **Açık kapı — kritik** |
| `recruitment/*` | `module.access:job-applications` | Lisans only — **açık** |
| `documents/*` | `module.access:document-management` | Lisans only — **açık** |
| `onboarding/*` | `module.access:onboarding` | Lisans only — **açık** |
| `leaves/*` | `module.access:leave-management` | Lisans only — **açık** |
| `performance/*` | `module.access:performance` | Lisans only — **açık** |
| `training/*` | `module.access:training` | Lisans only — **açık** |
| `assets/*` | `module.access:asset-management` | Lisans only — **açık** |
| `surveys/*` | `module.access:surveys` | Lisans only — **açık** |
| `analytics/*` | `module.access:hr-analytics` | Lisans only — **açık** |

#### Portal — `auth:sanctum` + `company.active` + `portal.access`

| Alt grup | Ek | Not |
|----------|-----|-----|
| dashboard, profile, leaves, documents, payslips, announcements, requests, timesheet, expenses | — | Self-servis; Spatie yok |
| portal/training, performance, surveys | `module.access:*` | Lisans + portal |

**Açık kapı tanımı:** Authenticated company kullanıcısı (`UserType::user` dahil) için ne `company_admin` ne `permission:` — sadece lisans veya hiçbiri. En kritik: **modül route'ları** (lisanslı herkes HR API'sini çağırabilir) + **attendance** + **activity-logs**.

---

### 2) PermissionSeeder envanteri

#### Hiyerarşik (`{module}.{page}.{action}` + wildcard)

| Module | Pages (actions) |
|--------|-----------------|
| `management` | users, roles, branches, audit_logs, settings, webhooks, api_keys |
| `employees` | list, departments, organization, custom_fields, reports, documents |
| `recruitment` | positions, applications, cv_pool, custom_fields |
| `leaves` | requests, types, balances, calendar, holidays, accrual_policies, custom_fields |
| `documents` | list, categories, custom_fields |
| `onboarding` | processes, templates |
| `performance` | reviews, periods, criteria, okr, feedback, competencies, one_on_one, custom_fields |
| `training` | list, sessions, custom_fields |
| `assets` | list, categories, assignments, maintenance, custom_fields |
| `surveys` | list |
| `analytics` | reports |
| `timesheet` | attendance, shifts |
| `expenses` | claims, categories |

Her page için ayrıca `{module}.*` ve `{module}.{page}.*` seed edilir.

#### Legacy (eski düz format — hâlâ seed)

`users.*`, `roles.*`, `employees.*`, `branches.*`, `company.*`, `settings.*`, `job-positions.*`, `applications.*`, `cv-pool.*`, `recruitment.*`, `documents.*`, `onboarding.*`, `leaves.*`, `leave-types.manage`, `trainings.*`, `performance.*`, `assets.*`, `reports.*`, `logs.view`, `audit.*` …

#### Rol → izin (özet)

| Spatie rol | Kapsam |
|------------|--------|
| `admin` | `$allPermissions` (hiyerarşik + legacy) |
| `hr_manager` | Çoğu modül `.*` veya geniş CRUD; management kısmi |
| `hr_specialist` | Personel/ise alım/izin/eğitim ağır; delete/approve sınırlı |
| `manager` | Team view + leave approve + performance create |
| `employee` | Dar: kendi leave create, view documents/training/performance |

**Not:** `UserType::company_admin` ≠ Spatie `admin` rolü otomatik. Frontend type bypass ile admin gibi davranır; backend `company_admin` middleware type'a bakar. Spatie rol ataması ayrı.

---

### 3) Eşleme stratejisi (öneri — henüz uygulanmadı)

#### Katman modeli (net)

```
1) auth:sanctum          → kimlik
2) company.active        → firma hesabı
3) module.access:{slug}  → lisans (firma modülü satın aldı mı?)
4) permission:{m.p.a}    → rol (kullanıcı bu aksiyona yetkili mi?)
5) BelongsToCompany      → veri kapsamı (tenant satır filtresi)
```

`module.access` **ve** `permission` **birlikte** — ikisi de gerekli. Biri diğerinin yerine geçmez.

#### `company_admin` / `super_admin` ilişkisi

| Type | Öneri |
|------|--------|
| `super_admin` | Permission bypass (platform); `admin/*` type ile kalır |
| `company_admin` | **Kısa vade:** `company_admin` middleware kalsın + permission eklensin; Gate'te type bypass (frontend ile aynı). **Uzun vade:** sadece Spatie `admin` + permission, type sadeleşsin |
| `user` | Sadece permission (+ portal.access self) |

#### Route grubu → önerilen permission

| # | Route grubu | Mevcut MW | Önerilen permission (CRUD örneği) | Risk | Sıra |
|---|-------------|-----------|-----------------------------------|------|------|
| P0 | PUBLIC auth + public/jobs + /up | throttle | **YOK — dokunma** | — | — |
| P0 | `admin/*` | super_admin | Spatie yok / opsiyonel `platform.*` | Düşük | Son |
| 1 | `activity-logs` | auth only | `management.audit_logs.view` (+ export) | **Kritik** | **1** |
| 2 | `attendance` (HR) | auth only | `timesheet.attendance.{view,create,edit,approve}` | **Kritik** | **1** |
| 3 | `approvals/*` | auth only | Yeni: `workflows.approvals.{view,approve}` **veya** self-only bırak | Orta | 2 |
| 4 | `recruitment/*` | module | `recruitment.positions\|applications\|cv_pool.*` | Yüksek | 2 |
| 5 | `leaves/*` (company) | module | `leaves.requests\|types\|…` | Yüksek | 2 |
| 6 | `documents/*` | module | `documents.list\|categories.*` | Yüksek | 3 |
| 7 | `onboarding/*` | module | `onboarding.processes\|templates.*` | Orta | 3 |
| 8 | `performance/*` | module | `performance.{reviews,periods,…}.*` | Orta | 3 |
| 9 | `training/*` | module | `training.list\|sessions.*` | Orta | 4 |
| 10 | `assets/*` | module | `assets.list\|categories\|…` | Orta | 4 |
| 11 | `surveys/*` | module | `surveys.list.*` | Orta | 4 |
| 12 | `analytics/*` | module | `analytics.reports.view\|export` | Orta | 4 |
| 13 | `users\|roles\|…` (company_admin) | company_admin | `management.{users,roles,…}.*` (+ type bypass) | Orta | 5 |
| 14 | `employees/*` | company_admin | `employees.{list,departments,…}.*` | Orta | 5 |
| 15 | `workflows/*` | company_admin | **Eksik seed** → `workflows.definitions.*` ekle | Orta | 5 (seed önce) |
| 16 | `dashboard` | auth | Soft: authenticated OK **veya** herhangi bir modül view | Düşük | 6 |
| 17 | `notifications` | auth | Soft: self — permission yok | Düşük | — |
| 18 | `auth/me` vb. | auth | Soft: self — dokunma | — | — |
| 19 | `portal/*` | portal.access | **Öneri A:** permission yok (self). **Öneri B:** `portal.*` dar set | Karar | Sonra |

---

### 4) Eksik izinler (seed'e enforcement öncesi eklenmeli)

| Eksik | Gerekçe |
|-------|---------|
| `workflows.definitions.{view,create,edit,delete}` | `workflows/*` route'ları var, seed yok |
| `workflows.approvals.{view,approve}` (veya eşdeğeri) | `approvals/*` — self mi yoksa yetki mi? |
| `recruitment.interviews.*`, `recruitment.forms.*`, `recruitment.reports.view` | Route var; seeder'da page yok |
| `documents.reports.view` | Documents report/KPI endpoint'leri |
| `management.company.view\|edit` (veya settings altında netleştir) | `company/*` show/update |
| `timesheet.shifts.*` kullanımı | Seed var; company UI route net değil |
| Platform `super_admin` izinleri | İsteğe bağlı; şimdilik type yeterli |

---

### 5) Risk haritası — enforcement açılınca ne kilitlenir?

| Senaryo | Etki |
|---------|------|
| `UserType::user` + modül lisanslı, Spatie `employee` | Bugün: recruitment/leaves API **açık**. Sonra: **403** (doğru). Portal ayrı kalmalı. |
| `company_admin` type, Spatie rol **atanmamış** | Frontend bypass ile UI açık; backend `permission:` **strict** olursa **kilitlenir**. → Gate type-bypass şart veya seed'de otomatik `admin` rolü. |
| Rolde sadece `employees.*` wildcard | Frontend OK; **Spatie ham middleware FAIL** (wildcard expand yok). → Backend matcher **zorunlu**. |
| `hr_manager` attendance izni yok | Attendance route'ları kilitlenir — seed'e `timesheet.*` eklenmeli. |
| Test suite `RouteAuthorizationTest` | `company_admin` type ile çalışıyor; permission middleware sonrası factory'ye Spatie rol + izin sync gerekir. |

---

### 6) Test stratejisi

Mevcut: `RouteAuthorizationTest` / `RouteComprehensiveTest` (type + module).  
Hedef matris (her grup için örnek endpoint):

| Case | Beklenen |
|------|----------|
| Token yok | 401 |
| Auth var, permission yok (ve type bypass yok) | 403 |
| Auth + doğru permission (+ module varsa lisans) | 200/201 |
| Başka `company_id` kaydı | 404/403 (tenant scope) |
| Modül kapalı, permission var | 403 (`module.access`) |

`RouteComprehensiveTest`: `allowedUsers` yerine **permission matrisi** (`role` veya explicit permission set). Feature test: `actingAs` + `$user->givePermissionTo(...)`.

---

### 7) Önerilen uygulama sırası (kademeli — big-bang yok)

0. **Önkoşul:** Backend wildcard matcher (`matchesPermission` ile aynı mantık) + `company_admin`/`super_admin` Gate bypass kararı  
1. **Dalga 1 (kritik açık kapı):** `activity-logs`, `attendance` — seed gap yok/az  
2. **Dalga 2 (modül, yüksek trafik):** `recruitment`, `leaves` — seed eksikleri doldur  
3. **Dalva 3:** `documents`, `onboarding`, `performance`  
4. **Dalga 4:** `training`, `assets`, `surveys`, `analytics`  
5. **Dalga 5:** `company_admin` gruplarına permission ekle (type bypass ile güvenli geçiş)  
6. **Dalga 6:** portal kararı + dashboard soft  
7. Her dalgada: PermissionSeeder güncelle → route MW → feature test → CI yeşil

---

### Karar noktaları — uygulama öncesi cevaplanmalı

1. **Wildcard backend:** Spatie `permission:` yerine custom `permission.hier:` mi, yoksa `Gate::before` ile expand mi? (Frontend parity şart.)
2. **`company_admin` type bypass backend'de kalacak mı?** (Öneri: evet, en azından Dalga 5'e kadar — aksi halde rolü olmayan admin'ler kilitlenir.)
3. **Portal:** permission'sız self mi, yoksa `portal.leaves.create` gibi ayrı izinler mi?
4. **`approvals/*`:** herkes kendi kuyruğunu mu görür (permission soft), yoksa `workflows.approvals.view` mi?
5. **Legacy permission'lar:** enforcement'ta yok sayılıp sadece hiyerarşik mi kullanılacak? (Öneri: sadece hiyerarşik; legacy seed'de dursun, middleware'e yazma.)
6. **`UserType::user` + Spatie `hr_manager`:** mümkün mü? (İzin middleware sonra type `company_admin` olmadan HR API açılır — istenen mi?)

**Bu adımda route middleware EKLENMEDİ.** Onay + kararlar sonrası Dalga 0 (matcher) → Dalga 1.

---

## Permission Enforcement — Dalga 0 + 1 (UYGULANDI)

**Tarih:** 11 Temmuz 2026 · **Kararlar kilitlendi** (Gate::before, company_admin bypass geçici, portal/approvals dokunulmadı, sadece hiyerarşik).

### Dalga 0 — Altyapı

| Madde | Durum |
|-------|--------|
| `HierarchicalPermission` matcher (tam / `page.*` / `module.*` / `*`) | ✅ `app/Support/HierarchicalPermission.php` |
| `Gate::before` (AppServiceProvider) | ✅ super_admin + company_admin bypass; wildcard expand |
| `User::$guard_name = 'sanctum'` | ✅ PermissionSeeder ile hizalı (aksi halde GuardDoesNotMatch) |
| Unit: `HierarchicalPermissionGateTest` | ✅ bypass + wildcard + deny |

### Dalga 1 — Açık kapı kapanışı

| Route grubu | Middleware | Seed izin |
|-------------|------------|-----------|
| `GET activity-logs`, `GET activity-logs/{id}` | `permission:management.audit_logs.view` | Seeder'da var (`audit_logs` underscore — hyphen değil) |
| `GET activity-logs/export` | `permission:management.audit_logs.export` | var |
| `attendance` GET/POST/PUT/approve | `timesheet.attendance.{view,create,edit,approve}` | var |

Portal / approvals / diğer modüller: **dokunulmadı**.

### Test kanıtı

| Kanıt | Sonuç |
|-------|--------|
| `UserType::user` + izin yok → activity-logs/attendance **403** | ✅ |
| İzinli user / wildcard → **200** | ✅ |
| super_admin / company_admin bypass → **200** | ✅ |
| Tenant izolasyonu (başka firma satırı listede yok) | ✅ |
| Tam suite | **62 passed**, 1 risky, 0 failed |

Mevcut `RouteAuthorizationTest::test_activity_log_routes` company_admin ile → bypass sayesinde hâlâ 200 (doğru).

---

## Permission Enforcement — Dalga 2 (UYGULANDI)

**Tarih:** 11 Temmuz 2026 · **Kapsam:** `recruitment`, `leaves`, `documents` (+ `public/jobs` korundu).

### Seed teyidi — eklenen izinler

| Modül | Eklenen sayfa/aksiyon | Not |
|-------|----------------------|-----|
| recruitment | `interviews.{view,create,edit,delete}` | Mülakat CRUD + complete/cancel→edit |
| recruitment | `reports.{view,export}` | Rapor endpoint'leri |
| recruitment | `forms.{view,create,edit,delete}` | Form builder |
| recruitment | `cv_pool.edit` | bulk-tag / rate / removeTag |
| documents | `reports.{view,export}` | Doküman raporları |
| leaves | — | Seed zaten yeterli (`types`, `requests`, `balances`, `calendar`, `holidays`, `accrual_policies`) |

`hr_specialist` rolüne yeni view/edit izinleri de eklendi (interviews, forms, reports, cv_pool.edit, documents.reports, holidays, accrual_policies).

Modül slug'ları (değişmedi): `job-applications` / `leave-management` / `document-management`.

### Enforce edilen route grupları

| Grup | Katman | Örnek permission |
|------|--------|------------------|
| `recruitment/*` | `module.access:job-applications` + `permission:recruitment.{page}.{action}` | positions.view/create/edit/delete; applications.view/edit/approve; cv_pool.view/edit; interviews.*; reports.view; forms.* |
| `leaves/*` | `module.access:leave-management` + `permission:leaves.{page}.{action}` | types.*; requests.view/create/approve; calendar.view; balances.view; holidays.*; accrual_policies.* |
| `documents/*` (+ `/documents` CRUD) | `module.access:document-management` + `permission:documents.{page}.{action}` | categories.*; list.*; reports.view |
| `public/jobs/*` | **permission yok** | auth'suz 200 korundu |

### Yan düzeltmeler (testte ortaya çıkan gerçek bug)

| Bug | Ayırım | Düzeltme |
|-----|--------|----------|
| Public JobController `status=published` + kolon `deadline` | şema uyumsuzluğu → 500 | `active` + `application_deadline` |
| LeaveRequest approve/reject `status !== STATUS_PENDING` (string) | enum cast → karşılaştırma bozuk | `LeaveRequestStatus::Pending` |

`leaves/requests` PUT/PATCH/DELETE route'ları kaldırıldı (controller'da update/destroy yoktu; apiResource kalıntısı).

### Test kanıtı

| Kanıt | Sonuç |
|-------|--------|
| auth yok → **401** (3 grup) | ✅ |
| lisanssız firma (module.access yok) → **403** | ✅ |
| `UserType::user` + izin yok → **403** (3 grup) | ✅ |
| doğru Spatie izin → **200** | ✅ |
| super_admin / company_admin bypass → **200** | ✅ |
| tenant izolasyonu | ✅ |
| public/jobs auth'suz → **200** | ✅ |
| Mevcut RouteAuthorization recruitment/leaves/documents | ✅ (company_admin bypass) |
| Tam suite | **83 passed**, 1 risky, 0 failed |
| CI (`faz2-rbac-audit`) | ✅ https://github.com/alataxbilisim/alatax-hr/actions/runs/29149666669 |

Dosya: `tests/Feature/PermissionEnforcementWave2Test.php`

---

## Permission Enforcement — Dalga 3 (UYGULANDI)

**Tarih:** 11 Temmuz 2026 · **Kapsam:** onboarding, performance, training, assets, surveys, analytics. Portal route'lar (**dokunulmadı**).

### Seed teyidi — eklenen izinler

| Modül | Değişiklik | Not |
|-------|------------|-----|
| performance | `feedback.edit` eklendi | decline / addProviders |
| onboarding, training, assets, surveys, analytics | sayfa seti zaten yeterli | — |

Rol atamaları:
- **hr_manager:** `training.*`, `performance.*`, `assets.*`, `surveys.*`, `analytics.*` (önceki kısmi listeler yerine)
- **hr_specialist:** assets/surveys/analytics view + performance periods/criteria/feedback.view
- **manager:** `performance.feedback.edit`, `performance.okr.view`

Modül slug'ları: `onboarding` / `performance` / `training` / `asset-management` / `surveys` / `hr-analytics`.

### Enforce edilen route grupları

| Grup | Katman |
|------|--------|
| `onboarding/*` | module + `onboarding.{templates\|processes}.*` |
| `performance/*` | module + periods/criteria/reviews/okr/feedback/competencies/one_on_one |
| `training/*` | module + `training.list.*` + `training.sessions.*` |
| `assets/*` | module + categories/list/assignments/maintenance |
| `surveys/*` | module + `surveys.list.*` |
| `analytics/*` | module + `analytics.reports.view` |
| `portal/training|performance|surveys` | **permission yok** (KARAR 3) |

### Yan düzeltmeler (gerçek bug)

| Bug | Ayırım | Düzeltme |
|-----|--------|----------|
| 6 model `use App\Models\Traits\BelongsToCompany` (trait yok) | class load → fatal | `App\Traits\BelongsToCompany` |

### Test kanıtı

| Kanıt | Sonuç |
|-------|--------|
| 6 grup: 401 / lisanssız 403 / izinsiz user 403 / izinli 200 / bypass 200 | ✅ |
| Tenant izolasyonu (onboarding…surveys + analytics company scope) | ✅ |
| SurveyTest (company_admin bypass) | ✅ kırılmadı |
| Tam suite | **95 passed**, 1 risky, 0 failed |
| CI (`faz2-rbac-audit`) | ✅ https://github.com/alataxbilisim/alatax-hr/actions/runs/29150149532 |

Dosya: `tests/Feature/PermissionEnforcementWave3Test.php`

---

## Permission Enforcement — Dalga 4 SON (UYGULANDI)

**Tarih:** 11 Temmuz 2026 · **Kapsam:** admin grupları (users, roles, company, branches, api-keys, webhooks, custom-fields, workflows, employees, departments).

### Seed — eklenenler

| İzin sayfası | Aksiyonlar |
|--------------|------------|
| `management.company` | view, edit |
| `management.workflows` | view, create, edit, delete |
| `management.custom_fields` | view, create, edit, delete |

`admin` rolü = `$allPermissions` (full). `hr_manager`: company/settings/custom_fields + employees.* — **api_keys / webhooks / workflows yok**.

### Uygulama notu — company_admin soft-pass

`CompanyAdminOnly` UserType::user'ı artık engellemez (Dalga 4 geçiş). Asıl kapı `permission:`.  
company_admin / super_admin type → Gate::before bypass.  
Böylece **UserType::user + hr_manager Spatie rolü → 200** (type-only değil).

### Enforce

Tüm admin gruplarında `company_admin` **kaldırılmadı** + `permission:` eklendi.

| Grup | Permission |
|------|------------|
| users | management.users.* |
| roles / permissions | management.roles.* |
| company / settings | management.company.* / settings.* |
| branches | management.branches.* |
| api-keys | management.api_keys.* |
| webhooks | management.webhooks.* |
| custom-fields | management.custom_fields.* |
| workflows | management.workflows.* |
| employees (+ reports/dashboards/docs) | employees.{list,reports,documents,custom_fields,organization}.* |
| departments | employees.departments.* |

### `permission:` sayımı

| Önce (Dalga 0 öncesi) | Şimdi |
|----------------------|--------|
| **0** | **~343** `permission:` kullanımı (`routes/api.php`) |

### Bilinçli permission-dışı kalanlar (açık kapı DEĞİL / kararlı istisna)

| Route | Neden |
|-------|--------|
| `auth/*` (login, me, profile…) | public / self |
| `public/jobs/*` | public başvuru |
| `portal/*` | KARAR 3 — portal.access + self |
| `approvals/*` | KARAR 4 — self kuyruk |
| `notifications/*` | self bildirim |
| `GET /dashboard` | auth’lu ana sayfa (ince permission yok — soft) |
| `admin/*` (superadmin platform) | `super_admin` type middleware |

### Test kanıtı

| Kanıt | Sonuç |
|-------|--------|
| izinsiz user → **403** (admin grupları) | ✅ |
| **UserType::user + hr_manager → 200** (users/employees…); api-keys/webhooks/workflows → **403** | ✅ kritik |
| company_admin Spatie’siz → **200** (bypass) | ✅ |
| Tam suite | **103 passed**, 1 risky |
| CI (`faz2-rbac-audit`) | ✅ https://github.com/alataxbilisim/alatax-hr/actions/runs/29150827816 |

Dosya: `tests/Feature/PermissionEnforcementWave4Test.php`

### ENFORCEMENT TAMAMLANDI

Modül + admin route grupları permission-korumalı. Kalanlar yukarıdaki bilinçli istisnalar.

---

## Policy + Data Scope Haritası (ADIM 1 — teşhis + strateji)

**Tarih:** 11 Temmuz 2026 · **Uygulama yok** — harita + kararlar.

### Teşhis özeti

| Katman | Durum |
|--------|--------|
| Route permission (endpoint) | ✅ Dalga 0–4 |
| Tenant (`BelongsToCompany` / `company_id`) | ✅ firma izolasyonu |
| Laravel Policy (`app/Policies`) | ❌ **0 sınıf** |
| `$this->authorize()` / model Policy | ❌ yok |
| Kayıt seviyesi (own/team/department) | ❌ **yok** (nadir manuel filtreler hariç) |

**Sonuç:** İzni olan kullanıcı, firma içindeki **tüm** ilgili kayıtlara erişebilir. Örnek: `leaves.requests.approve` → herhangi bir pending talebi onaylar (ekip kontrolü yok).

### İlişki altyapısı (kapsam hesabı için)

| Kaynak | Durum | Not |
|--------|--------|-----|
| `Employee.manager_id` → `employees` (self-ref) | ✅ | `manager()` / `subordinates()` var; **hiçbir controller enforce etmiyor** |
| `Department.manager_id` | ⚠️ tutarsız | Migration/Model → `users`; Controller validasyon → `exists:employees,id` |
| `Branch.manager_id` → `users` | ✅ | branch scope için kullanılabilir |
| Spatie `roles` tablosu `scope` kolonu | ❌ | standart Spatie; `teams=false` |

### Model → aksiyon → kapsam → mevcut durum → öneri

| Model / yüzey | Aksiyon | Önerilen kapsam | Mevcut | Öncelik |
|----------------|---------|-----------------|--------|---------|
| **LeaveRequest** | `view` (index/show) | own / team / company | Tenant + permission; **tüm firma listesi** | P0 |
| **LeaveRequest** | `approve` / `reject` | team (manager) veya workflow ataması; company (hr) | Sadece `pending` + permission; **manager kontrolü YOK**. `cancel` → own (manuel) | P0 |
| **LeaveRequest** | Portal | own | ✅ `user_id` filtresi | — |
| **Employee** | `view` list/show | team / department / company | Tüm firma + filtreler | P1 |
| **Employee** | `update` / `delete` | company (HR); manager sınırlı edit? | Permission only | P1 / karar |
| **Department** | view/update | department (yöneticisi) / company | Permission only; manager_id tutarsız | P2 |
| **PerformanceReview** | view | own (çalışan) / reviewer / company | Admin: tüm firma; Portal: own ✅ | P1 |
| **PerformanceReview** | approve | reviewer veya company | Permission only; `reviewer_id` zorunlu değil | P1 |
| **OneOnOne** | CRUD | own (manager veya employee) | ✅ kısmen manuel (`manager_id` / `employee_id`) | referans desen |
| **Document** (şirket) | view/download | company (+ ileride visibility) | Tüm firma (izinli) | P2 |
| **EmployeeDocument** | portal | own + `visibleToEmployee` | ✅ | — |
| **ExpenseClaim** | view/update | own (portal); approve → team/company | Portal own ✅; **HR admin API yok**; modelde `BelongsToCompany` **YOK** | P2 + bug |
| **ApprovalRecord** | approve/reject | atanan onaycı / vekil | ✅ `WorkflowService::canApprove` | koru |
| **ApprovalRecord** | pending/history | own | ✅ | koru |
| **ApprovalRecord** | `getApprovalHistory` / `delegations` | company veya tighten | Geniş yüzey (company_id only) | P2 |
| **JobApplication / Recruitment** | view | company (HR) | Permission + tenant | düşük (genelde company) |

### İki onay dünyası (önemli)

1. **Legacy:** `LeaveRequestController::approve` — permission + status; **atama/ekip yok**.
2. **Workflow:** `approvals/*` + `WorkflowService::canApprove` — **atanan approver / vekalet**.

Policy tasarımında: workflow kayıtları için mevcut `canApprove` korunmalı; legacy leave approve ya Policy (`team`) ile kısıtlanmalı ya da workflow’a taşınmalı (**karar noktası**).

### Data scope modeli (öneri)

**Seviyeler:** `own` → `team` → `department` → `branch` → `company` (üst küme altı kapsar).

**Katmanlama:**

```
SuperAdmin (tüm tenant'lar)
  └─ BelongsToCompany (firma)
       └─ DataScope (own/team/department/branch/company)  ← YENİ
            └─ Policy (tekil kayıt) + Query scope (liste)
```

**Öneri mimari (uygulama yok):**

| Parça | Rol |
|-------|-----|
| Laravel **Policy** | `view` / `update` / `delete` / `approve` tekil kayıt |
| **Query scope / ScopeService** | `index` — sadece kapsamdaki ID’ler |
| `BelongsToCompany` | değişmez; DataScope onun altında |

### Scope nasıl saklanır? — KARAR GEREKLİ

| Seçenek | Artı | Eksi |
|---------|------|------|
| **A) `roles.data_scope` kolonu** (string enum) | Basit; rol = kapsam | Aynı rol farklı firmada farklı scope istenirse zor; migration |
| **B) Config map** (`config/data_scope.php`: `hr_manager => company`) | Kod review kolay; hızlı deneme | DB’den yönetilmez; tenant özelleştirmesi yok |
| **C) Permission suffix** (`leaves.requests.approve.team`) | Çok esnek | Permission patlaması; frontend/RBAC karmaşık |
| **D) Pivot `role_company` / company_user settings** | Multi-tenant özelleştirme | Erken aşama için ağır |

**Öneri (rapor):** **A + B hibrit** — varsayılanlar config’te; Spatie role’a nullable `data_scope` kolonu (yoksa config fallback). Permission’a scope gömmeyelim (C).

Örnek varsayılan map:

| Rol | Scope |
|-----|-------|
| `employee` | own |
| `manager` | team |
| `hr_specialist` | company (veya department — karar) |
| `hr_manager` / `admin` | company |
| `company_admin` type | company (bypass dönemi) |

### Uygulama sırası (onay sonrası)

1. **Kararlar kilitlensin** (aşağıdaki sorular).
2. **Altyapı:** `DataScope` enum + resolver (`User` → scope) + `scopesFor(User)` (employee ID listesi).
3. **P0 — LeaveRequestPolicy** + index query filter + approve Policy; feature test: manager kendi ekibi 200 / başka ekip 403 / hr_manager company 200.
4. **P1 — EmployeePolicy** (view scope) + PerformanceReviewPolicy.
5. **P2 — Document visibility, ExpenseClaim BelongsToCompany + approve, Approval history tighten, Department.manager_id düzeltmesi.**
6. Gate::before `company_admin` bypass’ın Policy’ye etkisi netleştirilsin (şu an tüm Gate ability true — Policy’yi de bypass eder).

### Test stratejisi (LeaveRequest P0)

| Senaryo | Beklenen |
|---------|----------|
| manager, kendi `subordinates` leave → view/approve | 200 |
| manager, başka ekip leave → view/approve | 403 |
| hr_manager / company scope → tüm firma | 200 |
| employee, başkasının leave show | 403 |
| auth yok | 401 |
| tenant dışı kayıt | 404/boş (BelongsToCompany) |

### Bilinen teknik borç (Policy dalgasında işaretle)

- `Department.manager_id`: User vs Employee tutarsızlığı.
- `ExpenseClaim`: `BelongsToCompany` eksik.
- Leave `myRequests` / `pendingApprovals` controller metotları route’suz (ölü kod).
- Legacy leave approve vs Workflow çakışması.

---

### KARAR NOKTALARI (uygulama öncesi cevap bekleniyor)

1. **Scope saklama:** A (rol kolonu) / B (config) / A+B hibrit / başka?
2. **İlk model:** LeaveRequest (P0) mi, Employee (P1) mi?
3. **Leave approve:** Policy ile `team` mi, yoksa sadece Workflow `canApprove` mi (legacy approve kapatılsın mı)?
4. **`hr_specialist` varsayılan scope:** `company` mi `department` mi?
5. **Gate::before company_admin bypass:** Policy’lerde de geçerli kalsın mı (geçici), yoksa Policy’de kayıt seviyesi admin’e de mi uygulansın?
6. **Manager tanımı:** yalnızca `Employee.manager_id` zinciri mi, yoksa `Department.manager_id` da “department scope” için mi (önce User/Employee tutarsızlığı düzeltilsin mi)?

---

## Policy + Data Scope UYGULAMA — Dalga 1 (LeaveRequest)

**Tarih:** 11 Temmuz 2026 · **Branch:** `faz2-rbac-audit`  
**Kapsam:** Adım 0 (manager) + DataScope altyapısı + LeaveRequestPolicy. Diğer modeller sonraki dalgalar.

### Kilitlenen kararlar

| # | Karar |
|---|--------|
| 1 | Scope: **A+B hibrit** — `config/data-scope.php` defaults + `roles.data_scope` override |
| 2 | İlk model: **LeaveRequest** |
| 3 | Approve: Policy **team** + Workflow `canApprove`; legacy serbest approve **KAPALI** |
| 4 | Defaults: admin/hr_manager=`company`, hr_specialist=`department`, manager=`team`, employee=`own` |
| 5 | `company_admin` bypass **Gate::before**’da kalır (Policy’de ayrı bypass yok) |
| 6 | Team = **Employee.manager_id** subordinates |

### Adım 0 — Manager ilişkisi

| Kaynak | Şema (gerçek) | Kullanım |
|--------|---------------|----------|
| `Employee.manager_id` | → `employees` (self-ref) | **team scope** kaynağı |
| `Department.manager_id` | → `users` | Departman yöneticisi User; **team hesabına girmez** |

**Tutarsızlık:** Controller `exists:employees,id` + Employee lookup yazıyordu; FK `users`.  
**Düzeltme (küçük):** validation → `exists:users,id`; index/show User eager load. FK değiştirilmedi (büyük şema yok).

**Tanımlar:**
- **team** = actor’ün `Employee` kaydı → `subordinates` → onların `user_id` (+ kendi)
- **department** = aynı `Employee.department_id` personellerinin `user_id`
- **branch** = CHECK’te var; Employee’de `branch_id` yok → LeaveRequest için boş küme (ileride)

### Adım 1 — DataScope altyapısı

| Parça | Dosya |
|-------|--------|
| Enum | `app/Enums/DataScopeLevel.php` (`own\|team\|department\|branch\|company`) |
| Config | `config/data-scope.php` |
| Migration | `2026_07_11_000001_add_data_scope_to_roles_table.php` (nullable string + CHECK) |
| Servis | `app/Services/DataScopeService.php` — `resolve`, `scopeForUser`, `allowsUserId`, `isDirectSubordinate` |
| Unit | `tests/Unit/Services/DataScopeServiceTest.php` (8 test) |

Çoklu rol → **en geniş** kapsam. Rol yok → `own`.

### Adım 2 — LeaveRequest Policy

| Parça | Dosya |
|-------|--------|
| Policy | `app/Policies/LeaveRequestPolicy.php` — viewAny/view/update/delete/approve |
| Controller | `authorize()` + index `DataScope::scopeForUser`; reject de `approve` |
| Workflow | `WorkflowService::canApprove` **public** |
| BaseController | `AuthorizesRequests` trait |

**approve kuralı:**
1. `company` scope → evet
2. Aktif workflow `ApprovalRecord` varsa → `canApprove`
3. Yoksa: `team` → doğrudan subordinate; `department` → aynı dept (kendi hariç)
4. Aksi → **hayır** (legacy kapalı)

### Test sonuçları

| Suite | Sonuç |
|-------|--------|
| DataScope unit | 8 passed |
| LeaveRequestPolicyDataScope feature | 7 passed (manager başka ekip **403** kritik) |
| Tam `php artisan test` | **118 passed**, 1 risky (`RouteComprehensiveTest`) |

Kritik kanıt: `test_manager_cannot_see_or_approve_other_team_leave` → liste yok + show/approve **403**.  
Kritik kanıt: `test_permission_alone_without_team_cannot_approve_legacy_closed` → sadece permission ile onay **403**.

### Sonraki dalgalar (Dalga 1 sonrası)

Employee, ExpenseClaim (`BelongsToCompany`), PerformanceReview, Document visibility…

---

## Policy + Data Scope UYGULAMA — Dalga 2 (Employee, ExpenseClaim, PerformanceReview)

**Tarih:** 11 Temmuz 2026 · **Branch:** `faz2-rbac-audit`  
**Kapsam:** 3 model Policy + DataScope; HR ExpenseClaim API eklendi.

### Kapsam kararları

| Model | view | update/delete | approve |
|-------|------|---------------|---------|
| **Employee** | own / team (+kendisi) / department / company | **yalnızca company veya department** (İK) — manager team ile **görür, düzenleyemez** | — |
| **ExpenseClaim** | own / team / department / company (`user_id`) | sahibi + **draft** only | LeaveRequest deseni (workflow \| team \| company); legacy **KAPALI** |
| **PerformanceReview** | reviewee **veya** reviewer **veya** DataScope(reviewee) | **yalnızca reviewer** (approved hariç) | company \| team(subordinate reviewee) \| department |

### PerformanceReview şema doğrulaması

| Kolon | Gerçek FK | Anlam |
|-------|-----------|--------|
| `employee_id` | → **`users`** (employees tablosu değil) | Reviewee |
| `reviewer_id` | → **`users`** | Reviewer |
| `status` | draft / submitted / approved / rejected | |

İsim yanıltıcı ama migration tutarlı; Policy `employee_id`’yi User id olarak ele alır. Model **atlanmadı**.

### ExpenseClaim

- `BelongsToCompany` **eklendi** (önceki bug).
- HR API yoktu → eklendi: `GET/POST /api/v1/expenses/claims…` (+ approve/reject).
- Portal: Policy ile update/delete güçlendirildi (approved → 403).

### DataScope genişletmeleri

- `scopeForEmployee` / `allowsEmployee` / `teamEmployeeIds`
- `scopeForPerformanceReview` (reviewer **veya** scoped reviewee)
- `canManageHrRecords` (company|department)
- `resolve`: `company_admin` / `super_admin` → **company** (liste filtresi Gate bypass ile uyumlu)

### Yan düzeltmeler (show 500 — Policy testlerinde ortaya çıktı)

| Bug | Düzeltme |
|-----|----------|
| `TrainingCertificate::where('user_id')` | `whereHas('participant')` + `issue_date` |
| `AssetAssignment.returned_date` | `return_date` |
| `ActivityLog.subject_type` | `model_type` / `model_id` |

### Test

| Suite | Sonuç |
|-------|--------|
| `PolicyDataScopeWave2Test` | **13 passed** |
| Tam `php artisan test` | **133 passed**, 1 risky |

Kritik: expense manager başka ekip approve → **403**; employee manager update subordinate → **403**.

### Sonraki / son Policy dalgası

Document görünürlük, ApprovalRecord tighten…

---

## Policy + Data Scope UYGULAMA — Dalga 3 SON (Document + ApprovalRecord)

**Tarih:** 11 Temmuz 2026 · **Branch:** `faz2-rbac-audit`

### Şema kararları (ön kontrol)

| Model / tablo | Görünürlük / sahiplik |
|---------------|------------------------|
| **`documents`** | `is_visible_to_employee` **YOK**; firma geneli. `uploaded_by` → users |
| **`employee_documents`** | **`is_visible_to_employee`** (bool); `employee_id` → employees; portal bunu kullanır |
| **`approval_records`** | `approver_id`, `is_current`, `status`; morph `approvable_*` |

İki belge modeli → **iki Policy**: `DocumentPolicy` + `EmployeeDocumentPolicy`.

### Document / EmployeeDocument kurallar

| Yüzey | view | update/delete |
|-------|------|---------------|
| Document (HR) | company\|department: hepsi; own/team: yalnızca `uploaded_by` | İK (`canManageHrRecords`) veya yükleyen |
| EmployeeDocument (HR) | `allowsEmployee` (bayraktan bağımsız) | İK + allowsEmployee |
| EmployeeDocument (Portal) | kendi employee + **`is_visible_to_employee=true`** | — (salt okuma) |

### ApprovalRecord

- Policy: `approve`/`reject`/`skip` → `WorkflowService::canApprove` (atanan veya vekil)
- `skip` artık `canApprove` ister (açık kapı kapandı)
- Vekalet entity_type: `LeaveRequest` / `leave_request` / `null` hizalandı
- `canApprove`: company_admin/super_admin geçici bypass (Gate ile uyum)
- `delegations` (tüm firma): yalnızca company scope
- `getApprovalHistory`: company veya ilgili onaycı/atanmış

### Yan bug düzeltmeleri

| Bug | Düzeltme |
|-----|----------|
| Portal download `public` disk | → `private` (HR upload ile aynı) |
| Portal `error(..., null, 404)` imza | → `error($msg, 404)` |
| Vekalet entity_type mismatch | snake + basename + null |

### Test

| Suite | Sonuç |
|-------|--------|
| `PolicyDataScopeWave3Test` | **6 passed** |
| Tam suite | **139 passed**, 1 risky |

### POLICY BLOĞU TAMAMLANDI mı?

**Evet — kayıt seviyesi P0–P2 modeller kapsandı.**

| Model | Policy | Dalga |
|-------|--------|-------|
| LeaveRequest | ✅ | 1 |
| Employee | ✅ | 2 |
| ExpenseClaim | ✅ | 2 |
| PerformanceReview | ✅ | 2 |
| Document | ✅ | 3 |
| EmployeeDocument | ✅ | 3 |
| ApprovalRecord | ✅ | 3 |

**Açık kalan / sonraki (Policy dışı veya düşük öncelik):**

| Konu | Not |
|------|-----|
| Document satır görünürlük (dept/kişi) | Şemada kolon yok — ayrı ürün kararı / migration |
| `document_approvals` / `required_documents` | Extended tablolar; model/controller entegrasyonu yok |
| ApprovalDelegation ayrı Policy | Controller’da own + company scope yeter |
| OneOnOne, Asset, Training, vb. | Permission + tenant; satır kapsamı ürün gerektirmedikçe sonraya |
| Hassas alan (maaş) izinleri | Ayrı blok (alan seviyesi) |
| Gate::before company_admin bypass kaldırma | Faz 2 sonu / Faz 6 |

---

## Alan Seviyesi İzinler Haritası — ADIM 1 TEŞHİS + STRATEJİ

**Tarih:** 11 Temmuz 2026 · **Branch:** `faz2-rbac-audit`  
**Kapsam:** Teşhis + strateji + saklama kararı önerisi. **Uygulama yok.**  
**Katman ayrımı:** Enforcement = route · Policy = kayıt · **bu blok = ALAN seviyesi.**

### Mevcut durum (özet)

| Katman | Durum |
|--------|-------|
| `app/Http/Resources/` | **Yok** — API Resource sınıfı tanımlı değil |
| `field_permissions` tablosu | **Yok** — yalnızca ROADMAP (`docs/ROADMAP.md` ~135, 188) |
| Spatie alan izni (`employees.salary.view`) | Kodda **1 referans** (`EmployeeReportController`); PermissionSeeder’da **yok** |
| Model `$hidden` | Employee/Payslip/ApiKey/User’da kısmi — **rolden bağımsız** (İK de göremez) |
| Write path alan filtresi | **Yok** — `employees.list.edit` + Policy update → maaş/TCKN mass-assign |

---

### 1) Hassas alan haritası

#### Employee (`backend/app/Models/Employee.php`)

| Alan | `$hidden` | Kim görmeli (öneri) | Mevcut API durumu |
|------|-----------|---------------------|-------------------|
| `gross_salary`, `net_salary` | ✅ | `employees.salary.view` (hr_manager / bordro; company_admin) | JSON’da **yok** (herkese gizli — İK de göremez) |
| `national_id` (TCKN) | ✅ | `employees.national_id.view` veya hr + kişinin kendisi | JSON’da yok |
| `iban` | ✅ | `employees.bank.view` (bordro/İK) | JSON’da yok |
| `sgk_number` | ✅ | `employees.sgk.view` (İK) | JSON’da yok |
| `bank_name` | ❌ | banka grubu ile aynı | **Dönüyor** (kısmi sızıntı) |
| `sgk_start_date`, `currency` | ❌ | sgk/salary ile hizala | Dönüyor |
| `custom_fields` | ❌ | Form Engine + alan izni (Faz 4) | Dönüyor |
| engel oranı | — | şemada kolon yok | — |

**Dönüş yolları:** `EmployeeController` show/index/store/update → **ham Eloquent** (`$hidden` uygulanır). Portal profil: manuel whitelist (maaş/banka yok). Auth login nested employee: `$hidden` geçerli.

**Write:** `update`/`store` validasyonunda `national_id`, `gross_salary`, `net_salary`, `iban`, `sgk_number`, `bank_name` serbest; Policy sadece satır/kapsam.

#### Payslip

| Alan | `$hidden` | Kim | Mevcut |
|------|-----------|-----|--------|
| `gross_salary`, `net_salary`, `deductions`, `bonuses` | model `$hidden` | kendi bordrosu (portal) / bordro rolü (ileride HR API) | Portal `show`: manuel dizi ile **tüm tutarlar kendi kaydında**; HR CRUD API yok |

#### CompanyLedger (SuperAdmin cari)

| Alan | `$hidden` | Kim | Mevcut |
|------|-----------|-----|--------|
| `amount`, `balance_after`, ödeme ref | Yok | SuperAdmin (route zaten kısıtlı) | **Tam model döner** — alan filtresi yok (P2) |

#### ApiKey / Webhook / User

| Model | Hassas | `$hidden` | Mevcut |
|-------|--------|-----------|--------|
| ApiKey | `key` | ✅ | store/regenerate’de kasıtlı açık; list/show gizli |
| Webhook | `secret` | ✅ | regenerate’de açık |
| User | `password`, `two_factor_secret`, `two_factor_recovery_codes` | ✅ | Auth `formatUser` whitelist; `invitation_token` **$hidden değil** (dikkat) |

#### Rapor / Dashboard bypass (kritik)

| Yer | Sorun |
|-----|-------|
| `EmployeeReportController` | `employees.salary.view` ister ama izin **seed edilmemiş**; company_admin tip bypass |
| `EmployeeDashboardController` | `AVG(gross_salary)` ham SQL — **izin kontrolü yok** |

---

### 2) Saklama kararı (öneri — onay bekliyor)

| Seçenek | Artı | Eksi |
|---------|------|------|
| **A) Spatie named permission** (`employees.salary.view`, `.edit` …) | Mevcut altyapı; seeder/role ataması hazır; EmployeeReport prototipiyle uyumlu; tablo yok | Firma-özel dinamik alan matrisi UI zayıf; Form Engine için yetersiz |
| **B) `field_permissions` tablosu** | ROADMAP DoD; rol×entity×field view/edit; Form Engine | Migration + UI + seed; daha ağır |

**Öneri (Faz 2 başlangıç):** **A — Spatie permission.**  
`field_permissions` tablosu → **Faz 4 Form Engine** ile (dinamik custom_fields + UI sekmesi). ROADMAP maddesi “temel” için Spatie yeterli sayılır; tablo “v2 / Form Engine tüketimi”.

**Karar noktası K1:** Spatie mi, yoksa hemen `field_permissions` tablosu mu?

---

### 3) Uygulama noktası (strateji — henüz kod yok)

**Okuma (response):**
1. `EmployeeResource` (ve diğer hassas modeller) ekle — controller ham model dönmesin.
2. `$hidden`’daki maaş/TCKN/iban/sgk’yı **kaldır veya `makeVisible` ile yetkiye bağla**; Resource içinde:
   - `$this->when($request->user()->can('employees.salary.view'), …)` / `mergeWhen`
   - Yetkisizde anahtar **hiç olmamalı** (`null` değil).
3. Ham SQL / aggregate (dashboard, rapor): aynı `can()` gate — Resource yetmez.

**Yazma (request):**
- FormRequest (veya controller öncesi): yetkisiz alan gelirse **reddet (422)** veya **sessizce strip**.
- **Öneri:** güvenlik için strip + (opsiyonel) audit uyarısı; sessiz strip UX’te “kaydettim sandım” riski → tercihen **açık 422** veya strip + response’ta “ignored_fields”.

**Karar noktası K2:** Yetkisiz write → **422 reddet** mi, **sessiz yoksay** mı?

**Resource yoksa bugün:** Tüm hassas CRUD ham model. Strateji: **Resource’a geçiş** (zorunlu). Dinamik `$hidden` / `makeVisible` geçici yama olabilir ama `.cursorrules` + ROADMAP Resource diyor.

---

### 4) Uygulama sırası (önerilen)

| # | İş | Neden |
|---|-----|-------|
| 1 | Seed: `employees.salary.view` (+ `.edit`); roller: hr_manager (+? company_admin) | Hayalet izni gerçek yap |
| 2 | `EmployeeResource` + show/index/update dönüşleri | Okuma filtresi |
| 3 | Update/store: salary alanlarını izne bağla | Write kilidi |
| 4 | Dashboard + Report maaş metriklerini aynı gate | Bypass kapat |
| 5 | `national_id` / `bank`+`iban` / `sgk` izin grupları | Genişletme |
| 6 | Payslip HR yüzeyi gelince Resource | Şimdilik portal own OK |
| 7 | ApiKey/User/Ledger | P2 — mevcut $hidden + route yeter / polish |

**Test stratejisi (uygulama dalgasında):**
- yetkili → response’ta `gross_salary` **VAR**
- yetkisiz → anahtar **YOK**
- yetkisiz PUT maaş → maaş **değişmez** (+ 422 veya strip kanıtı)

---

### 5) Karar noktaları — sorular

1. **K1 — Saklama:** Spatie named permission ile başlayalım mı? (`field_permissions` → Faz 4)
2. **K2 — Write:** yetkisiz alan gönderiminde 422 mi, sessiz strip mi?
3. **K3 — Rol matrisi:** `employees.salary.view` kimlere? Öneri: `hr_manager` + `company_admin`; `hr_specialist` **hayır**; manager/employee **hayır**. Onay?
4. **K4 — TCKN:** İK + kişinin kendisi (portal) mi, yoksa yalnızca İK mı?
5. **K5 — `$hidden` stratejisi:** Resource gelince global `$hidden` kaldırılsın mı (Resource tek kaynak), yoksa `$hidden` kalsın + yetkiliye `makeVisible` mı?
6. **K6 — `bank_name`:** hemen `iban` ile aynı gruba mı alınsın?
7. **K7 — Audit:** maaş değişikliği ActivityLog’da maskeli mi, tam mı görünür olsun? (şu an `toArray()` `$hidden` yüzünden maaş diff yok)

**Sonraki adım (onay sonrası):** ADIM 2 uygulama — önce Employee/maaş (sıra #1–4).

---

## Alan Seviyesi İzinler UYGULAMA — Dalga 1 (Employee)

**Tarih:** 11 Temmuz 2026 · **Branch:** `faz2-rbac-audit`

### Kararlar (kilitlendi)

| # | Karar |
|---|--------|
| K1 | Spatie named permission; `field_permissions` → Faz 4 |
| K2 | Yetkisiz write → sessiz strip + audit notu |
| K3 | salary → hr_manager (`employees.*`) + company_admin (Gate); hr_specialist **yok** |
| K4 | TCKN → own **veya** `employees.tckn.view`; manager subordinate göremez |
| K5 | global `$hidden` kaldırıldı; çıkış `EmployeeResource` |
| K6 | bank_name + iban + sgk → salary grubu |
| K7 | audit old/new hassas değerler `***`; açıklamada “maaş güncellendi” |

### Yapılanlar

| Parça | Detay |
|-------|--------|
| Seed | `employees.salary.view/edit`, `employees.tckn.view` — admin=all; hr_manager=`employees.*`; hr_specialist açık liste (salary/tckn yok) |
| Service | `EmployeeSensitiveFieldService` — view/edit/strip/mask |
| Resource | `EmployeeResource` — when ile alan; yetkisizde anahtar yok |
| Controller | Employee CRUD + Auth portal login + User show nested + Branch employees + Portal profile |
| `$hidden` | Employee modelinden kaldırıldı |
| Write | store/update strip + `yetkisiz alan güncellemesi yok sayıldı: …` |
| Audit | maskForAudit + “maaş güncellendi” notu |
| Bypass | Dashboard widget-data + Report getData/metadata maaş gate |

### Çıkış tarama (sızıntı kontrolü)

| Çıkış | Durum |
|-------|--------|
| EmployeeController index/show/store/update/portal | Resource ✅ |
| Auth portal login | Resource ✅ |
| UserController show nested | Resource ✅ |
| BranchController employees | Resource ✅ |
| PortalProfile show | Resource ✅ |
| managers()/orgChart/getManagers | kolon whitelist (maaş yok) ✅ |
| PerformanceReview `employee` | User FK (Employee modeli değil) ✅ |

### Test

| Suite | Sonuç |
|-------|--------|
| `EmployeeFieldPermissionTest` | **9 passed** (hr_manager VAR / hr_specialist YOK / own TCKN / manager yok / strip+audit / company_admin / mask / dashboard+report 403) |
| Tam suite | **148 passed**, 1 risky |

### Sonraki

- Payslip / diğer modeller alan seviyesi (isteğe bağlı)
- Rol Yönetimi UI’da salary/tckn checkbox
- `field_permissions` tablosu (Faz 4)
