# FAZ 4 — Teşhis Raporu

**Branch:** `faz4-form-engine` (temel: `faz3-tasarim` / `b83da8b`; Faz 3 kodu üstünde)  
**Tarih:** 12 Temmuz 2026  
**Kapsam:** ADIM 1–2 teşhis/envanter + **ADIM 3 Lookup Engine çekirdek + Personel pilot**.

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

## Combobox Envanteri (ADIM 2)

**Amaç:** Lookup Engine boyutlandırma — sistemdeki her seçenek listesi.  
**Tarama:** Company SPA (birincil) + Portal + Superadmin + `backend/app/Enums` + lookup tabloları + controller `in:` validasyonları.  
**Sınıflar:**
- **BASİT** — değer + etiket (+ renk/sıra); Lookup Engine adayı
- **ZENGİN** — kendi tablosu + iş kuralları / hiyerarşi / form şeması; tablo kalır, tutarlı UI alır
- **?** — basit/zengin belirsiz (aşağıda sorulacak)
- **SİSTEM** — infra/meta (SMTP, timezone, widget tipi); firma HR lookup’ı değil (envanterde ayrı, Lookup Engine dışı)

**Kaynak kısaltmaları:** `hardcode` = FE sabit dizi/option · `enum` = PHP PortableEnum/CHECK · `tablo` = DB + API · `FK` = entity seçici · `jsonb` = tanım içi seçenek

Aynı mantıksal liste birden fazla UI yüzeyinde (form + filtre + badge) tekrarlanabilir; tabloda **bir satır = bir mantıksal alan**. UI yüzeyi “Sayfa” sütununda özetlenir.

### 1. Personel / Organizasyon

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Personel | Form + Liste filtre + Toplu | Durum | active, on_leave, suspended, terminated | hardcode (BE string, CHECK yok) | Evet | BASİT |
| Personel | Form | Cinsiyet | male, female, other | hardcode | Evet | BASİT |
| Personel | Form | Medeni durum | single, married, divorced, widowed | hardcode | Evet | BASİT |
| Personel | Form | Kan grubu | A/B/AB/0 × Rh± | hardcode | Evet (standart set) | BASİT |
| Personel | Form | Eğitim seviyesi | İlkokul…Doktora | hardcode (TR etiket) | Evet | BASİT |
| Personel | Form | Yakınlık (acil) | Eş, Anne, Baba, Kardeş, Çocuk, Arkadaş, Diğer | hardcode | Evet | BASİT |
| Personel | Form + Liste filtre | Sözleşme tipi | permanent, temporary, intern, contract | hardcode | Evet | BASİT |
| Personel | Form | Çalışma tipi | full_time, part_time, remote, hybrid | hardcode | Evet | BASİT |
| Personel | Form | Para birimi (maaş) | TRY, USD, EUR, GBP | hardcode | ? (global mi firma mı) | BASİT / ? |
| Personel | Form + Liste | Departman | departments.* | tablo FK | Evet (var) | ZENGİN |
| Personel | Form | Yönetici | employees FK | FK | — | ZENGİN (entity) |
| Personel | Form | Özel alan select/radio | field_options[] | jsonb | Evet (var) | ZENGİN (custom field; Lookup değil) |
| Personel › Belgeler | Tab form + filtre | Belge kategorisi | id_card, contract, certificate, education, health, other | hardcode + Rule::in | Evet | BASİT |
| Personel › Belgeler | (BE) | Belge durumu | active, archived, expired | string | Evet | BASİT |
| Departman | Form | Üst departman | departments FK | tablo | Evet | ZENGİN |
| Departman | Form | Departman yöneticisi | employee FK | FK | — | ZENGİN (entity) |
| Şube | Form | Ülke | Türkiye | hardcode | Hayır (sistem ref) | SİSTEM / BASİT |
| Şube | Form | Şehir | 81 il | hardcode | Hayır (TR ref) | SİSTEM / BASİT |
| Şube | Form | Yönetici | user FK | FK | — | ZENGİN (entity) |

