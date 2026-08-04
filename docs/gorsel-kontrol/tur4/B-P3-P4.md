# Tur4 B — P3/P4 bağlam + PDKS portal regresyonu

**Branch:** `faz4-form-engine` · **DB wipe:** yok · **Push:** yok · Görsel koşucu: dokunulmadı

## Özet

| Madde | Sonuç |
|-------|--------|
| PDKS portal regresyon | Risk **vardı** → düzeltildi; testler kalıcı yeşil |
| P3 Ayar çözümleme | Bitti |
| P4 Rapor dataset'leri | Bitti (4 dataset; grep başka home okuyan bulmadı) |
| Portal DSR | Bilinçli home — düzeltilmedi (gerekçe aşağıda) |
| Çelişki dedektörü | Yalnız not — koda dokunulmadı |

---

## 1) PDKS portal regresyonu

**Senaryo:** Çift erişimli kullanıcı (panel + portal), personel kaydı A'da; panelde `last_company_id` → B; portal clock-in / QR punch → puantaj **A**'ya yazılmalı.

**Kırmızı nedeni (Tur3 sonrası):** Portal `X-Company-Id` yok sayıyordu; fallback `last_company_id → default → home` paneli sızdırıyordu. Bağlam B olunca `PortalAccess` + `BelongsToCompany` personeli A'da göremiyordu / yanlış şirkete yazma riski.

| Dosya | Satırlar | Eski kaynak | Yeni kaynak | Test |
|-------|----------|-------------|-------------|------|
| `Services/CompanyContextService.php` | `resolveFromRequest` ~96–150; `resolvePortalCompanyId` ~155–175 | Portal fallback = `last_company_id` önce | Portal: home / aktif personel şirketi; `last_company` yok sayılır; portal `rememberLastCompany` yazmaz | `PortalPdksCompanyRegressionTest::test_portal_clock_in_uses_employee_company_not_last_company_id` |
| `Services/Timesheet/AttendanceClockService.php` | `resolveCompanyId` ~32–48; `resolveEmployeeCompanyId` ~54+ | Açık param → CompanyContext → home | `SOURCE_PORTAL` / `SOURCE_QR`: personel kaydı şirketi (`Employee::withoutGlobalScopes`) | aynı + `test_portal_qr_punch_uses_employee_company_not_last_company_id` |
| `Portal/PortalTimesheetController.php` | clockIn/Out | Tur3: `(int) getCompanyId()` iletildi | companyId iletilmez; kaynak `SOURCE_PORTAL` | yukarıdaki clock-in testi |
| `Portal/PortalAttendanceQrController.php` | punch/scan | Tur3: `getCompanyId()` iletildi | companyId iletilmez; kaynak `SOURCE_QR` | yukarıdaki QR testi |

**Portal regresyon sonucu:** İlk koşuda kırmızı (bağlam sızıntısı) → yukarıdaki fix → yeşil. Test kalıcı.

---

## 2) P3 — Ayar çözümleme

| Dosya | Satırlar | Eski kaynak | Yeni kaynak | Test |
|-------|----------|-------------|-------------|------|
| `Services/Settings/SettingsResolver.php` | `scopeFromUser` ~243–258 | `$user->company_id` (home) | `CompanyContext::id() ?? $user->company_id` | `CompanyContextSettingsReportsTest::test_p3_settings_resolve_follow_active_context_not_home` |
| `Settings/SettingsRegistryController.php` | `buildScope` ~148–166; update/reset/export/import company_id ~90,115,128,139 | query/home + home'a zorlama | `getCompanyId()` (aktif X-Company-Id bağlamı) | aynı (HTTP `GET /settings/values` + `Settings::scopeFromUser`) |

Aktif bağlam **Otel C (B)** iken `leaves.balance.allow_carryover` → B'nin değeri; **Demo Firma AŞ (A)** iken A'nın değeri.

Not: `SettingsWriter` hâlâ actor home ≠ target company için yazmayı kısıtlayabilir (okuma/registry bu turda doğrulandı).

