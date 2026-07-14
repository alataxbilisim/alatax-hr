# FAZ B — Rapor

**Branch:** `faz4-form-engine`  
**Tarih:** 14 Temmuz 2026  
**PUSH:** yok (kullanıcı onayı beklenir)  
**DB:** `migrate:fresh` / wipe / truncate **kullanılmadı**; testler yalnızca `alatax_hr_testing` (RefreshDatabase).

---

## B-1 — İzin akışı tamiri

### ADIM 0 — Teşhis

| # | Bulgu | Gerçek |
|---|--------|--------|
| 1 | Bakiye `update` / `bulkUpdate` | Controller metodları vardı; **route yoktu** |
| 2 | Company iptal | `cancel()` vardı; **route yoktu**; status enum karşılaştırması + Policy dar |
| 3 | Portal types | `unit` / `default_limit` seçiliyordu — şemada yok; kaynak `default_days` |
| 4 | Personel LeavesTab | FE `entitled_days`; BE `total_days` |

### Uygulama özeti

| Adım | Durum | Kanıt |
|------|--------|--------|
| 0 Teşhis | ✅ | Yukarıdaki tablo |
| 1 Bakiye manuel atama | ✅ | Route + FormRequest (`reason`) + ActivityLog + DataScope/branch-context |
| 2 İzin iptal (pending) | ✅ | `POST .../cancel` + Policy `allowsUserId` + `pending_days` geri |
| 3 Alan drift | ✅ | Portal `default_days` (+ alias); LeavesTab → `total_days`; portal balances `unit` kolon kaldırıldı |
| 4 Test | ✅ | Aşağıdaki sayılar |

### Eklenen / netleşen route'lar

| Method | Path | Permission |
|--------|------|------------|
| `PUT`/`PATCH` | `/api/v1/leaves/balance/{leave_balance}` | `leaves.balances.edit` |
| `POST` | `/api/v1/leaves/balance/bulk` | `leaves.balances.edit` |
| `GET` | `/api/v1/leaves/balance/my` | `leaves.balances.view` |
| `POST` | `/api/v1/leaves/requests/{leave_request}/cancel` | `leaves.requests.delete` \| `leaves.requests.create` |

`leaves.balances.edit` zaten hiyerarşik PermissionSeeder’da (`balances => view, edit`); admin / `leaves.*` rolleri kapsar.

### Test sonuçları (`alatax_hr_testing`)

| Suite | Sonuç |
|-------|--------|
| `LeaveFlowRepairTest` | **11 passed** (24 assertions) |
| Regresyon: DataScope + BranchContext + BranchDataScope + LeaveRequestPolicy + Wave2/3 | **50 passed** (148 assertions) |
| Fail | **0** |

### Güvenlik doğrulama

- Başka tenant bakiyesi → 404 (BelongsToCompany)
- Yetkisiz rol / başka şube branch_manager → 403
- Approved iptal → 422; başka kullanıcının talebi (employee) → 403
- DataScope / A3–A6 branch-context testleri yeşil kaldı
- Push yapılmadı

---

## B-2 — İşe alım akışı tamiri

### ADIM 0 — Teşhis

| # | Bulgu | Gerçek |
|---|--------|--------|
| 1 | Public apply status | `published` arıyordu; enum/JobController **`active`** → **KARAR: `active`** |
| 2 | Public create alanları | `applicant_*` / `position_id` — şema: `first_name`/`last_name`/`email`/`job_position_id` |
| 3 | HR store | **Yoktu**; kanban `GET /recruitment/applications` (index de yanlış kolon arıyordu) |
| 4 | hired→employee | Stage update vardı; personel dönüşümü **yoktu** |

### Uygulama özeti

| Adım | Durum | Not |
|------|--------|-----|
| 1 Public başvuru | ✅ | `active` + şema alanları + `consent_kvkk`/`consent_at` + company slug tenant |
| 2 Manuel aday | ✅ | `POST /recruitment/applications` + FE “Aday Ekle” |
| 3 hired→personel | ✅ | `POST .../convert-to-employee` ön-doldurma; **onboarding otomatik YOK** (ayrı iş) |
| 4 Test | ✅ | Aşağıda |

### Status kararı

