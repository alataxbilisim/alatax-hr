# YENİDEN ÇERÇEVELEME + YAPILANDIRILABİLİRLİK BOŞLUK DENETİMİ

**Tarih:** 6 Ağustos 2026 · **Branch:** `faz4-form-engine` · **Push:** yok  
**Kapsam:** Bölüm A = belge; Bölüm B = teşhis (ürün kodu değiştirilmedi).

---

## A) Yapılan belge değişiklikleri

| ID | Dosya | Ne değişti |
|----|-------|------------|
| **A1** | `docs/ROADMAP.md` §0 | “Dobedan’a göre karar” → genel pazar; Dobedan pilot/doğrulama; ölçek **aralık** (10 kişi → on binlerce / yüzlerce şube). Sabit “3 şirket / 6 şube / ~3000” kaldırıldı. İlgili cümleler (§2 ilke 6, Faz 7, lisans, M3) aynı çerçeveye çekildi. |
| **A1** | `docs/GUNCEL_DURUM_RAPORU.md` §0 | Aynı çerçeve tablosu. |
| **A1** | `docs/BURADAN_BASLA.md` §2 | Aynı çerçeve. |
| **A1** | `docs/GRUP_MIMARI_TESHIS.md` | Müşteri sabit sayıları → ölçek aralığı; ADIM 4 başlığı aralık olarak yeniden yazıldı. (`docs/arsiv/` tarihsel bırakıldı.) |
| **A2** | `docs/ENTEGRASYON_SPEC.md` | **Yeni.** Üç katman (profil / bağlayıcı / alan sahipliği); özel alan eşlemesi; sistem profil + müşteri kopyası; güvenlik (doğrudan DB/ağ = on-prem+lisans); idempotent import; export alan izni; Logo + Sedna ilk hedefler. |
| **A3** | `docs/MODUL_SPEC.md` | Ortak standarta 4 madde: Ayarlar Stüdyosu/⚙, özel alan, import/export profili, kurulumsuz varsayılanlarla çalışma. |
| **A4** | `.cursorrules` | Yapılandırılabilirlik sınırı (içinde / dışında). |
| **A4** | `docs/SISTEM_ISLEYIS.md` | Aynı sınır tablosu + kurulumsuz çalışma notu; portal `home_company_id` ifadesi güncellendi. |

**Ürün kodu:** değişmedi. Geçici teşhis script’leri: `backend/scripts/tmp_b1_cf_rename.php`, `tmp_db_counts.php` (rapor sonrası silinebilir).

---

## B) Boşluk denetimi

### B1 — Özel alan kimliği

**Ham bulgu**

| Soru | Cevap |
|------|--------|
| Kimlik nedir? | **`field_key`** (ve sistem alanlarında `system_key`). Etiket ayrı: `field_label` / `label_override`. |
| Değer nerede? | Entity JSONB (`employees.custom_fields` vb.) **`field_key` anahtarıyla**. |
| Rapor kolonu? | `cf_{field_key}` (`AbstractDataset::fieldsForCompany`). |
| Etiket değişince ne olur? | Form layout / liste / rapor **key ile** bağlanır → etiket değişince **kırılmaz**. |
| `position` string dual-write sınıfı mı? | **Hayır.** Tek kimlik `field_key`; ikinci “etiket kolonu” SSOT yok. |

**Kanıt (2026-08-06)**

1. `CustomFieldController::update` validate listesinde **`field_key` yok** — API üzerinden etiket güncellenir, key gönderilmez / kabul edilmez.
2. Transaction’lı probe script: etiket `B1 Renamed Label` → `field_key_stable: true`, `jsonb_value_ok: true`, `report_dimension_present: true` (`cf_b1_probe_key`).
3. Risk (küçük): `field_key` model `fillable` içinde; başka yol (tinker / mass assignment) key değiştirirse JSONB yetim kalır. Update FormRequest’te `prohibited` yok — savunma validate omit’e dayanıyor.

**Kova:** **Faz 6 öncesi** — `field_key` update’te `prohibited`; soft-delete/rename politikası dokümante. **Backlog** — layout/liste görünümlerinde key drift audit.

---

### B2 — Özel alan kapsamı

**Ham bulgu**

