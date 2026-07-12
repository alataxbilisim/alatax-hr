# FAZ 4 — Teşhis Raporu

**Branch:** `faz4-form-engine` (temel: `faz3-tasarim` / `b83da8b`; Faz 3 kodu üstünde)  
**Tarih:** 12 Temmuz 2026  
**Kapsam:** ADIM 1 — teşhis + vizyon kaydı. **Uygulama yok.**

---

## Özet karar (en önemli soru)

**Picklist/combobox değerleri için tutarlı, firma-yönetilebilir bir altyapı VAR MI?**

# **HAYIR — dağınık.**

Sistem üç ayrı desende yönetiliyor; ortak `picklists` / `lookup_values` tablosu, generic API veya paylaşılan “seçenek listesi” UI’ı **yok**:

| Desen | Örnek | Firma yönetebilir mi? |
|-------|--------|------------------------|
| PHP `PortableEnum` + migration string + FE hardcoded `<option>` | Personel durumu, kanban aşamaları, sözleşme tipi | Hayır |
| Modül başına DB tablosu + ayrı CRUD | `leave_types`, `departments`, `document_categories`, `asset_categories` | Kısmen (UI varsa) |
| DB var, Company UI yok | `expense_categories`, `request_types` | Hayır (Portal sadece okur) |

**İş boyutu:** Picklist birleştirme + Ayarlar Stüdyosu, Form Engine’in yanında **ayrı büyük iş kalemi**. Özel alan altyapısı ise **kısmi iskelet** (personelde değer saklama var; validasyon/FE-BE sözleşmesi kırık).

Kullanıcı vizyonu `docs/ROADMAP.md` Faz 4 altına kaydedildi (Ayarlar ≠ Yönetim Stüdyosu; sıra: özel alan+picklist → izin/kanban → stüdyo iskeleti → form düzeni).

---

## A. Özel Alan (custom field) altyapısı

### Mevcut ne var?

**Tablo:** `custom_field_definitions`  
Migration: `backend/database/migrations/2025_01_25_000001_create_custom_field_definitions_table.php`

| Kolon | Not |
|-------|-----|
| `company_id` | Tenant (FK, BelongsToCompany) |
| `entity_type` | `employee`, `leave_request`, `training`, `performance`, `document`, `expense`, `job_application`, `asset` |
| `field_key` / `field_label` / `field_type` | Unique `(company_id, entity_type, field_key)` |
| `field_options` | jsonb — select/radio için `[{value, label}]` beklenir |
| `is_required`, `is_active`, `sort_order` | |
| `validation_rules` | jsonb — **zorlanmıyor** (model’de TODO) |
| softDeletes + audit kolonları | Auditable trait yok |

**Model:** `backend/app/Models/CustomFieldDefinition.php`  
**API:** `GET/POST/PUT/DELETE /api/v1/custom-fields` (+ reorder, field-types, entity-types) — `CustomFieldController`  
**İzinler:** `management.custom_fields.*` + modül bazlı `{module}.custom_fields.*` (PermissionSeeder)

**Değer saklama:** Yalnızca **`employees.custom_fields` jsonb**. Diğer entity’lerde kolon **yok**.

**Renderer:** `frontend/packages/shared/src/components/CustomFieldRenderer.tsx`  
Tipler: text, number, date, select, checkbox, radio, textarea, file (dosya adı stub), email, phone, url.  
Validasyon: HTML `required`; zod/RHF yok; `validation_rules` kullanılmıyor.

**Admin UI:**
- Modül sayfaları: `/employees/custom-fields` vb. → `ModuleCustomFieldsPage.tsx` (7 modül)
- Orphan: `/settings/custom-fields` → `CustomFieldsPage.tsx` (sidebar’da yok)
- **Seeder veri yok** — alanlar elle/API ile oluşturulmalı

### Personel formu “Özel Alanlar” sekmesi — gerçekten çalışıyor mu?

