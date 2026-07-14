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
