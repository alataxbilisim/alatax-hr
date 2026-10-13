# Denetim Turu — Faz 6 öncesi bakılmamış alanlar

**Branch:** `faz4-form-engine`  
**Tarih:** 2026-08-05  
**Tür:** Teşhis (ürün kodu değiştirilmedi)  
**Ham çıktı:** `docs/denetim-raw.json`  
**Script:** `backend/scripts/denetim/run-denetim.php` (yalnız teşhis)

## DB / yedek

| Ortam | İşlem |
|-------|--------|
| `alatax_hr` | **pg_dump alındı** (değiştirilmeden önce) |
| Dump yolu | `docs/backups/alatax_hr_20260805_230705.dump` (~20.4 MB, custom format `-Fc`) |
| `alatax_hr_testing` | `migrate:fresh --seed` ile teşhis koşturuldu |

---

## 1) Silme / cascade matrisi

Gerçek denemeler (`alatax_hr_testing`). API guard varsa ayrıca not edildi.

| Senaryo | API davranışı | Eloquent / DB (gerçekleşen) | Soft-delete yan etki | Kova |
|---------|---------------|----------------------------|----------------------|------|
| **Şubesi olan şirket sil** | `Admin\CompanyController::destroy`: kullanıcı varsa **400** (şube kontrolü yok) | **Soft:** şirket `deleted_at` dolar; şube **durur** (FK tetiklenmez). **Force:** şube CASCADE ile silindi; ardından `Auditable` `activity_logs` insert’i silinmiş `company_id` yüzünden **FK 23503** → süreç kırılıyor | Soft’ta şube yetim (aktif görünür, şirket soft) | **Faz 6 öncesi kapat** (force+audit tutarlılığı; soft orphan politikası) |
| **Personeli olan departman sil** | **422** — “Önce personelleri taşıyın” | Soft-delete API’siz denenince **başarılı**; `employees.department_id` **eski id’de kalır** (SET NULL soft’ta çalışmaz) | Personel “silinmiş departmana” bağlı kalır | **Faz 6 sırasında** (API guard var; soft orphan riski) |
| **Personeli olan pozisyon sil** | Yalnız `is_system` engeli; **personel kontrolü YOK** | Soft-delete **OK**; `position_id` eski id’de. **ForceDelete:** FK `nullOnDelete` → `position_id = null` | Soft’ta personel yetim FK tutar | **Faz 6 öncesi kapat** (API’de personel/usage guard) |
| **İzni / zimmeti / evrağı olan personel sil** | Soft-delete, engel yok | Soft-delete **OK**. LeaveRequest, AssetAssignment, EmployeeDocument **yerinde** (`deleted_at` null). Dosya diskten **silinmez** | İlişkili kayıtlar parent’sız / soft-parent’lı kalır | **Faz 6 sırasında** (puantaj/zimmet/evrak yaşam döngüsü) |
| **Rolü atanmış kullanıcı sil** | Soft-delete (kendini silme hariç engel yok) | Soft-delete **OK**. `model_has_roles` pivot **1→1 kaldı** (yetim pivot) | Spatie pivot soft-delete temizlemiyor | **Faz 6 öncesi kapat** |
| **Onay zincirinde adımı olan rol sil** | Kullanıcı varsa **400**; korumalı rol **403** | Eloquent `Role::delete()` Auditable yüzünden hata: `Class name must be a valid object or a string`. `approval_steps.specific_role` **string**, FK yok → rol gitse bile adım **yetim string** bırakır | N/A (Role soft-delete değil) | **Faz 6 öncesi kapat** (rol silme + adım referansı) |

### Ek gözlemler

- Soft-delete **hiçbir FK cascade/SET NULL tetiklemez**; şema kuralları yalnız hard DELETE’te geçerli.
- Şirket hard-delete + Auditable log sırası **tutarsız** (önce cascade, sonra log FK kırığı).
- Zimmet `asset_assignments.user_id` üzerinden; personel soft-delete zimmeti otomatik kapatmıyor.

---

## 2) Zaman dilimi

### Ham sonuç

| Katman | Değer |
|--------|--------|
| `config('app.timezone')` | **`UTC`** (hardcoded `config/app.php`; `.env`’de `APP_TIMEZONE` yok) |
| PHP `date_default_timezone` | UTC |
| PostgreSQL `SHOW timezone` | UTC |
| `attendance_records.date` | `date` |
| `clock_in` / `clock_out` | **`time without time zone`** |
| `created_at` / `updated_at` | **`timestamp without time zone`** (timestamptz değil) |

### Punch testi (`AttendanceClockService` → `now()->toDateString()`)