**`active`** — `JobPositionStatus` enum + Public `JobController` + `publish()` yardımcıları baskın doğruluk; `published` yalnızca `published_at` timestamp anlamında.

### Eklenen alanlar (ekleyici migration)

- `job_applications.consent_kvkk` (bool)
- `job_applications.consent_at` (timestamp, nullable)
- `job_applications.converted_employee_id` → `employees` (nullable FK)

### Eklenen route'lar

| Method | Path | Auth / Permission |
|--------|------|-------------------|
| `POST` | `/api/v1/public/jobs/{slug}/apply` | public + `company_slug` body |
| `POST` | `/api/v1/public/companies/{slug}/jobs/{slug}/apply` | public (tercih) |
| `POST` | `/api/v1/recruitment/applications` | `recruitment.applications.edit` |
| `POST` | `/api/v1/recruitment/applications/{id}/convert-to-employee` | `edit` \| `employees.list.create` |

### Test sonuçları (`alatax_hr_testing`)

| Suite | Sonuç |
|-------|--------|
| `RecruitmentFlowRepairTest` | **10 passed** |
| Regresyon paketi (B-1 + kanban + DataScope + branch) | **56 passed** / **0 fail** |

### DUR notu

- **Onboarding otomatik tetikleme** bu adımda yok (AKIS_SPEC Aşama 3 zinciri ayrı iş).

---

## B-DB — Test suite dev DB koruması (acil)

### Teşhis

| Madde | Bulgu |
|-------|--------|
| `phpunit.xml` | `DB_DATABASE=alatax_hr_testing` vardı ama **`force` yoktu** |
| `.env.testing` | **YOKTU** |
| `config/database.php` | `env('DB_DATABASE')` |
| Docker compose | `DB_DATABASE=alatax_hr` process env'e yazılıyor |
| `bootstrap/cache/config.php` | yok |
| Base `TestCase` | RefreshDatabase kullanmıyor; Feature testleri trait ile kullanıyor |

### Kök neden

Docker/compose ortamında `DB_DATABASE=alatax_hr` zaten set. PHPUnit `<env>` varsayılanı mevcut env'i **ezmez**.  
Runtime kanıt (önce): `DB::connection()->getDatabaseName() = **alatax_hr**` → `RefreshDatabase` dev'i siliyordu.

### Koruma

1. `phpunit.xml`: `DB_DATABASE` / `APP_ENV` / `DB_CONNECTION` → `force="true"`
2. `tests/bootstrap.php`: `putenv` + `$_ENV` ile `alatax_hr_testing` kesinleştirme; config cache silme
3. `.env.testing` + `.env.testing.example` (dev adından farklı)
4. `TestCase::assertUsingTestingDatabase()` + `beforeRefreshingDatabase()` — yanlış DB'de **RuntimeException**

### Kanıt

| An | `getDatabaseName()` | Sentinel `admin@demo.test` |
|----|---------------------|----------------------------|
| Önce (probe) | `alatax_hr` | — |
| Sonra (probe) | `alatax_hr_testing` | — |
| Suite sonrası (dev tinker) | `alatax_hr` | **mevcut (SENTINEL_AFTER=yes)** |

Tam suite (ilk B-DB sonrası): 303 passed / 22 failed (testing DB deadlock — **dev wipe değil**).  
DB wipe komutu çalıştırılmadı. Push yok.

---

## B-DB2 — 22 fail yeşile çekildi

### Sınıflandırma (C = 0)

| Kat. | Adet | Anlam |
|------|------|--------|
| **A** | 4 | PermissionDoesNotExist — seed yarışı / eksik wildcard satırı zinciri |
| **B** | 18 | DeadlockException / QueryException(40P01) — eşzamanlı suite + PermissionSeeder `firstOrCreate` yarışı |
| **C** | **0** | Gerçek B-1/B-2 regresyonu yok |

Fail listesi (özet): HierarchicalPermissionGate×7, Survey×6, PositionCatalog×1, AuditWave2×1, BranchContext×1, CompanyAdminRoleGuarantee×1, DefaultCompanyHrSeed×1, EmployeeCustomField×1, EmployeeFieldPermission×1, LeaveFlowRepair bulk×1, PermissionEnforcementWave2×1.

### Düzeltmeler

