# FAZ 4A — Form Engine Teşhis

**Tarih:** 15 Temmuz 2026  
**Branch:** `faz4-form-engine`  
**Kapsam:** Yalnızca okuma / teşhis. Uygulama, migration, route, push yok.  
**Kaynak:** Kod + migration + FE sayfaları + yerel DB salt-okunur sayım.

---

## Özet (tek cümle)

Çalışan tohum: **tenant-özel `custom_field_definitions` + employee `custom_fields` jsonb + admin CRUD UI + `CustomFieldRenderer` + sunucu tip/zorunluluk validasyonu**; Form Engine’in Zoho parçaları (sistem alan metadata, layout, koşullu görünürlük, alan izin→render, liste görünümü, tek motor) **yok**. Üstüne üç paralel form şeması daha var.

---

## 1. `custom_field_definitions`

**Migration:** `backend/database/migrations/2025_01_25_000001_create_custom_field_definitions_table.php`

| Kolon | Tip / not |
|-------|-----------|
| `id` | PK |
| `company_id` | FK → companies, cascade — **tenant-özel** |
| `entity_type` | string (`employee`, `leave_request`, …) |
| `field_key` | string (unique ile birlikte) |
| `field_label` | string |
| `field_type` | string |
| `field_options` | jsonb nullable — `[{value, label}]` |
| `is_required` | bool default false |
| `is_active` | bool default true |
| `sort_order` | int default 0 |
| `validation_rules` | jsonb nullable — yorum: `['min:3',…]`; **runtime’da uygulanmıyor** |
| `placeholder`, `help_text`, `default_value` | nullable |
| `created_by`, `updated_by` | nullable FK users |
| timestamps + softDeletes | var |
| unique | `(company_id, entity_type, field_key)` |
| index | `(company_id, entity_type, is_active)` |

**Model sabitleri (`CustomFieldDefinition`):**

- **Tipler:** `text`, `number`, `date`, `datetime`, `select`, `checkbox`, `radio`, `textarea`, `file`, `email`, `phone`, `url`
- **Entity:** `employee`, `leave_request`, `training`, `performance`, `document`, `expense`, `job_application`, `asset`
- `validateValue()` içinde `validation_rules` için `// TODO: Implement validation logic`

**FE hayalet alanlar (DB’de yok):** Admin UI (`ModuleCustomFieldsPage` / eski `CustomFieldsPage`) `is_unique`, `show_in_list`, `show_in_filter` gönderiyor; BE `store`/`update` bunları validate etmiyor → **sessizce düşer**. Liste/filtre davranışı yok.

**Seed:** `CustomFieldDefinition` seed’i yok (grep: 0).

**Yerel DB sayımı (salt okuma, 15 Temmuz 2026):**  
`custom_field_definitions = 0`, `employees` dolu `custom_fields` = 0, `application_forms = 0`, `request_types` dolu `form_fields` = 0.  
⚠️ **BELİRSİZ / ortam notu:** Yerel `.env` `mysql` connection kullanıyor; proje hedefi PostgreSQL/`jsonb`. Bu sayım **bu makinedeki mevcut DB** için geçerli; prod/staging ayrı doğrulanmalı.

---

## 2. `CustomFieldRenderer` (@shared)

**Dosya:** `frontend/packages/shared/src/components/CustomFieldRenderer.tsx`

| Konu | Durum |
|------|--------|
| Render edilen tipler | text, email, phone, url, number, date, datetime, textarea, select, checkbox, radio, file (+ default → text) |
| react-hook-form | **Yok** — controlled `values` + `onChange` |
| zod | **Yok** |
| Validasyon FE | yalnızca HTML `required`; select’te `required` yok |
| `file` | stub: `file.name` string yazar; upload yok |
| `readonly` | prop var |

**Kullanım:**

1. `EmployeeForm` — sekme **Özel Alanlar**; `customFieldsApi.getAll('employee')`; submit’te `formData.custom_fields` → `employeesApi.create/update`.
2. `CustomFieldsTab` (detay) — **readonly** gösterim; `onChange` no-op.