### 2. İzin

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| İzin | Form (Company+Portal) | İzin türü | leave_types | tablo | Evet (CRUD var) | ZENGİN |
| İzin | Liste filtre + badge | Talep durumu | pending, approved, rejected, cancelled | hardcode + LeaveRequestStatus | Hayır (workflow) | BASİT / ? (kilitli sistem mi) |
| İzin › Tür formu | Form | Cinsiyet kısıtı | all, male, female | hardcode + GenderRestriction | Evet | BASİT |
| İzin › Hakediş | Form | Hakediş tipi | annual, monthly, per_pay_period, hourly, custom | enum AccrualTypeValue | Evet | BASİT / ? (politika motoru) |
| İzin › Hakediş | Form | İzin türü FK | leave_types | tablo | Evet | ZENGİN |
| İzin › Tatil | Form + filtre | Tatil tipi | national, company, regional (+ BE: religious) | hardcode / Typef3342e | Kısmi | BASİT |
| İzin › Bakiye/Rapor | Filtre | Yıl | son 5 yıl (dinamik) | hardcode logic | — | SİSTEM |

### 3. Puantaj (Company UI yok; Portal + BE)

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Puantaj | Portal Timesheet badge | Gün durumu | present, late, absent, leave, holiday, early_leave… | hardcode + BE string | Evet | BASİT |
| Puantaj | BE | Timesheet durumu | draft, submitted, approved, rejected | string | Hayır (workflow) | BASİT / ? |
| Puantaj | BE | Clock-in metodu | manual, qr, nfc, gps, face | string | ? | BASİT |
| Puantaj | BE / Portal okuma | Vardiya | shifts | tablo (CRUD UI yok) | Evet | ZENGİN |
| Puantaj | BE | Çalışma planı tipi | fixed, flexible, shift | string | Evet | BASİT / ? |
| Puantaj | BE | Dönem tipi | weekly, biweekly, monthly | string | Evet | BASİT |

### 4. Masraf (Company UI yok; Portal)

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Masraf | Portal form | Kategori | expense_categories | tablo (Company CRUD **yok**) | Evet | ZENGİN |
| Masraf | Portal badge | Talep durumu | draft, submitted, approved, rejected, paid | hardcode | Hayır (workflow) | BASİT / ? |

### 5. Doküman

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Doküman | Form + filtre | Kategori | document_categories | tablo | Evet (CRUD var) | ZENGİN |
| Doküman | Liste filtre | Dosya tipi | pdf, image, document, spreadsheet, presentation, archive | hardcode | Hayır | SİSTEM |
| Doküman | Badge | Onay durumu | draft, pending, approved, rejected | enum ApprovalStatusValue | Hayır (workflow) | BASİT / ? |
| Doküman | BE | required_documents.scope | all, department, position, employee_type | enum | Evet | BASİT |

### 6. İşe Alım

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| İşe Alım | Pozisyon formu | Çalışma şekli | full_time, part_time, contract, internship, remote | enum EmploymentType | Evet | BASİT — **Personel work_type ile FARKLI set** |
| İşe Alım | Pozisyon formu | Deneyim seviyesi | entry, mid, senior, lead, manager | enum ExperienceLevel | Evet | BASİT |
| İşe Alım | Pozisyon formu | Durum | draft, active, paused, closed | enum JobPositionStatus | Evet | BASİT |
| İşe Alım | Kanban + Detay | Başvuru aşamaları | new→…→hired/rejected (+ withdrawn) | hardcode + JobApplicationStatus | **Evet (vizyon)** | BASİT (workflow) — **renk+sıra kritik** |
| İşe Alım | Mülakat form | Tip | phone, video, onsite, technical, hr, panel | enum + API getTypes | Evet | BASİT |
| İşe Alım | Mülakat filtre/form | Durum | scheduled, completed, cancelled, no_show, rescheduled | enum | Evet | BASİT |
| İşe Alım | Tamamla modal | Öneri | strong_hire…strong_no_hire | enum RecommendationValue | Evet | BASİT |
| İşe Alım | Rapor etiket | Kaynak | website/linkedin… vs BE job_board/social… | hardcode / application_sources | Evet | BASİT — **FE↔BE etiket seti uyumsuz** |
| İşe Alım | Form | Pozisyon / Aday / Görüşmeci | FK | FK | — | ZENGİN (entity) |
| İşe Alım | BE | Teklif durumu | draft, sent, accepted, rejected, expired, withdrawn | enum | Evet | BASİT |
| İşe Alım | Form builder | Alan tipi | text, email, select… | hardcode | Meta | SİSTEM |

⚠️ `ApplicationController::updateStatus` validasyonu (`interview,testing,offer,accepted,pool`) enum/CHECK ile **uyumsuz** (ADIM 1 notu).