| Simüle an | TR karşılığı | Yazılan `date` | Yazılan `clock_in` |
|-----------|--------------|----------------|---------------------|
| 2026-08-05 20:30 UTC | 23:30 TR (aynı gün) | **2026-08-05** | 20:30 (UTC saati) |
| 2026-08-05 21:30 UTC | **00:30 TR 6 Ağustos** | **2026-08-05** (UTC günü) | 21:30 |

**Sonuç:** Uygulama Türkiye gününü değil **UTC takvim gününü** yazıyor. Gece 00:00–02:59 TR aralığında punch bir önceki UTC gününe düşer. `clock_in` duvar saati de UTC.

### Aylık sınır

`Carbon::…->startOfMonth()/endOfMonth()` app TZ=UTC ile kesiliyor → TR gece yarısı kayması aylık puantaj raporunda sınır hatalarına yol açabilir.

### Varsayım

Kod **Europe/Istanbul sabit +3 varsaymıyor**; sunucu/app **UTC**’ye güveniyor. DST yokluğu TR için avantaj; ama TZ yanlış seçilmiş.

**Kova:** **Faz 6 öncesi kapat** (puantaj modülü öncesi `Europe/Istanbul` + date/time semantiği).

---

## 3) Para hassasiyeti

### Kolon tipleri (ücret / masraf / bordro odaklı)

Tüm incelenen tutar kolonları PostgreSQL **`numeric(p,s)`** (Laravel `decimal`).  
**float / double precision: 0 adet** (`float_double_hits: []`).

Öne çıkanlar:

| Tablo | Kolon | Tip |
|-------|-------|-----|
| `employees` | `gross_salary`, `net_salary` | numeric(12,2) |
| `payslips` | `gross_salary`, `net_salary`, `total_*` | numeric(12,2) |
| `payslips` | `bonuses`, `deductions` | **jsonb** (içerik tip kontrolü yok) |
| `expense_claims` | `total_amount` | numeric(12,2) |
| `expense_items` | `amount` | numeric(12,2) |
| `salary_records` / `salary_bands` / `salary_review_items` | amount alanları | numeric(12,2) |
| `company_ledger` | `amount`, `balance_after` | numeric(12,2) |

### Yuvarlama (servis)

| Servis | Davranış |
|--------|----------|
| `SalaryRecordService` | `round((float)$amount, 2)` yazarken |
| `SalaryReviewService` | proposed / increase_percent → `round(..., 2)` |
| `SalaryBandService` | ratio → `round(..., 4)` |
| `LeaveCalculationService` accrual | `getMonthlyAccrual(): float` + `+=` — **BCMath yok** |
| Expense | DB decimal; toplama servisinde merkezi para tipi yok |

**Risk:** DB decimal olsa da PHP `(float)` cast IEEE kaybı üretebilir (yüksek tutarlarda).

**Kova:** Para tipi/float cast → **backlog** (kritik bordro/masraf hesabı öncesi). Accrual float → **Faz 6 sırasında** (izin motoru).

---

## 4) Türkçe metin

| Kontrol | Sonuç |
|---------|--------|
| DB collation | **`en_US.utf8`** / `en_US.utf8` |
| `ILIKE '%istanbul%'` → `İstanbul` | **Buldu** |
| `ILIKE '%ismail%'` → `ismail` + `İSMAİL` | **İkisini de buldu** |
| `ORDER BY full_name` (default) | `Uçar, Çakır, Öz, Ünal, Şahin` — **TR alfabe değil** |
| `ORDER BY … COLLATE "tr-TR-x-icu"` | `Çakır, Öz, Şahin, Uçar, Ünal` — **doğru** |

Liste sıralaması varsayılan collation ile Türkçe değil; ICU `tr-TR` mevcut ve doğru.

**Kova:** Arama çoğu senaryoda çalışıyor → **backlog**. Liste sıralaması TR → **Faz 6 sırasında** (personel listeleri) veya backlog.

---

## 5) Dosya depolama

### Yapılandırma

| Disk | Root | Env |
|------|------|-----|
| default | `FILESYSTEM_DISK=local` | `.env` |
| `local` (private) | `storage/app/private` | on-prem path volume ile taşınabilir |
| `public` | `storage/app/public` | `APP_URL/storage` |
| `s3` | AWS_* | `FILESYSTEM_DISK=s3` ile on-prem→S3 geçiş mümkün |

### Silme davranışı

- `EmployeeDocumentController::destroy`: diskten `Storage::disk('private')->delete` + model silme.
- `EmployeeController::destroy`: yalnız soft-delete — **dosya ve evrak satırı kalır**.
- KVKK collector soft-delete/anonimizasyonda private dosya silebilir.

### Yetim tarama (container disk)

