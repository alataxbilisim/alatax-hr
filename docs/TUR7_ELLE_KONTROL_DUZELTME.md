# Tur 7 — Elle kontrol düzeltmeleri

**Branch:** `faz4-form-engine` · **DB wipe yok** · **push yok**  
**Tarih:** 2026-08-05  
**Suite:** **721 passed** (716 + 5 yeni)

---

## git diff --stat (özet)

```
backend/app/Http/Controllers/Api/V1/EmployeeController.php
backend/app/Http/Resources/EmployeeResource.php
backend/app/Models/Dashboard.php
backend/app/Models/Employee.php
backend/app/Services/Reports/DashboardService.php
backend/app/Support/PanelAccess.php
backend/database/migrations/2026_08_05_140000_add_full_name_to_employees_table.php
backend/tests/Feature/DashboardV2Test.php
backend/tests/Feature/EmployeeNamePersistTest.php
backend/tests/Feature/PanelAccessControlTest.php
frontend/apps/company/src/components/EmployeeForm.tsx
frontend/apps/company/src/components/layout/moduleNav.ts
frontend/apps/company/src/pages/dashboards/DashboardViewPage.tsx
frontend/apps/company/src/pages/employees/EmployeeFormEnginePage.tsx
frontend/apps/company/src/pages/employees/EmployeesPage.tsx
frontend/packages/shared/src/constants/permissions.ts
frontend/packages/shared/src/i18n/locales/tr/common.json
frontend/packages/shared/src/styles/components.css
```

---

## 1) 🔴 Personel Ad Soyad kaydedilmiyor

| | |
|--|--|
| **Tekrar** | `/employees/new` → Sicil + Ad Soyad → Kaydet; düzenlemede tekrar yaz |
| **Kök neden** | Form `name` gönderiyor; `Employee` tablosunda ad yoktu — yalnızca portal açılınca `users.name` yazılıyordu. Update’te `name` validasyonda yoktu → yutuluyordu. `EmployeeController@store` ~347–386 / `@update` ~425–508; `Employee::$fillable` |
| **Düzeltme** | `employees.full_name` migration + fillable; store/update `name` → `full_name`; user varsa sync; Resource `full_name` + `name` (display); liste/form okuma |
| **Test** | `EmployeeNamePersistTest` (create / update / user sync) |

---

## 2) 🔴 Sınırlı rol → listeden kaybolma + panel login engeli

| | |
|--|--|
| **Tekrar** | Roller’de yalnız `employees.list.view` ile “test” rolü → kullanıcıya ata |
| **(a) Liste** | `UserController@index` → `PanelAccess::constrainUsersQuery` — eski: portal-self dışı izin yoksa hariç |
| **(b) Login** | `AuthController` → `PanelAccess::has` false → `panel_access_denied` |
| **Kök neden** | `employees.list.view` ∈ `PORTAL_SELF_PERMISSIONS` → custom dar rol = portal-only sanılıyordu (`PanelAccess.php`) |
| **Düzeltme** | `employee` **rolü dışı** herhangi bir Spatie rolü = panel erişimi (FE `hasPanelAccess` aynası). Portal-only = yalnızca `employee` rolü. Kurtarma: rol ataması sonrası kullanıcı tekrar `/users`’da |
| **Test** | `PanelAccessControlTest::test_limited_custom_role_stays_in_users_list_and_can_login` (+ mevcut Y2 portal-only korunur) |

---

## 3) 🟠 Select key = etiket → pozisyon birleşmesi

| | |
|--|--|
| **Tekrar** | Personel formu Pozisyon: aynı adlı DEMO_POS_07 + YAZ_KID |
| **Kök neden** | `EmployeeForm.tsx` `value: p.name` — Select key/value çakışması (Select.tsx zaten `opt.value` kullanıyor) |
| **Düzeltme** | `value = p.code` (benzersiz); onChange’de DB’ye **ad** yazılır. Etiket `KOD — Ad` bilinçli (çakışmada ayırt edilir; FormEngine ile uyumlu) |
| **Test** | FE; BE suite’te name/panel/dashboard. Manuel: iki aynı adlı pozisyon ayrı satır |

---

## 4) 🟠 Select uzun etiket taşması (L2)

| | |
|--|--|
| **Tekrar** | Uzun pozisyon etiketi seçiliyken kutu yatay büyür |
| **Kök neden** | `.ax-select-value-text` ellipsis vardı; trigger/wrap `max-width` / `overflow` eksikti (`components.css`) |
| **Düzeltme** | `.ax-select-wrap` + `.ax-select-trigger`: `max-width:100%`, `min-width:0`, `overflow:hidden`; `title` tooltip mevcut |
| **Test** | Görsel / CSS; birleşme düzelince metin zaten kısalır |

---

## 5) 🟠 Pano kaydet 422 + “yetki” mesajı

| | |
|--|--|
| **Tekrar** | `/dashboards/:id` düzenle → widget taşı → Kaydet |
| **Kök neden** | `DashboardService::update` yetki/sistem hatalarını **ValidationException (422)** ile `"…yetkiniz yok"` diye atıyordu; FE izin varken `canEdit` yalnız sahibi kabul ediyordu; sistem panosu tamamen kilitliydi |
| **Düzeltme** | Yetki → **403**; firma panosu + `reports.dashboards.edit` → `canEdit`; firma içi sistem panosu layout kaydı OK; FE Infinity→sayı; 422’de validation mesajı |
| **Test** | `DashboardV2Test::test_owner_can_save_layout_and_stranger_gets_403` |

---

## 6) 🟡 Analitik → Raporlar Panolar’a gidiyor

| | |
|--|--|
| **Tekrar** | Sol menü Analitik → **Raporlar** |
| **Kök neden** | `nav.analyticsReports` → `/analytics` → `AnalyticsPage` `navigate(/dashboards/:id)` (sistem panosu) |
| **Düzeltme** | Menüde **Raporlar** → `/reports`; `/analytics` etiketi **Analitik özeti** |
| **Test** | Route/nav eşlemesi; sayfa `/reports` açılır |

---

## 7) Konsol — ECharts (borç)

`[ECharts] grid.containLabel` → `grid.outerBounds` önerisi. **Bu turda dokunulmadı.**

---

## Bitiş kontrolü

| Madde | Durum |
|-------|--------|
| 1–6 | Düzeltildi + test (1,2,5 BE; 3–4 FE; 6 nav) |
| Suite | **721 passed** |
| Commit | lokal; **push yok** |
