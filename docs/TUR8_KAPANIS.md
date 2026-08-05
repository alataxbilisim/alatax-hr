# TUR 8 — Tur 7 kapanışı

**Branch:** `faz4-form-engine` · **DB wipe:** yok · **Push:** yok  
**Suite:** `725 passed (3180 assertions)` (= 721 + 4 yeni)  
**Tarih:** 2026-08-05

---

## 1) `full_name` backfill

### Ham sayım (`alatax_hr`)

```
SELECT count(*) FROM employees WHERE full_name IS NULL OR full_name = '';
→ 6

total employees = 62
null_with_user  = 0
null_no_user    = 6
```

### Sonuç

- Kullanıcısı olan boş kayıt **yok** → `users.name` backfill’i bu ortamda noop.
- **6 kayıtta `user_id` yok** — ad hiç girilmemiş; otomatik doldurulamaz. Elle personel formundan ad yazılmalı veya silinmeli / arşivlenmeli. `php artisan employees:backfill-full-name` bu satırları uyarır, güncellemez.
- Komut: `employees:backfill-full-name` (`--dry-run` destekli, `production` ortamında çalışmaz). Migration `2026_08_05_140000` içinde de user→full_name backfill vardı (Tur7).

### Tek doğruluk kaynağı

- SSOT: **`employees.full_name`**
- API `name` = display alias (`display_name` accessor)
- `users.name` yalnız bağlı hesap etiketi; personel adının kaynağı değil
- `.cursorrules`’a tek satır kural eklendi

### Test

`EmployeeFullNameBackfillTest` — boş full_name + user → komut doldurur; liste + detayda görünür.

---

## 2) Panel erişimi → `roles.panel_access`

Tur7’deki “adı ≠ employee” kuralı kaldırıldı.

| Değişiklik | Dosya |
|---|---|
| Migration + backfill (`employee`→false, diğerleri→true) | `2026_08_05_153000_add_panel_access_to_roles_table.php` |
| `PanelAccess::has` / query constrain | `app/Support/PanelAccess.php` |
| Role CRUD + korumalı rollerde yalnız `panel_access` | `RoleController` |
| Seeder idempotent | `PermissionSeeder` |
| Auth `formatUser.panel_access` | `AuthController` |
| FE `hasPanelAccess` | `permissions.ts` |
| Rol formu checkbox | `RoleForm.tsx` + i18n |

### Testler

- **Yeni (kırmızı→yeşil):** `test_portal_like_named_role_without_panel_access_flag_is_portal_only` — “Otel Personeli”, yalnız portal-self, `panel_access=false` → panel yok, `/users`’ta yok, login 403.
- **Regresyon:** Tur7 dar-custom-rol (`panel_access=true`) + Tur3 portal-only Y2 yeşil.

---

## 3) Pozisyon: kimlik (code)

### Teşhis (sorulara cevap)

| Soru | Cevap |
|---|---|
| `positions.code` her satırda dolu ve benzersiz mi? | Evet: **206** aktif satır, **0** null/boş code, `(company_id, code)` duplikasyon **0** |
| `employees.position` nasıl saklanıyor? | **Serbest metin string** (FK yok) — katalog ile uyumlu tutulması hedeflenmiş |
| `EmployeeFormEnginePage` aynı desen mi? | Evet: Select `value = code` (zaten) |

### Yapılan (şema değişikliği yok)

- `EmployeeForm` onChange artık **code** yazar (Tur7’de kısa süre name yazılıyordu).
- `EmployeeResource.position_label`: code veya legacy name ile katalogdan ad çözümler.
- İleride daha sıkı bütünlük için öneri (karar sende — **şimdi yapılmadı**): `employees.position_id` FK → `positions.id`; mevcut string kolonu geçiş sonrası drop. Dosya/satır: `employees` migration baseline + `Employee` fillable + formlar. Şimdilik string kolonda **code** yeterli.

### Test

