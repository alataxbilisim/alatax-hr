# Faz 6 Öncesi Son Kapatma

**Branch:** `faz4-form-engine`  
**Tarih:** 2026-08-05  
**Kısıtlar:** DB wipe yok · push yok · önce test

---

## Özet

| Madde | Sonuç |
|-------|--------|
| 1) 3 SPA build | Yeşil (company / portal / superadmin) |
| 2) ReportEngine GROUP BY + sıralama | Düzeltildi + registry senkron data-provider test |
| 3) `employees.position_id` FK | Migration + dual-write + form/resource/dataset + test |
| 4) AttendanceClock çoklu in/out | Yalnız analiz (kod yok) — karar Faz 6 |

---

## 1) Company SPA build

**Sorun:** `EmployeesPage.tsx` — `Employee` tipinde `full_name` / `name` yoktu; `tsc` kırılıyordu.

**Çözüm:**
- `frontend/apps/company/src/pages/employees/EmployeesPage.tsx` — local `Employee` arayüzüne `full_name`, `name` (+ `position_id` / `position_label`)
- `frontend/packages/shared/src/types/modules.ts` — paylaşılan tip hizalandı

**Doğrulama:** `@alatax/company`, `@alatax/portal`, `@alatax/superadmin` production build yeşil.

---

## 2) ReportEngine GROUP BY (SQLSTATE 42803)

**Sorun:** `group_by: ['position']` (veya herhangi bir gruplama) + dataset `defaultSort` (ör. `employee_code`) → PostgreSQL 42803 (SELECT’te olmayan kolon ORDER BY).

**Kural (uygulandı):** Gruplama varken sıralama yalnız `group_by` alanları veya aggregation alias’ları üzerinden; aksi sort’lar elenir, boşsa güvenli varsayılan seçilir.

**Dosya:** `backend/app/Services/Reports/ReportQueryBuilder.php`

**Test:** `backend/tests/Feature/Reports/DatasetGroupedQueryTest.php`
- Her registry dataset için gruplamalı sorgu (provider ↔ `DatasetRegistry` senkron — `DatasetRegistryIsolationTest` deseni)
- Regression: `group_by position` + güvensiz `sorts: employee_code` hata vermez
- Employees probe artık `position_id` (FK kimliği)

---

## 3) Pozisyon → `position_id` FK

### (a) Migration
`backend/database/migrations/2026_08_05_220000_add_position_id_to_employees_table.php`
- nullable FK → `positions`, indeks `(company_id, position_id)`
- Idempotent SQL backfill: tek eşleşen **name** veya **code** → `position_id`
- Belirsiz (aynı ad birden fazla) / eşleşmeyen bırakılır — tahmin yok
- **`employees.position` string kolonu drop edilmedi**

### (b) Backfill komutu + yerel durum
`php artisan employees:backfill-position-id [--dry-run]`  
Rapor: `storage/logs/position_id_backfill_report.json`

**Docker `alatax_hr` (migrate sonrası, 2026-08-05):**

| Metrik | Değer |
|--------|-------|
| Toplam personel (silinmemiş) | 62 |
| `position_id` dolu | 37 |
| String var, FK boş (belirsiz) | 23 |
| Pozisyon boş | 2 |
| Komut: updated | 0 (migration zaten tek eşleşenleri doldurdu) |
| Komut: ambiguous | 23 |
| Komut: unmatched | 0 |

Belirsiz örnekler: aynı şirket içinde sistem kodu + `DEMO_POS_*` aynı **name** (ör. «Genel Müdür» → `GEN_MUD` / `DEMO_POS_09`). Manuel seçim veya Tur8 sonrası code yazımı gerekir; tahmin edilmedi.

### (c) Uygulama yüzeyi
- **Model:** `Employee::$fillable` + `positionRef()` BelongsTo
- **Controller:** `position_id` validasyonu; `syncPositionFields()` — id/code/tekil ad → FK + dual-write `position = code`; belirsiz ad → FK null, string korunur
- **Resource:** `position_id`, `position_label` önce FK’den
- **Dataset:** join `positions`; alanlar `position_id`, `position_name`, `position_code`; hierarchy `position_id`
- **FE:** `EmployeeForm` Select value = id; FormEngine options value = id (field_key hâlâ `position`, BE çözümler)