| Adım | Durum |
|------|--------|
| Admin UI’dan alan tanımı | UI var; **select options payload FE↔BE uyumsuz** (`options: string[]` vs `field_options: [{value,label}]`); UI’da `datetime`/`multiselect`/`color` var, BE reddeder |
| Formda sekme + yükleme | `EmployeeForm` → `customFieldsApi.getAll('employee')` |
| Kaydet → jsonb | Evet — create/update body’de `custom_fields`; `EmployeeResource` döner |
| Detayda gösterim | **Yok** (`EmployeeDetailPage` göstermiyor) |
| Sunucu validasyonu (zorunlu/tip) | **Yok** — `nullable|array` |

**Verdict:** Temel text/number için “tanımla → formda doldur → kaydet → düzenlemede geri gel” **kısmen çalışır**. Select/opsiyonlu alanlar ve çoklu tip uyumsuzlukları kırık. İzin talebi formu custom field şeması yanlış + BE kolonu yok → **çalışmaz**.

### Ne eksik / tutarsız?

1. FE–BE sözleşme (options, UI-only kolonlar `is_unique`/`show_in_list`)
2. Tanımdan üretilen validasyon (Laravel + zod)
3. Diğer entity’lerde değer kolonu + Form entegrasyonu
4. Seed / demo alan yok
5. Feature test (CRUD + tenant + değer) yok; sadece permission wave testi
6. Form Engine (`form_definitions`, layout, koşullu görünürlük) **yok** — ROADMAP 4A

---

## B. Picklist / Lookup (combobox içerikleri)

### Madde madde

| # | Liste | Kaynak | company_id | Yönetilebilir UI |
|---|--------|--------|------------|------------------|
| 1 | Personel durum (Aktif/İzinli/Askıda/Çıkmış) | Hardcoded FE + BE `in:` validation | satırda | **Hayır** |
| 2 | İzin türleri | DB `leave_types` + LeaveTypeSeeder (firma başına 8 tür) | **Evet** | **Evet** — Leaves → Türler |
| 3 | Kanban aşamaları (8 kolon) | **FE hardcoded** `ApplicationsPage.statusColumns` + PHP `JobApplicationStatus` | N/A | **Hayır** — isim/sıra/renk değiştirilemez |
| 4a | Departman | DB `departments` | Evet | Evet — DepartmentsPage |
| 4b | Pozisyon (personel) | Serbest metin `employees.position` | — | Hayır (input); `job_positions` = ilan, master değil |
| 4c | Masraf kategorisi | DB `expense_categories` | Evet | **Hayır** (Company UI yok; Portal okur) |
| 4d | Doküman kategorisi | DB `document_categories` | Evet | Evet — Documents |
| 4e | Talep tipi | DB `request_types` | Evet | **Hayır** (Company UI yok; Portal okur) |

### Diğer örnekler (dağınık)

- Varlık durum/kondisyon: enum + FE hardcoded (`retired` vs BE `disposed` tutarsızlığı riski)
- Sözleşme tipi / çalışma tipi / cinsiyet / medeni hal / kan grubu: FE hardcoded
- İlan employment type / experience: PortableEnum + FE hardcode
- Asset kategorileri: DB + UI (Assets)

### Genel desen

```
Sabit workflow durumları  → PortableEnum (~67 enum) + FE local array (sık senkron değil)
Firma lookup (kategori/tür) → Her modül kendi tablosu + controller + (bazen) UI
Custom field select       → field_options jsonb — sistem picklist’i DEĞİL
Merkezi picklist servisi  → YOK
```

**Kanban özel not:** Kullanıcı vizyonundaki “aşama ismi/sırası/renk özelleştirme” için bugün **hiçbir DB modeli yok**; tamamen kod sabiti. Bu, “izin türü yönetme”den farklı ve daha büyük bir iş (workflow state machine + migrate + UI).

### Verdict (B)

Tutarlı picklist/lookup altyapısı **yok**. Birkaç yerde iyi örnek var (`leave_types`, `departments`, `document_categories`); çoğu durum/seçenek hardcoded; bazı tablolar UI’sız. Zoho tarzı “listeleri stüdyodan tasarla” hedefi için **yeni soyutlama + migrasyon stratejisi** gerekir.

