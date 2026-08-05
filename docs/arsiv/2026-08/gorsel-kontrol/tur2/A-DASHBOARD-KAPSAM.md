# Tur2 Bölüm A — Dashboard şirket kapsamı teşhisi

**Branch:** faz4-form-engine · **Tarih:** 2026-08-04

## 1. SQL sonucu (ham)

```sql
SELECT company_id, count(*) AS cnt
FROM users
WHERE deleted_at IS NULL
GROUP BY 1
ORDER BY 1;
```

```json
[
    { "company_id": 69, "cnt": 51 },
    { "company_id": 70, "cnt": 3 },
    { "company_id": 71, "cnt": 11 },
    { "company_id": 72, "cnt": 10 }
]
```

Slug eşlemesi (demo): `69=demo-firma`, `71=demo-otel-b`, `72=demo-otel-c`.

**Sonuç:** 51 tek bir `company_id` içinde (69 / Demo Firma AŞ). Hipotez “41+10 employee toplamı” değil; `users` satırları home company’de toplanmış.

## 2. Üç sorunun cevabı

### Q1 — “Aktif Kullanıcı” hangi kaynak?

**Dosya:** `backend/app/Http/Controllers/Api/V1/DashboardController.php`

**Tur1 (bug):** satır ~40–45 — `$company = $user->company` → `User::where('company_id', $company->id)`. Yani **`$user->company_id` (home)**. CompanyContext / `getCompanyId()` yoktu.

**Yan etki:** `LeaveRequest` / `JobPosition` / `Document` sorguları aynı `$company->id` (home) ile yazılıyordu; modellerde `BelongsToCompany` global scope ise **aktif bağlamı** ekliyordu → home ≠ aktif iken `WHERE company_id=home AND company_id=aktif` → **0**. KPI’lar “doğru düşmüş” gibi görünen yanlış sıfır.

### Q2 — “Firma Bilgileri” kartı hangi company?

Aynı controller: `$user->company` (home). FE `data.company.name` / `package_type` gösterir (`DashboardPage.tsx` ~136, ~364).

### Q3 — Hoş geldiniz alt satırı FE kaynağı?

`frontend/apps/company/src/pages/DashboardPage.tsx`:
- Başlık: `user?.name` (auth) → `welcomeNamed`
- Alt satır: **`data.company.name`** (dashboard API) — `auth.user.company` değil, `companyContext` de değil.

Ayrıca `useEffect(..., [])` — şirket değişiminde yeniden fetch yok (React Query değil, local state). Şirket değişince `/dashboard`’a navigate ile remount olursa yeni istek atılır; aynı route’ta kalınırsa FE bayat kalabilir.

**Öneri (bu turda uygulanmadı — FE+BE birlikte sınır dışı):** `companyContext.version` bağımlılığı ile `loadDashboard()` tekrarı.

## 3. G1 kapsamında mıydı?

**Hayır.** `docs/FAZ_G_RAPOR.md` G1 listesi: BelongsToCompany, BaseController, BranchContext*, NotificationController, ApprovalWorkflowPolicy, EmployeeDashboardController, ActivityLog, Auth formatUser…  

`DashboardController` (company SPA `/api/v1/dashboard`) **listede yok — dönüşümde atlanmış.**

## 4. Kardeş tarama (Portal hariç)

Operasyonel yolda hâlâ `$user->company_id` / `$request->user()->company_id` / `auth()->user()->company_id` okuyanlar (grep `backend/app`, Portal controller’lar elendi):

| Dosya | Not |
|-------|-----|
| `Http/Controllers/Api/V1/DashboardController.php` | **düzeltildi** → `getCompanyId()` |
| `Http/Controllers/Api/V1/Kvkk/DestructionController.php` | `$request->user()->company_id` (çok satır) |
| `Http/Controllers/Api/V1/Kvkk/DataBreachController.php` | aynı |
| `Http/Controllers/Api/V1/Kvkk/LegalHoldController.php` | aynı |
| `Http/Controllers/Api/V1/Kvkk/RetentionPolicyController.php` | aynı |
| `Http/Controllers/Api/V1/Kvkk/DataSubjectRequestController.php:218` | `$user->company_id` |
| `Http/Controllers/Api/V1/Settings/SettingsRegistryController.php` | query/body fallback `$user->company_id` |
| `Services/Settings/SettingsResolver.php:245–250` | `$user->company_id` |
| `Services/Notification/NotificationService.php` | membership vs home karşılaştırması |
| `Services/Timesheet/AttendanceClockService.php` | `$user->company_id` |
| `Services/Reports/Datasets/*Dataset.php` | `$user->company_id` (rapor/G2 alanı) |
| `Services/BranchContextService.php:38` | fallback home |
| `Traits/BelongsToCompany.php:59` | fallback home (bilinçli) |
| `Policies/ApprovalWorkflowPolicy.php:54` | fallback home |
| `EmployeeDashboardController.php` | `CompanyContext::id() ?? $user->company_id` (kısmen çevrilmiş) |
| `UserController.php` | çoğunlukla `!== getCompanyId()` izolasyon kontrolü |

Portal `*/Portal/*` bilinçli istisna — listelenmedi.

## 5. Karar

**Düzeltildi.**

1. **BE** `DashboardController::index` → `$this->getCompanyId()` + `Company::find` (G1 atlanmıştı).
2. **FE** `DashboardPage.tsx` → `companyContext.version` ile yeniden fetch (şirket değişince bayat `data.company` kalıyordu). A3’te “tek controller” yeterli sandık; Tur2 G3 ölçümü FE bayatlığını kanıtladı → aynı semptom için minimal FE eklendi.
3. Regresyon: `GroupIsolationTest::test_16_dashboard_stats_follow_active_company_context`

### Ürün kodu diff

```
backend/app/Http/Controllers/Api/V1/DashboardController.php
backend/tests/Feature/GroupIsolation/GroupIsolationTest.php  (+test_16)
frontend/apps/company/src/pages/DashboardPage.tsx            (companyVersion refetch)
```

## 6. Test

- GroupIsolation: **16 passed**
- Tam suite: **657 passed** (656 + test_16)