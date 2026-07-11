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

Dosya: `tests/Feature/PermissionEnforcementWave2Test.php`

### Sonraki dalgalar (bu turda yok)

onboarding, performance, training, assets, surveys, analytics + admin grupları (users/employees/roles) — **dokunulmadı**.
