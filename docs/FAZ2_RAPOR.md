# Faz 2 — PHPUnit Factory Temeli (İlerleme Raporu)

**Branch:** `faz2-rbac-audit`  
**Başlangıç:** 11 Temmuz 2026  
**Kapsam (bu adım):** Faz 0/1 PHPUnit borcu — factory + `users.type` hizalama; CI `continue-on-error` kaldırma.

---

## ADIM 1 — Başlangıç teşhisi (`php artisan test`, Docker pgsql)

**Özet:** `41 failed`, `3 passed` (13 assertion).

### (a) Eksik factory

| Model | Belirti | Etkilenen testler |
|-------|---------|-------------------|
| `Company` → `CompanyFactory` yok | `Class "Database\Factories\CompanyFactory" not found` | ExpenseTest (7), SurveyTest (7), TimesheetTest (9) ≈ **23** |

Diğer factory'ler mevcut ama `Company::factory()` zincirine bağımlı: `ExpenseCategoryFactory`, `ExpenseClaimFactory`, `SurveyFactory`, `AttendanceRecordFactory`.

### (b) Enum / `users.type` uyumsuzluğu

| Kaynak | Değer | Gerçek şema |
|--------|-------|-------------|
| `RouteAuthorizationTest` / `RouteComprehensiveTest` setUp | `type => 'employee'` | `UserType`: `super_admin \| company_admin \| user` |
| Hata | `ValueError: "employee" is not a valid backing value for enum App\Enums\UserType` | ≈ **18** test |

**Karar (backend gerçeği):** Enum'a `employee` **eklenmeyecek**. Personel = `UserType::User` (`user`) + `employees` satırı (`PortalAccess` middleware `user->employee` ister). Testler enum'a hizalanır: `employee` → `user`.

### (c) Diğer altyapı (beklenen, factory sonrası)

| Konu | Açıklama |
|------|----------|
| Portal | `PortalAccess` → aktif `Employee` kaydı zorunlu; portal test setUp'larına `EmployeeFactory` |
| Surveys modülü | `module.access:surveys` → firmaya modül atanmalı |
| `Company.is_active` | Kolon yok; `status` (`CompanyStatus`) kullanılır — testlerde `is_active` düzeltilmeli |
| Test DB | Docker'da `alatax_hr_testing` oluşturulmalı; suite `DB_DATABASE=alatax_hr_testing` ile koşulmalı (dev DB'yi ezmemek) |

### Başlangıçta geçenler

- `Tests\Unit\ExampleTest`
- `Tests\Feature\ExampleTest` (veya AuthThrottle — ortamına göre)
- Kısmi: factory/enum'a takılmayan smoke testler

---

## ADIM 2–4 — Uygulama (güncellenir)

*(Aşağıdaki bölümler düzeltmeler sonrası doldurulur.)*

### Eklenen factory'ler

- (bekleniyor) `CompanyFactory`, `EmployeeFactory`; `UserFactory` enum state'leri

### `users.type` kararı

- Testler → `UserType::User` (`user`); enum değişmedi.

### Sonuç skor kartı

| Metrik | Önce | Sonra |
|--------|------|-------|
| Failed | 41 | _TBD_ |
| Passed | 3 | _TBD_ |
| `continue-on-error` kaldırıldı mı? | hayır | _TBD_ |
| CI tam yeşil mi? | — | _TBD_ |