1. `Tests\Concerns\RefreshDatabase` — `migrate:fresh` öncesi `pg_advisory_lock` (eşzamanlı suite DROP yarışı)
2. Tüm testler bu concern’e taşındı (Laravel trait yerine)
3. `PermissionSeeder` — rol wildcard’larını (`employees.*` vb.) upsert; model sync; testing’de seed lock; deadlock retry
4. `assignSpatieAdminRole` — permission **modelleri** ile sync
5. B-DB guard’a dokunulmadı

### Doğrulama

| Metrik | Sonuç |
|--------|--------|
| Tam suite | **325 passed / 0 fail** (1284 assertions, exit 0) |
| Sentinel `admin@demo.test` | suite sonrası **mevcut** |
| B-1 / B-2 / DataScope / branch | yeşil (filtreli + tam suite) |
| Push | yok |
| Dev DB wipe | yok |

---

## B-3 — Masraf + Puantaj HR yüzü

### ADIM 0 — Teşhis (özet)

| Alan | Önce | Sonra |
|------|------|--------|
| Masraf HR BE | index/show/approve/reject | + `markPaid` + kategori CRUD |
| Company FE expenses/attendance | yok | nav + route + sayfalar |
| Expense submit → workflow | yok (legacy submitted) | `startWorkflow` + leave köprüsü |
| Puantaj FE | yok | `/attendance` panosu |
| Policy discovery | — | `AttendanceRecordPolicy` (ad düzeltmesi) |

**Workflow kararı:** `ExpenseClaim` → entity `expense_request`; leave deseninden ciddi sapma yok; DUR edilmedi.

### Uygulama

| Adım | Durum | Kanıt |
|------|--------|--------|
| 1 Masraf HR yüzü | ✅ | Company nav/route; `mark-paid`; kategori CRUD |
| 2 Workflow wire | ✅ | Portal submit + HR approve/reject köprüsü |
| 3 Puantaj panosu | ✅ | DataScope + Policy; vardiya/PDKS **kapsam dışı** |
| 4 Test | ✅ | aşağıda |

### Route / permission (yeni veya netleşen)

| Method | Path | Permission |
|--------|------|------------|
| POST | `/api/v1/expenses/claims/{id}/mark-paid` | `expenses.claims.edit` |
| GET/POST/PUT/PATCH/DELETE | `/api/v1/expenses/categories…` | `expenses.categories.*` |
| (mevcut) | `/api/v1/attendance…` | `timesheet.attendance.*` — FE açıldı |

Seed: `hr_manager` → `timesheet.attendance.*`; `manager` / `branch_manager` → view+approve.

### PermissionEnforcementWave1 — TIGHTENING (zayıflatma değil)

| | Eski | Yeni |
|---|------|------|
| Beklenti | Salt `timesheet.attendance.approve` izni → 200 | Policy + DataScope: company/team/branch kapsamı gerekir |
| Test aktörü | rolü olmayan User + permission | `CompanyAdmin` (company scope) |
| Karar | — | **TIGHTENING** — ExpenseClaim “permission alone → 403” ile aynı model |

`branch_manager` / `manager`: seed izinleri + DataScope; `ExpenseAttendanceHrFaceTest` şube izolasyonunu doğrular (kendi şube ✅, diğer şube 403).

### Bug fix (doğrulama sırasında)

`AttendancePolicy` → **`AttendanceRecordPolicy`** (Laravel discovery: `AttendanceRecord` model adı). Yanlış ad `authorize(viewAny)` → 403 üretiyordu; izinler doğruydu.

### Doğrulama (OOM sonrası)

| Metrik | Sonuç |
|--------|--------|
| Filtreli `ExpenseAttendanceHrFaceTest` | **13 passed** (46 assertions) |
| Tam suite (`php artisan test`, sıralı; `--processes` PHPUnit’de yok) | **338 passed / 0 fail** (1330 assertions, ~451s, exit 0) |
| Önceki 137 | aşırı bellek/kill; bu koşuda tekrarlanmadı |
| company `tsc --noEmit` | **0** |
| Sentinel `admin@demo.test` @ `alatax_hr` | suite öncesi/sonrası **yes** |
| C regresyon | **0** |
| Push / dev wipe | yok |

Commit’ler: `8e343d5` feat(B-3); `1c6b5b4` policy ad fix.