**EmployeeForm:** `useState` form; **react-hook-form / zod yok**. Standart alanlar da hardcoded JSX sekmeleri.

**Uçtan uca (employee):** Tanım aktif + formda doldurulursa → BE `CustomFieldValidationService` → `employees.custom_fields` jsonb. Feature test: `EmployeeCustomFieldValidationTest`. Kod yolu **çalışır**; yerel DB’de tanım/veri **0**.

**Admin UI:** Evet — seed-only değil.  
- Stüdyo index: `/settings/custom-fields` → modül linkleri  
- Modül: `/employees/custom-fields`, `/leaves/custom-fields`, … → `ModuleCustomFieldsPage` (CRUD + reorder + toggle)  
- Eski `settings/CustomFieldsPage.tsx` **App.tsx’te route yok** (ölü/paralel kopya).

---

## 3. Backend

### `custom_fields` jsonb depolama

| Model / tablo | `custom_fields` jsonb |
|---------------|----------------------|
| `employees` | **Var** (baseline + update migration) |
| Diğer modeller (LeaveRequest, Document, …) | **Yok** (model grep yalnızca Employee) |

Sonuç: Admin UI leave/document/… için **tanım oluşturabilir**; değer yazacak kolon / controller yolu **yok** (tanım-only kör uç).

### CRUD API

`CustomFieldController` + `routes/api.php`:

- index / show / store / update / destroy / reorder  
- field-types / entity-types  
- Permission: `management.custom_fields.*` (+ employees tarafında `GET employees/custom-fields` ayrı)

### Validasyon

| Katman | Ne var |
|--------|--------|
| `CustomFieldValidationService` | Yalnız **employee**; aktif tanımlara göre required + tip (number/email/url/date/datetime/checkbox/select/radio) |
| `validation_rules` jsonb | **Uygulanmıyor** |
| Employee store/update | Elle Laravel rules (standart kolonlar) + service (custom_fields) |
| Tanımdan Laravel rules / FE zod üretimi | **Yok** |

### Alan izinleri (Faz 2)

- Tablo `field_permissions`: **yok** (PermissionSeeder yorumu: “Faz 4’e”).
- Gerçek: `EmployeeSensitiveFieldService` + Spatie izinler (`employees.salary.*`, `employees.tckn.view`) → Resource’da anahtar gizleme / yazmada strip.
- **CustomFieldRenderer / form layout’a bağlı değil.**

---

## 4. Boşluk tablosu (Form Engine vs mevcut)

| Özellik | Mevcut mu? | Gereken (ROADMAP 4A) |
|---------|------------|----------------------|
| Tenant-özel ek custom field tanımı | Evet (`company_id` + CRUD) | Temel — kullanılabilir tohum |
| Değer saklama (employee) | Evet (`employees.custom_fields`) | Evet |
| Değer saklama (diğer entity) | Hayır | Entity jsonb veya eşdeğer |
| Standart (sistem) alanları metadata (rename / gizle / zorunlu; silme yok) | Hayır — form JSX hardcoded | `form_definitions` + sistem alan kayıtları |
| Form layout (bölüm / satır / sıra) | Hayır (`form_definitions` yok; yalnızca custom `sort_order`) | Layout JSONB |
| Koşullu görünürlük | Hayır | v1 kural motoru |
| Alan izinleri → render (gizli / RO / RW) | Kısmi: maaş/TCKN API strip; form engine’e bağlı değil | Renderer + tanım entegrasyonu |
| Liste görünümleri (kolon / kayıtlı view) | Hayır (`show_in_list` FE hayalet) | DataTable + kayıtlı görünüm |
| FE FormEngine (rhf + zod + registry) | Hayır (`CustomFieldRenderer` sadece ek alan) | Personel formu ilk geçiş |
| Validasyon tek kaynak (BE rules + FE zod) | Hayır | Tanım endpoint’inden üret |
| Tip: tckn / lookup / decimal / multiselect | Hayır (planlı) | Alan tipi v1 genişletme |
| `file` gerçek upload | Hayır (stub) | Storage + path |
| Admin tanım UI | Evet (modül sayfaları) | Stüdyo’ya bağlanır |
| Seed / örnek tanımlar | Hayır | Opsiyonel demo |