| Metrik | Değer |
|--------|------:|
| Private dosya | 293 |
| Public dosya | 2 |
| `alatax_hr` DB `file_path` (documents+versions+employee_documents) | 5 (employee_documents: **0**) |
| Diskte DB karşılığı olmayan (vs `alatax_hr`) | **~293** |
| Ağırlık | Çoğu `kvkk-exports/...` test/export artığı |

**Kova:** Yetim cleanup job + personel silince evrak politikası → **Faz 6 sırasında**. On-prem path dokümantasyonu → **backlog**.

---

## 6) Kuyruk dayanıklılığı

| Madde | Sonuç |
|-------|--------|
| `jobs` tablosu | Var |
| `failed_jobs` tablosu | Var |
| Failed driver | `database-uuids` |
| `.env` `QUEUE_CONNECTION` | `database` (runtime’da redis cache/compose de görülebilir) |
| `retry_after` | 90 sn (database/redis) |
| Kasten patlayan job | `failed_jobs`’a **düştü** (uuid alındı) |
| `queue:retry {uuid}` | **Çalıştı** (exit 0); job yeniden fail → yine `failed_jobs` |
| Accrual batch | `LeaveAccrualBatchService`: firma bazlı try/catch — bir firma patlar, diğerleri devam |
| Accrual tek firma içi | `processMonthlyAccruals`: **`DB::transaction` yok** — balance+log ayrı save → **yarım iş kalabilir** |

**Kova:** Accrual transaction sınırı → **Faz 6 sırasında**. Failed job izleme/alert → **backlog**.

---

## 7) Soft delete + unique

| Alan | Unique? | Soft-delete sonrası aynı değerle yeni kayıt |
|------|---------|---------------------------------------------|
| `employees.employee_code` (+company) | Evet | **Engelleniyor** (23505) |
| `employees.national_id` | **Yok** | **Açılabiliyor** (çift TCKN) |
| `users.email` | Evet (global) | **Engelleniyor** |
| `branches.code` (+company) | Evet | **Engelleniyor** |
| `positions.code` (+company) | Evet | **Engelleniyor** |

Soft-deleted satırlar unique index’te kalır → sicil/e-posta/kod **yeniden kullanılamaz** (force-delete veya partial unique gerekir).

**Kova:** Soft-delete + partial unique (`WHERE deleted_at IS NULL`) → **Faz 6 öncesi kapat** (operasyonel acı). TCKN unique → **Faz 6 sırasında** (KVKK/kimlik).

---

## 8) E-posta doğrulama (Faz 0 borcu)

### Kod durumu

| Kontrol | Sonuç |
|---------|--------|
| `User` implements `MustVerifyEmail` | **Hayır** |
| `AuthController::register` | Token **hemen** verilir; `email_verified_at` set edilmez |
| Login / route `verified` middleware | **Yok** |
| `sendEmailVerificationNotification` | AuthController’da **0** kullanım |
| Davet akışı | `InvitationService` → `email_verified_at ??= now()` (davet = doğrulanmış sayılır) |

### SMTP / Mailtrap

| Env | Değer |
|-----|--------|
| `MAIL_MAILER` | **`log`** (Mailtrap/SMTP aktif değil) |
| `MAIL_HOST` | 127.0.0.1:2525 (kullanılmıyor; mailer=log) |
| Uçtan uca doğrulama maili | **Çalışmıyor** — akış kodda yok; mail log’a düşer |

**Kova:** Faz 0 borcu — **backlog** (Faz 6 bloğu değil; güvenlik/compliance için planlanmalı). Mailtrap/SMTP prod öncesi yapılandırma checklist.

---

## Özet kovalar

### Faz 6 öncesi kapat
1. `app.timezone` → `Europe/Istanbul` + attendance date/clock semantiği  
2. Pozisyon silmede personel/usage guard  
3. Kullanıcı soft-delete → `model_has_roles` cleanup  
4. Rol silme + `approval_steps.specific_role` yetim string  
5. Soft-delete + unique (partial unique veya reuse politikası)  
6. Company forceDelete ↔ Auditable / activity_logs sırası  

### Faz 6 sırasında
1. Personel silme → izin/zimmet/evrak/dosya yaşam döngüsü  
2. Accrual transaction sınırı  
3. Yetim dosya cleanup (özellikle `kvkk-exports`)  
4. TCKN unique (iş kuralı)  
5. Departman soft-orphan politikası  

### Backlog
1. E-posta doğrulama akışı (Faz 0)  
2. Para: `(float)` cast → BCMath / decimal value object  
3. TR collation varsayılan liste sıralaması  
4. Failed job monitoring / alert  
5. On-prem storage runbook  

---

## Kanıt dosyaları

- Dump: `docs/backups/alatax_hr_20260805_230705.dump`  
- Ham JSON: `docs/denetim-raw.json`  
- Script: `backend/scripts/denetim/run-denetim.php`  

**Push yok. Ürün kodu değiştirilmedi.**
