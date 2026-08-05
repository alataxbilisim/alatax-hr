# Tur5 — Bağlam dönüşümü kapanışı

**Branch:** `faz4-form-engine` · **DB wipe:** yok · **Push:** yok · Görsel koşucu: dokunulmadı

## Özet

| Madde | Sonuç |
|-------|--------|
| QR/portal punch güvenlik | Personel **auth `user_id`**’den; istek `employee_id` yok sayılır — test yeşil |
| Home ≠ personel şirketi | Önce home eşleşen personel; yoksa personel şirketi kazanır |
| SettingsWriter | Membership ile aktif bağlama yazma — asimetrisi kapandı |
| Dataset registry izolasyon | 11/11 yeşil (BelongsToCompany + Tur4 constrainQuery) |
| `.cursorrules` + `SISTEM_ISLEYIS.md` | Portal / panel bağlam kuralları kalıcı |
| G1/G2 belge | `FAZ_G_RAPOR.md` + `GUNCEL_DURUM_RAPORU.md` senkron |

---

## 1) QR/portal punch güvenlik denetimi

**Cevap (kod):** Personel **`auth()->user()` / `$request->user()` ilişkisinden** çözülür; istekten `employee_id` okunmaz.

| Katman | Kanıt |
|--------|--------|
| `PortalTimesheetController` | `clockIn`/`clockOut` → `$this->clock->…($request->user(), …)` — body’de employee_id yok |
| `PortalAttendanceQrController` | `scan` → `$user = $request->user()`; QR payload yalnız `token` (+ lat/long) |
| `AttendanceKioskTokenService::consume` | Payload: `jti`, `company_id`, `branch_id`, `exp` — **employee_id yok**; `actor->company_id` ile token firması eşleşmeli |
| `AttendanceClockService::resolveEmployeeCompanyId` | `Employee::withoutGlobalScopes()->where('user_id', $user->id)` — scope bypass yalnız auth kullanıcısının kaydı |

**Öncelik (tek cümle):** Portal/punch bağlamında önce `users.company_id` (home) ile eşleşen aktif personel şirketi; yoksa auth kullanıcısının aktif personel kaydının şirketi kazanır (`resolvePortalCompanyId` + `resolveEmployeeCompanyId`); istekten id okunmaz.

| Test | Sonuç |
|------|--------|
| `PortalPdksPunchSecurityTest::test_portal_clock_in_ignores_foreign_employee_id_in_request` | ✅ A kullanıcısı + B personel id body → kayıt A’ya, B user’a yazılmaz |
| `…::test_portal_qr_rejects_other_company_token_for_company_a_user` | ✅ B QR tokeni A kullanıcısına 422 |
| `…::test_portal_clock_in_uses_employee_company_when_home_differs` | ✅ Home A, personel yalnız B → puantaj B |

Kod dokunuşu: `CompanyContextService::resolvePortalCompanyId` home’u körü körüne seçmiyor; home’da personel yoksa personel şirketine düşüyor (PortalAccess 403 önlendi).

---

## 2) SettingsWriter — yazma yolu

| Dosya | Satırlar | Eski | Yeni | Test |
|-------|----------|------|------|------|
| `Services/Settings/SettingsWriter.php` | `put`/`reset`/`export`/`import` + `assertCanAccessCompany` | `(int) actor->company_id !== companyId` → 403 | home **veya** `hasMembership` | `SettingsWriterActiveContextTest::test_settings_write_follows_active_context_not_home` |

Aktif bağlam Otel C (B) iken `PUT /settings/values` → satır **B**’ye yazılır; A’ya yazılmaz / sessiz yutulmaz.

---

## 3) Dataset registry izolasyon

`DatasetRegistryIsolationTest` — `ApprovalEntityIsolationTest` deseni; provider `DatasetRegistry::all()` ile senkron.

| Dataset | Koruma | Sonuç |
|---------|--------|--------|
| employees, leave_requests, leave_balances, expense_claims, job_applications, attendance_records, assets | BelongsToCompany + CompanyContext | ✅ |
| payslips, employee_documents, training_participants, survey_responses | Tur4 `activeCompanyId` / constrainQuery | ✅ |

---

## 4) Kalıcı kurallar

- `.cursorrules` — Portal yolu + Panel/operasyonel yol (2 satır)
- `docs/SISTEM_ISLEYIS.md` — AŞAMA 1 altına aynı kurallar

---

## 5) Belge senkronu

- `docs/FAZ_G_RAPOR.md` — G1’de Dashboard/KVKK/PDKS/Settings/Dataset atlandığı; Tur2–Tur5’te kapatıldığı
- `docs/GUNCEL_DURUM_RAPORU.md` — test sayısı güncel; G1 ✅; QA-4 demo/retention düşürüldü; L2 Select ellipsis/title eklendi; G2 açık

---

## Test / suite

```
Filter (Tur5 + regresyon): yeşil
Full suite (Docker alatax-hr-app):
Tests: 690 passed (3022 assertions)
Duration: 1258.55s
(Tur4: 675 → +15 yeni Tur5 testi)
```

---

## git diff --stat

```
 .cursorrules                                       |  2 ++
 backend/app/Services/CompanyContextService.php     | 22 +++++++++-----
 backend/app/Services/Settings/SettingsWriter.php   | 35 ++++++++++++++--------
 .../Services/Timesheet/AttendanceClockService.php  |  3 +-
 docs/FAZ_G_RAPOR.md                                |  2 ++
 docs/GUNCEL_DURUM_RAPORU.md                        | 13 ++++----
 docs/SISTEM_ISLEYIS.md                             |  4 +++
 .../DatasetRegistryIsolationTest.php               | (yeni)
 .../PortalPdksPunchSecurityTest.php                | (yeni)
 .../SettingsWriterActiveContextTest.php            | (yeni)
 docs/gorsel-kontrol/tur5/KAPANIS.md                | (yeni)
```