### Paralel form altyapıları (kaç tane?)

| # | Altyapı | Tanım | Değer | FE |
|---|---------|-------|-------|-----|
| 1 | **Custom fields** | `custom_field_definitions` | `employees.custom_fields` | Renderer + EmployeeForm + admin CRUD |
| 2 | **Recruitment form builder** | `application_forms.fields` jsonb | `job_applications.form_data` | BE CRUD var; aktif company FE **yok** (FormBuilder `_archive_old_app`) |
| 3 | **Portal talep türleri** | `request_types.form_fields` | `employee_requests.form_data` | Portal API `form_fields` döner / `form_data` kabul; portal FE’de form_fields render **grep 0** |

**Rapt etme (teşhis notu, strateji değil):** Üç şema ayrı JSON alan sözleşmesi kullanıyor. Tek motor için ortak alan/registry sözleşmesi + migration/adapter gerekir; bugün paylaşılan yalnızca benzer “type/label/required/options” fikri.

---

## 5. TR alan seti

| Alan | Durum | Not |
|------|--------|-----|
| **TCKN** | Standart kolon `employees.national_id` | Validasyon: `nullable\|string\|max:20` — **algoritmik TCKN doğrulama yok**. İzin: `employees.tckn.view` (+ own). Custom field değil. |
| **SGK sicil** | Standart `sgk_number`, `sgk_start_date` | Maaş grubu hassas alan (`EmployeeSensitiveFieldService::SALARY_FIELDS`). Formda SGK sekmesi. |
| **SGK meslek kodu** | `positions.sgk_occupation_code` | Personel alan seti değil; pozisyon kataloğu. |
| **İŞKUR kodu** | **Yok** | Kodda `iskur` / `işkur` eşleşmesi yok (standart da custom da değil). |

---

## Tohum: ne kadar kullanılabilir?

**Kullanılabilir (doğrudan Form Engine üzerine):**

- Tablo + model + BelongsToCompany + soft delete  
- CRUD API + permission isimleri  
- `CustomFieldRenderer` (alan tipi registry iskeleti)  
- Employee jsonb + `CustomFieldValidationService` + testler  
- Admin CRUD UI iskeleti (hayalet kolonlar temizlenmeli)

**Kullanılamaz / yanıltıcı (şimdilik):**

- Non-employee entity_type’lar (tanım var, depo yok)  
- `validation_rules`, `is_unique`, `show_in_list`, `show_in_filter`  
- Sistem alan özelleştirme / layout / conditional / list views  
- `application_forms` + `request_types.form_fields` (ayrı dünyalar)  
- EmployeeForm’un tamamını “metadata-driven” saymak (yalnız “Özel Alanlar” sekmesi)

**Eksik çekirdek (Form Engine’e geçiş için):** `form_definitions` (veya eşdeğer), sistem alan kataloğu, FormEngine bileşeni, tek validasyon üretimi, diğer entity depoları, üç form şemasının birleştirme kararı.

---

## Kanıt dosyaları (özet)

- Migration: `2025_01_25_000001_create_custom_field_definitions_table.php`  
- Model/Service/Controller: `CustomFieldDefinition`, `CustomFieldValidationService`, `CustomFieldController`, `EmployeeController`  
- FE: `CustomFieldRenderer.tsx`, `EmployeeForm.tsx`, `ModuleCustomFieldsPage.tsx`, `CustomFieldsTab.tsx`  
- Paralel: `application_forms` migration + `FormBuilderController`; `request_types.form_fields` + `PortalRequestController`  
- Hassas alan: `EmployeeSensitiveFieldService`, `EmployeeResource`  
- Test: `EmployeeCustomFieldValidationTest.php`  
- ROADMAP hedef: `docs/ROADMAP.md` §4A  

---

---