`EmployeePositionCodePersistTest` — aynı adlı iki pozisyon, iki personele farklı code; kayıt sonrası ayırt edilir.

---

## 4) Sistem panosu düzeni kimin?

**Cevap:** Layout **tek paylaşımlı** `dashboards.layout` JSON satırında; kullanıcı başına değil. Tur7’nin sistem panosuna doğrudan layout kaydı açması herkesi etkilerdi — metinle (“kopyalayarak özelleştirin”) çelişiyordu.

**Doğru davranış** (`docs/SISTEM_PANOSU_DUZEN.md`): sistem panosu salt okunur; **Kopyala** ile firma panosu oluşur; layout orada saklanır.

**Düzeltme:** `Dashboard::canEdit` / `DashboardService::update` → `is_system` ise 403; FE’de düzenleme kapalı + “Kopyala ve özelleştir”.

### Test

`SystemDashboardReadonlyTest` — iki kullanıcı sistem layout’unu bozamaz; kopyalar bağımsız.

---

## 5) FE test boşluğu (yalnız karar — kurulmadı)

Tur7’nin Select key/value ve ellipsis düzeltmelerinin otomatik testi yok; monorepoda Vitest + Testing Library de yok. Faz 6’da 14 modül aynı shared `Select`’i kullanacak. **Öneri:** Faz 6 öncesi `packages/shared`’a dar bir Vitest + Testing Library kurmak (yalnız `Select` + 2–3 smoke: aynı label farklı value, ellipsis class, controlled value) ~0.5–1 gün; tüm company SPA sayfa/E2E’ye yaymak gereksiz maliyet. Tam Playwright zaten `scripts/gorsel-kontrol`’de — UI regression orada; unit FE yalnız shared bileşen için değerli. **Bu turda kurulmadı.**

---

## `git diff --stat` (Tur8, e2e png hariç)

```
 .cursorrules                                                     |  1 +
 backend/app/Console/Commands/EmployeesBackfillFullNameCommand.php | 70 (yeni)
 backend/app/Http/Controllers/Api/V1/AuthController.php            |  1 +
 backend/app/Http/Controllers/Api/V1/RoleController.php            | 28
 backend/app/Http/Resources/EmployeeResource.php                  | 26
 backend/app/Models/Dashboard.php                                 |  4
 backend/app/Models/Role.php                                      |  4
 backend/app/Services/Reports/DashboardService.php                | 18
 backend/app/Support/PanelAccess.php                              | 41
 backend/database/migrations/2026_08_05_153000_add_panel_access…  | 29 (yeni)
 backend/database/seeders/PermissionSeeder.php                    |  5
 backend/tests/Feature/EmployeeFullNameBackfillTest.php           | (yeni)
 backend/tests/Feature/EmployeePositionCodePersistTest.php        | (yeni)
 backend/tests/Feature/PanelAccessControlTest.php                 | 52
 backend/tests/Feature/SystemDashboardReadonlyTest.php            | (yeni)
 docs/SISTEM_PANOSU_DUZEN.md                                      | (yeni)
 docs/TUR8_KAPANIS.md                                             | (bu dosya)
 frontend/apps/company/src/components/EmployeeForm.tsx            |  9
 frontend/apps/company/src/components/RoleForm.tsx                | 24
 frontend/apps/company/src/pages/dashboards/DashboardViewPage.tsx | 50
 frontend/packages/shared/src/constants/permissions.ts            | 22
 frontend/packages/shared/src/i18n/locales/tr/common.json         |  9
```

Tracked diff (commit öncesi HEAD’e göre, yeni dosyalar hariç sayı): ~15 dosya, +219/−75 (+ yeni test/migration/command/docs).

---

## DoD

| Madde | Durum |
|---|---|
| 1–4 testli yeşil | ✓ |
| Suite 721 + yeni → **725** | ✓ |
| Commit | yapılacak (push yok) |
| Push | yok |