| Entity const (`CustomFieldDefinition`) | Form Engine / dataset kullanımı (bugün) | Depolama |
|----------------------------------------|------------------------------------------|----------|
| `employee` | ✅ Form + `EmployeesDataset` | JSONB `custom_fields` |
| `leave_request` | ✅ + dataset | JSONB |
| `expense` | ✅ | JSONB |
| `asset` | ✅ | JSONB |
| `job_application` | Form / `form_data` yolu | JSONB / form_data |
| `training`, `performance`, `document` | Const var; derinlik zayıf | — |

- EAV yok; hibrit **tipli kolon + JSONB**.
- Schema dump’ta `custom_fields` üzerinde **GIN yok** (filtre/rapor büyüdükçe seq scan riski).

**Ölçek (30k personel / ~45M işlem satırı varsayımı)**

| Tablo sınıfı | JSONB özel alan | Davranış |
|--------------|-----------------|----------|
| `employees` (~on binler) | Serbest (makul alan sayısı) | Key lookup OK; çok filtre → GIN gerekir |
| `leave_requests`, `expenses`, `assets` | Serbest / dikkatli | Orta hacim |
| `attendance_records`, puantaj satırları, benzeri yüksek kardinalite | **Yasak veya çok sınırlı** | 45M × JSONB filtre = rapor/filtre patlar; sabit kolon + indeks tercih |

**Öneri:** Serbest: personel, masraf, varlık, işe alım formu. Sınırlı: izin. Yasak/default kapalı: puantaj/attendance ve benzeri transaction tabloları.

**Kova:** **Faz 6 sırasında** — entity başına politika + GIN nerede serbestse. **Backlog** — yüksek hacimli tablolarda özel alan UI’ını kapat.

---

### B3 — Ayarlar Stüdyosu kapsamı

**Ham bulgu**

- `SettingsRegistry::buildPilotDefinitions`: ~15 tanım; `moduleKey`: **`leaves`**, **`reports`**, **`settings`**, **`kvkk`** (yasal taban + KVKK). Kod yorumu: diğer modüller Faz 6.
- `PageSettingsButton` kullanımı: **2 sayfa** — `LeavesPage`, `ReportPrivacySettingsPage`.

| # | Modül (B1–B14) | Registry / ⚙ | Olması gereken ayar örnekleri (boşluk) |
|---|----------------|--------------|----------------------------------------|
| B1 | Organizasyon | ❌ | Şube zorunluluğu, org ağacı derinlik, kod formatı |
| B2 | Personel | ❌ | Sicil maskesi, zorunlu alanlar, özel alan politikası, liste yoğunluğu |
| B3 | İzin | ✅ ⚙ | (pilot mevcut) + tür/politika stüdyo bağları |
| B4 | PDKS | ❌ | Grace, QR TTL, vardiya çakışma, eşzamanlı okutma limiti |
| B5 | Ücret & Ödemeler | ❌ | Masraf limiti, avans kuralları, para birimi |
| B6 | İşe Alım | ❌ | Pipeline aşamaları, CV saklama, form varsayılanı |
| B7 | Oryantasyon & Çıkış | ❌ | Şablon seçimi, otomatik tetik |
| B8 | Performans | ❌ | Dönem varsayılanı, 360 kuralları |
| B9 | Eğitim | ❌ | Zorunlu eğitim, sertifika hatırlatma |
| B10 | İSG | ❌ | Muayene periyodu, risk sınıfı |
| B11 | Varlık | ❌ | Kategori zorunlu, zimmet onay |
| B12 | Anket | ❌ | Anonim eşik, eNPS ölçeği |
| B13 | Doküman+ | ❌ | Saklama, zorunlu evrak tipleri |
| B14 | Analitik | ✅ kısmi (rapor gizlilik) | Cache TTL (registry’de var), varsayılan pano |

**Özet:** 14’ten **~2’si** (izin + analitik/rapor) bağlanmış; **~12’si** yok.

**Kova:** **Faz 6 sırasında** — her modül dalgasında Settings Registry + ⚙ DoD (zaten `.cursorrules` / MODUL_SPEC). **Faz 6 öncesi** — yok (pilot yeterli).

---

### B4 — Sistem / firma katmanı