## 4A-1 — Veri modeli + sistem alan metadata (uygulandı)

**Tarih:** 15 Temmuz 2026  
**Branch:** `faz4-form-engine`  
**Kapsam:** Backend tanım katmanı only — EmployeeForm / CustomFieldRenderer değişmedi.

### Karar: `custom_field_definitions` genişletildi (ayrı `form_fields` tablosu yok)

| Neden | Sonuç |
|-------|--------|
| Tohum sağlam; unique/CRUD/testler company_id ile çalışıyor | Yeni kolonlar eklendi; mevcut custom CRUD `customOnly()` ile sistem satırlarını görmez |
| Sistem satırları `company_id=null` (Lookup deseni) | Eski `where('company_id', …)` ekranı bozulmaz |
| Firma rename/gizle/zorunlu | Firma override satırı (`company_id=X`, `is_system=true`, aynı `system_key`) |
| Layout | Yeni tablo `form_definitions` (jsonb `layout`) |

### Eklenen kolonlar (`custom_field_definitions`)

`is_system`, `system_key`, `label_override`, `is_hidden`, `is_required_override`, `field_permission` (nullable CHECK: readonly|hidden). `sort_order` zaten vardı. `company_id` → nullable + pgsql partial unique.

### Sistem alan seed

- Seeder: `EmployeeFormFieldSeeder` → `FormFieldCatalogService::seedSystemCatalog()` (idempotent)
- **36** employee sistem alanı (EmployeeForm sekmeleri ile hizalı; `name` = Ad Soyad; first_name/last_name yok)
- `national_id` → `validation_rules: ['tckn']` + `App\Rules\TurkishNationalId`
- Varsayılan layout: `form_definitions` company_id=null

### API / permission

| Route | Permission |
|-------|------------|
| `GET /api/v1/form-definitions/{entityType}` | `management.forms.view` |
| `PUT /api/v1/form-definitions/{entityType}` | `management.forms.edit` |

Sistem alanı silme: PUT `delete` → 422; `DELETE /custom-fields/{id}` sistem için 422.

### validation_rules

- Custom jsonb: `CustomFieldValidationService` (tip + rules; yalnız `is_system=false`)
- Standart kolonlar: strip sonrası `FormFieldCatalogService::validateStandardFields` (TCKN dahil)
- FE zod: 4A-2

### Test kanıtı (Docker `alatax-hr-app`, pgsql)

- `getDatabaseName() = alatax_hr_testing`, driver `pgsql` (FormDefinitionApiTest)
- FormDefinitionApiTest: 9 passed (401/403/happy/tenant/TCKN/idempotent)
- Regresyon: EmployeeCustomFieldValidation + EmployeeFieldPermission + PolicyDataScopeWave2 yeşil
- **Tam suite: 347 passed / 0 fail**
- `alatax_hr` wipe yok; PUSH yok

### Bilinçli sınırlar (4A-2+)

- Render hâlâ hardcoded EmployeeForm
- `field_permission` metadata hazır; Spatie salary/tckn render’a bağlı değil
- Yalnız `entity_type=employee` form-definitions desteklenir

---

---

## 4A-2 — FormEngine render + layout editörü + personel pilotu

**Tarih:** 15 Temmuz 2026  
**Branch:** `faz4-form-engine`  
**Kapsam:** FE render motoru + stüdyo editör + personel beta yolu. Eski `EmployeeForm` **dokunulmadı**.

### Karar: ayrı route (flag değil)

| Yol | Davranış |
|-----|----------|
| `/employees/new`, `/employees/:id/edit` | Klasik `EmployeeForm` (varsayılan) |
| `/employees/form-engine/new`, `/employees/form-engine/:id/edit` | FormEngine pilotu |
| `/settings/forms/employee` | Layout / alan metadata editörü |

Neden: eski form varsayılan kalır; geri dönüş tek tık; auto-flag riski yok.

### ADIM sonuçları

