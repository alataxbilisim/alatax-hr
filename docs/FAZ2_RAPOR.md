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

## Notlar / Faz 2 devamı

- Migration squash hâlâ Faz 2 borcu (bu adımda yok).
- RBAC v2 + Audit v2 asıl Faz 2 kapsamı bundan sonra.
- `job_positions.deadline` vb. şema sapmaları logda görüldü; CvPool dışı — ayrı takip.