| Alan | Sistem / hibrit / firma ayrımı var mı? | Sürüm güncellemesi müşteri özelleştirmesini ezer mi? |
|------|----------------------------------------|-----------------------------------------------------|
| **Lookup** | Evet (`company_id` null + `is_system`, firma override) | Hayır (override ayrı satır) — desen doğru |
| **Ayarlar (SettingValue)** | Evet (system scope + company; `company_id` null sistem) | Hayır — değer satırı firma/system katmanlı |
| **Form düzenleri** | Evet (yorum: `company_id` null = sistem; firma satırı override) | Hayır — kopya/override deseni; dikkat: `BelongsToCompany` + null sistem satırı edge-case |
| **Onay akışları** | **Hayır** — yalnız `company_id` zorunlu; sistem şablon yok | N/A ezme; **eksik:** yeni firmada boş / elle kurulum |
| **Bildirim şablonları** | Hibrit: **config defaults** + DB firma override (`NotificationTemplate` company-only) | Config güncellemesi override’ı ezmez; override yoksa yeni default görünür |
| **Kayıtlı rapor / pano** | Evet (`is_system` + `company_id` null; kopyalanabilir) | Sistem şablon güncellenir; müşteri kopyası bağımsız (ezilmez) |

**Kova:** **Faz 6 öncesi / başı** — onay akışları için sistem varsayılan şablon + kopyala (kurulumsuz çalışma). **Faz 6 sırasında** — FormDefinition sistem satırları ile trait uyumu doğrula. **Backlog** — bildirim için tam “sistem satırı + kopya” (config yerine DB system).

---

### B5 — Export ve alan izni

**Ham bulgu**

| Yol | Alan izni? | Ücret sızıntısı? |
|-----|------------|------------------|
| Rapor motoru run/export (`ReportDefinitionService` / `ReportQueryBuilder`) | Evet (`permission`, `hidden_fields`, `filterAllowedFields`) | **Hayır** — test: `EmployeeFieldPermissionTest` (report salary measure forbidden), `ReportSharingPrivacyTest::hidden_fields…` **PASS** (2026-08-06) |
| Personel liste CSV (`EmployeeController::export`) | **Hayır** (sabit kolon seti) | Ücret kolonu **yok** → bugün sızmaz; telefon vb. izinsiz gidebilir; ileride kolon eklenirse kapı yok |
| Eski personel rapor Excel (`EmployeeReportController::exportExcel`) | **JSON aggregate’te var, exportExcel’de yok** | `exportExcel` → doğrudan `aggregateData`; `requires_permission` / `canViewSalary` **atlanır**. Ücret görme yetkisi olmayan kullanıcı `measure=avg_gross_salary` ile CSV alabilir (**kod yolu kanıtı**) |
| Kullanıcı liste export | Alan izni yok | Maaş yok |
| KVKK export (collector) | Veri sahibi / yetkili süreç; `EmployeeProfileCollector` maaş alanlarını paketler | RBAC “salary.view” değil; **amaçlı** veri sahibi paketi — ayrı tehdit modeli |

**Kova:** **Faz 6 öncesi** — `EmployeeReportController::exportExcel` permission gate (aggregate ile aynı). Liste export’a alan izni / hassas kolon politikası. **Faz 6 sırasında** — tüm export yolları tek “SensitiveExport” denetimi.

---

### B6 — Kurulumsuz çalışma (10 kişilik boş firma)

`DefaultCompanyHrSeedService::ensureForCompany` kayıt / admin şirket oluşturmada çalışır: TR izin türleri, hakediş politikası, tatiller, offboarding şablon — **izin tarafı makul default**.