| Adım | Durum | Kanıt |
|------|--------|-------|
| 1 FormEngine (@shared) | ✅ | `packages/shared/src/form-engine/*` — rhf + zod, Select + CustomFieldRenderer reuse |
| 2 field_permission → render | ✅ | hidden→yok, readonly→disabled + payload’dan çıkar; BE strip korunur |
| 3 Layout editörü | ✅ | Sıralı liste (↑↓); rename/gizle/zorunlu/permission; sistem silinemez |
| 4 Personel pilotu | ✅ | Ayrı route; create/update → system + `custom_fields` jsonb |
| 5 Test | ✅ | **349 passed / 0 fail** (pgsql `alatax_hr_testing`); company **tsc 0** |

### Zod / submit

- `buildZodSchema`: effective_required + tckn (+ tip)
- `buildSubmitPayload`: hidden/permission-hidden yok; readonly gönderilmez

### i18n / nav

- `common.formEngine.*`, `studio.formLayout`
- Stüdyo menü: Personel form düzeni (`management.forms`)
- Liste: “Yeni (Form Engine)” + satırda “FE”

### Görsel kontrol (kullanıcı)

1. `/settings/forms/employee` → bir alanı rename + gizle → Kaydet  
2. `/employees/form-engine/new` → gizlenen alan görünmemeli; etiket override görülmeli  
3. Klasik `/employees/new` hâlâ çalışmalı  

### Doğrulama

- DB wipe yok; PUSH yok  
- Docker’da ekleyici migrate + `EmployeeFormFieldSeeder` (36 alan) uygulandı (`alatax_hr`)  
- Suite: 349 passed  

---

## 4A-2 hotfix — form-engine boş sayfa / Sunucu hatası

**Tarih:** 15 Temmuz 2026  
**Branch:** `faz4-form-engine`  
**Tetik:** `/employees/form-engine/new` boş + “Sunucu hatası” toast; `/settings/forms/employee` çalışıyor.

### ADIM 0 — Gerçek hata

| Bulgu | Sonuç |
|-------|--------|
| Stack | `Class "App\Http\Controllers\Api\V1\Employee" not found` @ `DepartmentController.php:182` (`getManagers`) |
| Endpoint | `GET /api/v1/departments/managers` → **500** (eksik `use App\Models\Employee`) |
| `GET /api/v1/form-definitions/employee` | **200**, 36 alan (alatax_hr, company 70) — seed/form-definitions **değil** |
| Editör vs pilot | Editör yalnız `form-definitions`. Pilot ek olarak lookups + `/employees/departments|managers` + branches + positions. Axios global interceptor her 500’de “Sunucu hatası…” toast basar. |

### ADIM 1 — Dev veri (alatax_hr, salt okuma)

- `custom_field_definitions` sistem (`is_system=true`, `company_id IS NULL`): **36**
- `form_definitions` sistem (`company_id IS NULL`, employee): **1** satır var
- Firma kopyası (69 / 70): **0** — beklenen; GET sistem varsayılanına düşüyor
- Seed eksikliği yok; seeder yeniden çalıştırılmadı

### ADIM 2 — Düzeltmeler

1. **BE:** `DepartmentController` → `use App\Models\Employee` (kök 500)
2. **FE:** `EmployeeFormEnginePage` — hata durumunda otomatik `navigate` yok; anlamlı mesaj + **Tekrar dene** + klasik forma link; relation/lookup `allSettled` (tek companion 500 tüm boot’u düşürmez)
3. **Test:** `test_get_returns_catalog_default_when_no_form_definition_rows` + `DepartmentManagersApiTest` (401/403/200 + tenant)

### ADIM 3 — Doğrulama

- `GET .../form-definitions/employee` → 200  
- `GET .../departments/managers` → 200 (fix sonrası)  
- Suite: **354 passed / 0 fail** (`alatax_hr_testing`); B-DB guard sağlam; company **tsc 0**  
- DB wipe yok; PUSH yok; B-DB guard’a dokunulmadı  

---

*4A-2 uygulama kaydı. Big-bang geçiş yok; klasik form varsayılan.*
*4A-2 hotfix: departments/managers 500 + FE boş-sayfa UX.*