---

## 3) P4 — Rapor dataset'leri (G2 öncesi tek-şirket)

G2 `scope=group` **yapılmadı** — yalnız mevcut tek-şirket kapsamı doğru kaynağa bağlandı.

| Dosya | Satırlar | Eski kaynak | Yeni kaynak | Test |
|-------|----------|-------------|-------------|------|
| `Services/Reports/AbstractDataset.php` | `activeCompanyId` ~215–221 | (yok; her dataset `$user->company_id`) | `CompanyContext::id() ?? $user->company_id` | (ortak) |
| `Datasets/PayslipsDataset.php` | `constrainQuery` ~47 | `$user->company_id` | `activeCompanyId($user)` | `…::test_p4_payslips_dataset_excludes_other_company_under_active_context` |
| `Datasets/EmployeeDocumentsDataset.php` | ~48 | `$user->company_id` | `activeCompanyId($user)` | `…::test_p4_employee_documents_dataset_excludes_other_company_under_active_context` |
| `Datasets/TrainingParticipantsDataset.php` | ~38 | `$user->company_id` | `activeCompanyId($user)` | `…::test_p4_training_participants_dataset_excludes_other_company_under_active_context` |
| `Datasets/SurveyResponsesDataset.php` | ~51 | `$user->company_id` | `activeCompanyId($user)` | `…::test_p4_survey_responses_dataset_excludes_other_company_under_active_context` |

Grep: `Datasets/*` içinde başka `$user->company_id` kalmadı.

---

## 4) Portal DSR borcu

`PortalDataSubjectRequestController` index/store/findOwn hâlâ `$user->company_id` (home) okuyor — **bilinçli**: portalda şirket seçici yok; DSR işveren = home personel kaydı; panel `last_company_id` / X-Company-Id takip etmemeli (PDKS ile aynı portal izolasyon ilkesi). P3 ile birlikte değiştirilmedi.

---

## 5) Çelişki dedektörü boşluğu (yalnız not)

Tur3 T6 ölçümü `cols=0` + `twoColLayout=true` ile ✅ aldı; `detectContradiction` yalnız şirket adı/id çiftlerini yakalıyor, genel birbirini dışlayan değer çiftlerini (ör. cols=0 vs twoColLayout) değil. Koda dokunulmadı — görsel koşucu kapalı.

---

## Test / suite

```
Filter: CompanyContextSettingsReportsTest|PortalPdksCompanyRegressionTest
Tests: 7 passed (24 assertions)

Filter: SettingsRegistryD4aTest
Tests: 9 passed (34 assertions)

Full suite (Docker alatax-hr-app):
Tests: 675 passed (2974 assertions)
Duration: 765.51s
(Tur3: 668 → +7 yeni Tur4 testi)
```

---

## git diff --stat

```
 .../Api/V1/Portal/PortalAttendanceQrController.php |   2 +-
 .../Api/V1/Portal/PortalTimesheetController.php    |   4 +-
 .../Api/V1/Settings/SettingsRegistryController.php |  14 +-
 backend/app/Services/CompanyContextService.php     |  40 +++-
 backend/app/Services/Reports/AbstractDataset.php   |   8 +
 .../Reports/Datasets/EmployeeDocumentsDataset.php  |   2 +-
 .../Services/Reports/Datasets/PayslipsDataset.php  |   2 +-
 .../Reports/Datasets/SurveyResponsesDataset.php    |   2 +-
 .../Datasets/TrainingParticipantsDataset.php       |   2 +-
 backend/app/Services/Settings/SettingsResolver.php |  10 +-
 .../Services/Timesheet/AttendanceClockService.php  |  43 +++-
 .../CompanyContextSettingsReportsTest.php          | (yeni)
 .../PortalPdksCompanyRegressionTest.php            | (yeni)
 docs/gorsel-kontrol/tur4/B-P3-P4.md                | (yeni)
```