| Modül | Boş firmada | Tıkanma? |
|-------|-------------|----------|
| B1 Organizasyon | Şube/dept boş; personel şubesiz eklenebilir (politikaya bağlı) | Kısmi — org ağacı yoksa rapor kırılımı zayıf |
| B2 Personel | CRUD çalışır; pozisyon kataloğu seed’lenebilir | Çalışır |
| B3 İzin | Tür + politika seed | Çalışır (onay akışı yoksa tek adım / bypass davranışına bağlı) |
| B4 PDKS | Vardiya/tanım yoksa punch sınırlı | **Tıkanır / zayıf** — önce vardiya/konum |
| B5 Ücret/Masraf | Kategori yoksa masraf tipi eksik | **Kısmi tıkanma** |
| B6 İşe Alım | İlan/form boş | İlan açılabilir; pipeline boşsa zayıf |
| B7 Oryantasyon | Offboarding şablon seed | Onboarding şablon yoksa kısmi |
| B8 Performans | Dönem yok | **Tıkanır** — önce dönem |
| B9 Eğitim | Katalog boş | Eğitim açılabilir; zorunlu set yok |
| B10 İSG | Tanım yok | **Tıkanır** (modül derinlik) |
| B11 Varlık | Kategori yok | **Tıkanır** — kategori |
| B12 Anket | Anket yok | Anket oluşturarak çalışır |
| B13 Doküman | Kategori opsiyonel | Çalışır |
| B14 Analitik | Sistem rapor/pano | Çalışır (dataset’ler) |
| Onay (çapraz) | Sistem workflow yok | İzin/masraf **onay beklerken kuyruk boş** veya auto — **boşluk** |

**Kova:** **Faz 6 öncesi** — onay sistem şablonları + “kurulumsuz” smoke test (10 kişi senaryosu). **Faz 6 sırasında** — her modül default seed (MODUL_SPEC yeni madde).

---

### B7 — Ölçek varsayımları (kod örnekleri)

“Az personel / tek şirket” kokusu taşıyan, sayfalama veya bellek sınırı olmayan yollar:

| Dosya | Satır (yaklaşık) | Desen | Risk |
|-------|------------------|-------|------|
| `EmployeeController.php` | ~734 | `export` → `->get()` tüm personel | 10k+ CSV bellek |
| `UserController.php` | ~635 | `export` → `->get()` | Aynı |
| `ActivityLogController.php` | ~110 | export `->get()` | Log hacmi |
| `Timesheet/AttendanceController.php` | ~197, ~229 | `->get()` kayıt seti | Ay/şirket büyürse |
| `Timesheet/AttendanceReportController.php` | ~144 | `->get()->map` | Rapor belleği |
| `Portal/PortalTimesheetController.php` | ~217, 255, 289 | `->get()` | Portal ay görünümü |
| `RoleController.php` | ~62, ~232 | rol kullanıcıları `->get()` | Büyük rol |
| `DepartmentController.php` | ~43 | index `->get()` (paginate yok) | Çok dept |
| `Leaves/HolidayController.php` | ~34 | `->get()` | Düşük risk |
| `Leaves/LeaveCalendarController.php` | ~39 | `->get()` | Takvim aralığı büyürse |
| `WebhookController.php` | ~21 | `->get()` | Düşük |
| `EmployeeReportController.php` | ~325 | aggregate `->get()` | Eski rapor |
| `CustomFieldController.php` | ~32 | definitions `->get()` | OK (az satır) |

Liste endpoint kuralı (`.cursorrules`: paginate) birçok yerde ihlal / istisna (lookup, export, calendar). Select’e “tüm personel” yükleyen ayrı bir `employees/options` uçları da export kadar kritik; personel index paginate ✅.

**Kova:** **Faz 6 öncesi** — export’ları stream + chunk; attendance rapor `->get()` tavanı. **Faz 6 sırasında** — uzun koşu / 30k personel smoke. **Backlog** — calendar/department gibi düşük risk paginate.

---

## Özet kovalar

| Kova | Maddeler |
|------|----------|
| **Faz 6 öncesi** | B1 `field_key` prohibited; B5 legacy `exportExcel` gate + liste export politika; B4/B6 onay sistem şablonu; B7 export/attendance bellek tavanı |
| **Faz 6 sırasında** | B2 entity politikası + GIN; B3 tüm modül Settings+⚙; B6 modül default seed; B5 export birliği; B7 ölçek smoke |
| **Backlog** | B1 key drift audit; B2 attendance’da CF yasak UI; B4 bildirim DB system satırı; B7 düşük risk listeler |

---

## Doğrulama notları

- Ürün kodu / migration **değiştirilmedi**.
- Push yok.
- B1: canlı probe (rollback transaction) ✅.
- B5 rapor motoru: mevcut PHPUnit ✅; legacy Excel: **statik kod yolu** (runtime CSV çağrısı boş DB nedeniyle koşturulmadı — permission yokluğu satır 583–599 vs 186–188).