### 7. Onboarding

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Onboarding | Liste badge | Süreç durumu | pending, in_progress, completed, cancelled | enum | Hayır? | BASİT / ? |
| Onboarding | Şablon görev | Görev tipi | document_upload, document_fill, training, meeting, system_setup, quiz, custom | enum TypeValue | Evet | BASİT / ? (davranış bağlı) |
| Onboarding | Form | Çalışan / Şablon / Sorumlu | FK | FK / templates tablo | Evet | ZENGİN |
| Onboarding | BE | Görev durumu | pending, in_progress, completed, skipped | enum | Sistem | BASİT |
| Onboarding | BE | Milestone / buddy / survey_type | çeşitli | enum | Sistem | BASİT |

### 8. Performans

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Performans | Form | Dönem / çalışan / değerlendiren | FK | FK | — | ZENGİN (entity) |
| Performans | Badge | Dönem durumu | draft, active, closed | enum | Evet | BASİT |
| Performans | Badge | Değerlendirme durumu | draft, submitted, approved, rejected | enum | Workflow | BASİT / ? |
| Performans | BE + kısmi UI | OKR level | company, department, team, individual | enum | Evet | BASİT |
| Performans | BE | OKR / KR status, metric_type, confidence | çeşitli | enum | Evet | BASİT |
| Performans | BE | Feedback relationship | self, manager, peer, direct_report, external | enum | Evet | BASİT |
| Performans | Portal feedback tip | type | appreciation/suggestion… (**BE praise/suggestion… ile uyumsuz**) | hardcode | Evet | BASİT |
| Performans | BE | 1:1 mood / status | mood + status enum | enum | Evet | BASİT |
| Performans | CRUD | Yetkinlik / kriter | competencies, performance_criteria | tablo | Evet | ZENGİN |

### 9. Eğitim

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Eğitim | Form | Eğitim türü | online, classroom, hybrid | enum | Evet | BASİT |
| Eğitim | Form | Kategori | serbest + datalist öneri | API string | Evet | BASİT / ? (lookup mu serbest mi) |
| Eğitim | Oturum formu | Durum | scheduled, in_progress, completed, cancelled | enum | Evet | BASİT |
| Eğitim | Oturum / katılımcı | Eğitim / kullanıcı FK | FK | FK | — | ZENGİN |
| Eğitim | BE | Katılımcı durumu | registered, attended, absent, excused | enum | Evet | BASİT |
| Eğitim | BE | Learning path level / mandatory scope | beginner… / all… | enum | Evet | BASİT |
| Eğitim | BE | Eğitim talebi durumu | pending, approved, rejected, completed | enum | Workflow | BASİT / ? |