### (d) Dual-write / geri dönüş
Bu turda string kolon yazılmaya devam eder (`position` = katalog **code**). Drop sonraki sürümde.

### Test
`backend/tests/Feature/EmployeePositionIdPersistTest.php`  
Aynı adlı iki pozisyon → iki personele `position_id` → show ayrı → `group_by [position_id, position_name]` **2 satır**.

`EmployeePositionCodePersistTest` — code ile create artık `position_id` de set eder (dual-write).

---

## 4) AttendanceClockService — çoklu giriş/çıkış (yalnız soru)

### Bugünkü davranış
Kaynak: `backend/app/Services/Timesheet/AttendanceClockService.php` + `attendance_records` üzerinde **gün başına tek satır** modeli (`user_id` + `date` unique — migration notu).

| Senaryo | Sonuç |
|---------|--------|
| İlk punch / clockIn (kayıt yok) | Giriş yazılır |
| clockIn varken tekrar clockIn | `Bugün zaten giriş yapmışsınız` |
| Giriş var, çıkış yok → punch | clockOut |
| clockOut sonrası tekrar clockOut | `Bugün zaten çıkış yapmışsınız` |
| Giriş→çıkış sonrası üçüncü punch → clockIn yolu | Yine `Bugün zaten giriş yapmışsınız` (aynı satırda `clock_in` dolu) |

Yani **bölünmüş vardiya (in→out→in→out) desteklenmiyor.** Gün = tek in/out çifti.

### Çoklu in/out için ne gerekir? (Faz 6 kararı — uygulama yok)
1. **Veri modeli:** Ya punch satırları (`attendance_punches`: in/out çiftleri veya event stream), ya da günde N `attendance_records` (unique kaldırılır / slot eklenir).
2. **Durum makinesi:** «Son punch in mi out mu?» — kapalı çift sonrası yeni in açılabilir.
3. **Hesap:** `AttendanceCalcService` total_hours / late / early — çiftler toplamı veya vardiya pencereleri.
4. **UI / API:** Portal «bugün durumu», QR punch, manuel düzeltme, rapor dataset’leri.
5. **Geri uyumluluk:** Mevcut tek-satır kayıtlar 1. çift olarak migrate.
6. **İş kuralları:** Max çift/gün, gece vardiyası (tarih sınırı), mola vs punch ayrımı.

**Öneri (karar Faz 6):** Event/punch tablosu + gün özeti projection; mevcut `AttendanceRecord` özet kalsın veya deprecate edilsin — otelcilik split-shift için doğal model.

---

## Test / build kanıtı

| Kontrol | Sonuç |
|---------|--------|
| 3 SPA build | Yeşil (company / portal / superadmin) |
| Odak testler (position + grouped) | 14 passed |
| Tam suite | **738 passed** (3250 assertions) — önceki 725 + 13 |

Yeni / genişleyen testler:
- `DatasetGroupedQueryTest` — 11 dataset + registry sync + 1 regression
- `EmployeePositionIdPersistTest` — 1 (FK + gruplama 2 satır)

---

## git diff --stat (commit kapsamı)

```
 .../Http/Controllers/Api/V1/EmployeeController.php | 129 ++++++++++++++++++++-
 backend/app/Http/Resources/EmployeeResource.php    |  25 ++--
 backend/app/Models/Employee.php                    |   9 ++
 .../Services/Reports/Datasets/EmployeesDataset.php |  15 ++-
 .../app/Services/Reports/ReportQueryBuilder.php    |  95 ++++++++++++++-
 .../Feature/EmployeePositionCodePersistTest.php    |   3 +
 .../apps/company/src/components/EmployeeForm.tsx   |  40 +++++--
 .../src/pages/employees/EmployeeFormEnginePage.tsx |   9 +-
 .../company/src/pages/employees/EmployeesPage.tsx  |   6 +
 frontend/packages/shared/src/types/modules.ts      |   6 +
 + EmployeesBackfillPositionIdCommand.php
 + 2026_08_05_220000_add_position_id_to_employees_table.php
 + EmployeePositionIdPersistTest.php
 + DatasetGroupedQueryTest.php
 + docs/FAZ6_ONCESI_KAPATMA.md
```

---

## Commit

`96c4677` — `fix(faz6-prep): SPA build, ReportEngine GROUP BY, employees.position_id FK`  
15 files, +911/−25 · **push yok.**
