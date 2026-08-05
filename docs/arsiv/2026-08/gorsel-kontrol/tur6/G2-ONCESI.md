# Tur6 — G2 öncesi üç doğrulama

**Branch:** `faz4-form-engine` · **DB wipe:** yok · **Push:** yok

## Özet

| # | Madde | Sonuç |
|---|--------|--------|
| 1 | QR consume ↔ `resolvePortalCompanyId` | Kırmızıydı → `consume` tek kaynağa bağlandı; test yeşil |
| 2 | Membership = yetki | Belge: `SISTEM_ISLEYIS.md` + borç satırı |
| 3 | QA-4 DemoSeeder / Retention | Zaten düzeltilmişti (kanıt); DOK-3 🔴 yanlış işareti ✅ yapıldı |

---

## 1) QR yolu ile portal fallback

**Çelişki:** `resolvePortalCompanyId` home’da personel yoksa personel şirketine (B) düşüyordu; `AttendanceKioskTokenService::consume` token firmasını `actor->company_id` (home A) ile karşılaştırıyordu → 422.

| Dosya | Satırlar | Eski | Yeni | Test |
|-------|----------|------|------|------|
| `Services/Timesheet/AttendanceKioskTokenService.php` | `consume` | `(int) actor->company_id` | `CompanyContextService::resolvePortalCompanyId($actor)` | `PortalQrHomeEmployeeMismatchTest::test_portal_qr_punch_uses_employee_company_when_home_differs` |

Senaryo: home A, aktif personel yalnız B → B kiosk QR → punch **B**, 422 yok.

---

## 2) Membership = yetki (kod yok)

`docs/SISTEM_ISLEYIS.md` AŞAMA 1: kullanıcı üye olduğu her şirkette global Spatie rolüyle çalışır; şirkete özel rol yok. Kanca: `company_user.role_id` (NULL).

`docs/GUNCEL_DURUM_RAPORU.md` açık borçlar: «Şirkete özel rol (`company_user.role_id`) — Backlog».

---

## 3) QA-4 borçları — kanıt

### (a) DemoSeeder çift kaynak?

| Kaynak | Durum |
|--------|--------|
| `DemoSeeder.php` | İnce sarmalayıcı: yalnız `DemoDataSeeder::class` çağırır (yorum: QA-4 tek kaynak) |
| `DemoDataSeeder.php` | `admin@demo.test` + `demo-firma` üreten tek gövde |
| Test | `DemoDataSeederGuardTest::test_demo_seeder_is_thin_wrapper_over_demo_data_seeder` + idempotent (tek admin, tek slug) |

**Sonuç:** Çift üretim yok; Tur5 listeden düşürme doğruydu. DOK-3 1.1 🔴 → ✅.

### (b) RetentionPolicy `active => true`?

| Kaynak | Durum |
|--------|--------|
| `DemoDataSeeder` ~903–912 | `'active' => false` + D2c yorumu |
| Test | `DemoDataSeederGuardTest::test_retention_policies_inactive_after_seed` (aktif sayım = 0) |

**Sonuç:** Hâlâ true değil; yeni düzeltme gerekmedi. DOK-3 1.2 🔴 → ✅. Listeden düşürme doğruydu (yanlış “kapandı” değil — gerçekten kapalı).

---

## Not — suite süresi (yalnız öneri, uygulama yok)

Tur4 ~765s → Tur5 ~1258s. Faz 6’da 14 modül eklenince koşu maliyeti artacak.

**Öneriler (uygulama yok):**
1. PHPUnit `--parallel` / `paratest` (sqlite/pgsql izolasyonlu DB şablonları)
2. Suite grupları: `Unit` | `Feature/Api` | `Feature/GroupIsolation` | `Feature/Kvkk` — CI’da paralel job
3. Ağır seeder testlerini (`DemoDataSeederGuardTest`) ayrı “nightly / demo” job’una ayır
4. RefreshDatabase + migrate maliyetini `DatabaseMigrations` + şablon DB clone ile düşür

---

## Test / suite

```
Filter: PortalQrHomeEmployeeMismatchTest|PortalPdksPunchSecurityTest|PortalPdksCompanyRegressionTest|DemoDataSeederGuardTest
Tests: 12 passed (67 assertions)

Full suite (Docker alatax-hr-app):
Tests: 691 passed (3028 assertions)
Duration: 1258.55s
(Tur5: 690 → +1 yeni Tur6 testi)
```

---

## git diff --stat

```
 .cursorrules                                       |   2 +
 backend/app/Services/CompanyContextService.php     |  22 +-
 backend/app/Services/Settings/SettingsWriter.php   |  35 ++-
 .../Services/Timesheet/AttendanceClockService.php  |   3 +-
 .../DatasetRegistryIsolationTest.php               | 344 +++++++++++++++++++++
 .../GroupIsolation/PortalPdksPunchSecurityTest.php | 213 +++++++++++++
 .../SettingsWriterActiveContextTest.php            |  95 ++++++
 docs/FAZ_G_RAPOR.md                                |   2 +
 docs/GUNCEL_DURUM_RAPORU.md                        |  13 +-
 docs/SISTEM_ISLEYIS.md                             |   4 +
 docs/gorsel-kontrol/tur6/G2-ONCESI.md              | (yeni)
```