### 10. Varlık

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Varlık | Form | Kategori | asset_categories | tablo | Evet (CRUD var) | ZENGİN |
| Varlık | Form | Durum | FE: available/assigned/maintenance/**retired** · BE enum: …/**disposed** | enum + hardcode | Evet | BASİT — **FE↔BE tutarsız** |
| Varlık | Form | Kondisyon | FE: new/good/fair/poor · BE +broken | enum + hardcode | Evet | BASİT — **eksik değer** |
| Varlık | Zimmet | Kullanıcı FK | FK | FK | — | ZENGİN |
| Varlık | BE | Maintenance type/status | preventive… / scheduled… | enum | Evet | BASİT |
| Varlık | BE | Lifecycle stage | new, active, maintenance, retired, disposed | enum | Evet | BASİT |
| Varlık | BE | Depreciation method | none, straight_line, declining_balance | enum | Evet | BASİT |
| Varlık | BE/Portal | Asset request urgency/status | urgency + status | enum | Evet | BASİT |
| Varlık | BE | License type | perpetual, subscription… | enum | Evet | BASİT |

### 11. Anket

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Anket | Form | Anket türü | engagement, satisfaction, pulse, enps, onboarding, exit, custom | enum | Evet | BASİT |
| Anket | Form | Soru türü | text, rating, single_choice, multiple_choice, nps (+ BE: scale, matrix) | hardcode / enum | Meta | SİSTEM / BASİT |
| Anket | Form | Soru seçenekleri | kullanıcı yazar | dinamik | Anket başına | ZENGİN (içerik, lookup değil) |
| Anket | BE | recurrence / audience | none/weekly… · all/department… | enum | Evet | BASİT |
| Anket | Portal | Cevap (rating) | 1–5 | hardcode | — | SİSTEM |
| Anket | Portal | Filtre tab | all / pending / completed | hardcode | — | SİSTEM |

### 12. Talep (Company UI yok; Portal)

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Talep | Portal form | Talep türü | request_types | tablo (Company CRUD **yok**) | Evet | ZENGİN |
| Talep | Portal form + badge | Öncelik | low, normal, high, urgent | hardcode | Evet | BASİT |
| Talep | Portal badge | Durum | pending, in_review, approved, rejected, cancelled | hardcode | Workflow | BASİT / ? |

### 13. Kullanıcı / Rol / Workflow / Ayarlar / Rapor

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Kullanıcı | Form / toplu | Rol | roles | Spatie tablo | Evet | ZENGİN |
| Kullanıcı | Toplu işlem | Aksiyon | activate, deactivate, assign_role… | hardcode | — | SİSTEM |
| Rol | RoleForm | data_scope | own…company (**UI yok**, BE kolon var) | DataScopeLevel | Evet | BASİT — **UI eksik** |
| Rol | RoleForm | İzinler | permissions grid | Spatie + sabit etiket | Sistem | ZENGİN / SİSTEM |
| Workflow | BE/UI | entity_type, approver_type, timeout_action | sabit enum setleri | enum | Evet (tanım) | BASİT / ? (motor) |
| Ayarlar | Settings | SMTP encryption, SMS provider, TZ, dil, tarih, para, çalışma günü | sabit listeler | hardcode | Firma config | SİSTEM |
| Özel alan tanımı | Form | field_type / entity_type | sabit listeler | hardcode | Meta | SİSTEM |
| Rapor / Dashboard | Builder | boyut/metrik/chart/KPI tipi | API metadata + hardcode | API / hardcode | Meta | SİSTEM |
| Audit | Filtre | action | 40+ action key | hardcode | Sistem | SİSTEM |

### 14. Superadmin (Lookup Engine dışı; referans)

| Modül | Sayfa | Alan | Değerler | Kaynak | Sınıf |
|-------|-------|------|----------|--------|-------|
| Firma | Form + filtre | status | trial/active/suspended/cancelled (filtre: inactive uyumsuz) | hardcode + CompanyStatus | SİSTEM |
| Firma | Form | Lisans paketi | license_packages | tablo | ZENGİN |
| Paket | Form | Süre ay | 1/3/6/12/24 | hardcode | SİSTEM |
| Modül | Form | icon | bootstrap icon list | hardcode | SİSTEM |
| Ledger | Modal | payment_method | bank_transfer… | hardcode | SİSTEM |

---

### Sınıflandırma özeti (mantıksal alan bazında)

| Sınıf | Ne demek | Örnekler |
|-------|----------|----------|
| **BASİT → Lookup Engine** | Etiket/renk/sıra/aktif; iş kuralı yok veya minimal | Durumlar, cinsiyet, kan grubu, kanban aşaması, öncelik, mülakat tipi, eğitim tipi, varlık kondisyonu… |
| **ZENGİN → kendi tablo + ortak UI** | CRUD + ek kolonlar/kurallar | leave_types, departments, *\_categories, request_types, competencies, job_positions, roles… |
| **SİSTEM** | Lookup Engine’e alınmaz | SMTP, timezone, field_type meta, audit action, chart tipi |
| **Entity FK** | Kişi/kayıt seçici; picklist değil | manager, reviewer, employee, training_id |

**Workflow durumları** (izin/masraf/talep/onay): teknik olarak BASİT ama **firma ekle-sil yaparsa state machine kırılır** → varsayılan: Lookup’ta *yeniden adlandır + renk + gizle*; aşama ekle/sil ayrı ürün kararı (kanban ile aynı).

---

### Sayısal özet

Sayım birimi: **benzersiz mantıksal seçenek alanı** (form+filtre tekrarı tek sayılır). Entity FK’ler ve saf SİSTEM meta ayrı.

| Metrik | Sayı (yaklaşık) |
|--------|-----------------|
| Toplam mantıksal combobox/picklist (domain + zengin + sistem) | **~95** |
| … yalnızca domain (HR iş alanı; sistem meta hariç) | **~78** |
| **BASİT** (Lookup Engine adayı, net) | **~48** |
| **BASİT / ?** (workflow veya davranış bağlı — netleştirilecek) | **~14** |
| **ZENGİN entity / tablo** | **~16** |
| **SİSTEM / meta** | **~17** |
| Entity FK seçiciler (picklist sayılmaz, UI’da select) | **~15+** |
| Şu an **hardcode / enum** (firma değiştiremiyor) | **~55+** domain basit alan |
| Şu an **DB tablo** (+ çoğu Company CRUD) | **~12** (leave_types, departments, branches, document/asset categories, competencies, holidays, accrual_policies, job_positions, custom_fields, criteria, workflows, templates…) |
| DB tablo ama **Company CRUD yok** | **3+** (`expense_categories`, `request_types`, `shifts`; ayrıca `application_sources`) |
| En yoğun modül (basit + zengin domain) | **1) İşe Alım (~12)** · **2) Personel (~12)** · **3) Performans+Varlık (~10’ar)** · **4) İzin (~7)** |
| Company’de UI’sız (sadece Portal/BE) | Puantaj, Masraf, Talep picklist’leri |

**UI yüzey sayısı (tekrarlı select’ler dahil, Company ağırlıklı):** ~70+ `<select>`/const option yüzeyi; Portal ~8 form picklist; Superadmin ~6.

---

### Lookup Engine boyutlandırma çıkarımı

1. **İlk dalga (yüksek ROI, düşük risk):** Personel alanları (durum, sözleşme, çalışma tipi, cinsiyet, medeni, eğitim, yakınlık, belge kategorisi) + İşe Alım (deneyim, pozisyon durumu, mülakat tipi/öneri) + Varlık durum/kondisyon + Talep önceliği + Anket türü + Eğitim türü. Hepsi bugün hardcode.
2. **Paralel (zengin UI birleştirme):** `expense_categories` + `request_types` Company CRUD; mevcut leave/document/asset category UI’larını ortak “Liste yönetimi” kabuğuna al.
3. **İkinci dalga (ürün kararı şart):** Kanban başvuru aşamaları + diğer workflow status’ları (yeniden adlandır vs ekle/sil).
4. **Alınma:** SMTP/SMS/timezone, rapor chart tipi, custom field `field_type`, audit actions, entity FK’ler.

---

---

### Belirsizlikler — KARARLAR (12 Temmuz 2026)

| # | Soru | Karar |
|---|------|--------|
| 1 | Workflow / kanban aşama ekle-sil | **SİSTEM-KODLU hibrit:** kod (`value`) sabit; etiket/renk/sıra firma. Aşama ekle/sil yok (şimdilik). |
| 2 | work_type vs employment_type | **Tek `work_type` lookup** (birleşik değer seti). |
| 3 | Para / şehir / kan grubu | **SİSTEM lookup** (`is_system`, `company_id=null`, salt okunur). |
| 4 | accrual_type / onboarding görev tipi | **Lookup DEĞİL** — iş kuralı / motor sabiti. |
| 5 | Eğitim kategori | Sonraki dalga (bu pilotta yok). |
| 6 | data_scope | **Lookup DEĞİL** — Faz 2 güvenlik; sabit enum. |

**Lookup DEĞİL (zengin entity kalır):** izin türü, departman, kategoriler, request_types, roller…

---

## Lookup Engine — kararlar + araştırma (ADIM 3)

### Üç katman

| Katman | `company_id` | `is_system` | Firma ne yapabilir? | Örnek |
|--------|--------------|-------------|---------------------|--------|
| **FİRMA** | null = platform default; firma override kendi `company_id` | false | ekle / label-renk-sıra / pasifleştir | `employee_status`, `work_type` |
| **SİSTEM** | null | true | salt okunur (403) | `currency`, `city_tr`, `blood_type`, `country` |
| **HİBRİT** | override ile | meta/hybrid | sadece label/renk/sıra; `value`+silme kilitli | `application_stage` (sonraki dalga) |

### Araştırma bulguları (Zoho / BambooHR deseni) — 3 kritik kural

**K-A. Kayıtlar LABEL değil REFERANS tutar.**  
`employees.status = 'active'` (value). Label `"Aktif"`→`"Çalışıyor"` değişince kayıt değişmez; görüntü `resolve(value)` ile yeni label’ı alır.

**K-B. Kullanımdaki değer HARD DELETE edilemez.**  
Kullanım varsa → `is_active=false` (pasif). Pasif değer yeni formlarda seçilemez; eski kayıtlarda `resolve` ile görünür. Kullanım yoksa silinebilir.

**K-C. Cascading için hazır kolon.**  
`parent_lookup_id` nullable — şimdi mantık yok; ileride şehir→ilçe vb.

### Bu dalga kapsamı (çekirdek + pilot)

- `lookups` tablosu + LookupService + CRUD API (`management.lookups.*`)
- Sistem seed: para, TR illeri, kan grubu, ülke
- Firma default seed: `employee_status`, `work_type`
- Pilot bağlama: Personel Durum + Çalışma Tipi; İşe Alım `employment_type` → aynı `work_type`
- Kalan ~46 combobox + kanban hibrit → sonraki yayılım dalgaları

---

**DUR (görsel kontrol).** Altyapı + pilot sonrası: Personel Durum dropdown API’den; lookup label rename → form + mevcut kayıtta yeni label.