---

## C. Ayarlar — mevcut durum

### Company `/settings` (`SettingsPage.tsx`)

**Saf firma ayarları** (kişisel profil karışık değil):

| Sekme | İçerik |
|-------|--------|
| Genel | Firma adı, logo, vergi, adres |
| SMTP / SMS | Kanal test |
| Genel Ayarlar | timezone, dil, para, çalışma günleri |
| Bildirimler | Firma kanal tercihleri |
| API Keys / Lisans / Entegrasyonlar / Modüller | |

Sidebar: Yönetim → Ayarlar. Header kullanıcı menüsü de buraya gider — **kişisel profil linki yok**.

### Kişisel (profil / şifre / 2FA / tercih)

| Özellik | Backend | Company UI | Portal |
|---------|---------|------------|--------|
| Profil | `PUT /auth/profile` | **Yok** | `/profile` |
| Şifre (self) | `PUT /auth/password` | **Yok** | Portal profile |
| 2FA | `users/{id}/2fa/*` | Admin: UserDetailPage | — |
| Tema | preferences + localStorage | Header toggle | Header |
| Density | localStorage (+ preferences tipi) | Geçici toggle (Faz 3) | — |

**Sonuç:** Kullanıcı vizyonundaki **(1) AYARLAR = kişisel** Company’de henüz yok; **(2) YÖNETİM / Stüdyo** ise bugünkü `/settings` + dağınık modül ayarları (izin türleri Leaves’te, kategoriler Documents’ta, özel alanlar modül altında) — tek çatı değil.

---

## Strateji için çıkarımlar (uygulama yok — tartışma notu)

Önerilen sıra (kullanıcı vizyonuyla uyumlu):

1. **Özel alan sertleştirme:** FE–BE sözleşme, validasyon servisi, personel detay gösterimi, select options düzeltmesi → Form Engine’in zemini.
2. **Picklist soyutlama (ilk dalga dar):** Önce yönetilebilir DB listelerini (izin türü, kategoriler) ortak “LookupRegistry / company_lists” desenine çekmek; sonra kanban aşamaları gibi workflow enum’larını ayrı tasarlamak (riskli).
3. **Ayarlar Stüdyosu iskeleti:** Menü ayrımı (Kişisel AYARLAR vs YÖNETİM) + mevcut sayfaları taşıma/gruplama.
4. **Form düzeni / koşullu görünürlük:** `form_definitions` + FormEngine (ROADMAP 4A).

**Belirsizlik (strateji oturumunda netleştirilecek):**
- Kanban aşamaları: yeniden adlandırma yeterli mi, yoksa aşama ekle/sil/sırala da mı?
- Personel “pozisyon”: serbest metin mi kalacak, lookup mu olacak?
- Picklist v1 kapsamı: yalnızca “zaten DB’de olanlar” mı, yoksa status enum’ları da mı?

---

## Dosya referansları (hızlı)

```
Custom fields:
  backend/.../CustomFieldDefinition.php
  backend/.../CustomFieldController.php
  backend/.../migrations/2025_01_25_000001_create_custom_field_definitions_table.php
  frontend/packages/shared/src/components/CustomFieldRenderer.tsx
  frontend/apps/company/src/components/EmployeeForm.tsx
  frontend/apps/company/src/pages/shared/ModuleCustomFieldsPage.tsx

Picklist örnekleri:
  frontend/.../ApplicationsPage.tsx (statusColumns)
  backend/.../Enums/JobApplicationStatus.php
  backend/.../Models/LeaveType.php + LeaveTypeSeeder.php
  backend/.../Models/Department.php / DocumentCategory.php / ExpenseCategory.php / RequestType.php

Ayarlar:
  frontend/.../pages/settings/SettingsPage.tsx
  frontend/.../portal/pages/ProfilePage.tsx
  frontend/.../layouts/MainLayout.tsx (header → /settings)
```

---

**DUR.** Teşhis tamam. Uygulama yapılmadı. Sonraki adım: birlikte strateji + ilk dalga kapsamı.
