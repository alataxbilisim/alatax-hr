# UZUN KOŞU — Faz 6 öncesi genel sağlık turu

**Branch:** `faz4-form-engine`  
**Kural:** ürün kodu değiştirilmedi · `alatax_hr` wipe yok · push yok  
**Tarih:** 2026-08-05  
**Amaç:** ölçüm; düzeltme yok. Araçlar: `backend/scripts/uzun-kosu/*` (ölçüm script’leri).

---

## 1) Kararlılık — suite `--order-by=random` × 5

| # | Tohum (seed) | Süre wall (s) | Duration PHPUnit (s) | Sonuç | Log |
|---|---|---:|---:|---|---|
| 1 | 1785946250 | 1133 | 1110.69 | **725 passed** | docs/uzun-kosu-suite-1.log |
| 2 | 1785947440 | 1144 | 1099.16 | **725 passed** | docs/uzun-kosu-suite-2.log |
| 3 | 1785948557 | 1080 | 1059.37 | **725 passed** | docs/uzun-kosu-suite-3.log |
| 4 | 1785949654 | 1143 | 1110.57 | **725 passed** | docs/uzun-kosu-suite-4.log |
| 5 | 1785950782 | 1035 | 1014.57 | **725 passed** | docs/uzun-kosu-suite-5.log |

**Flaky:** yok (5/5 yeşil).  
**Süre trendi:** Tur4 765s → Tur5 1258s → bu tur ort. **1079s** (1015–1111). Tur5 bandında; Tur4’e göre ~+40%. Sapma ~96s. Yoğunluk: DemoSeed / PermissionSeeder feature testleri (10–18s/adet).

---

## 2) Ölçekli veri — `alatax_hr_testing`

Komut: `docker exec -e DB_DATABASE=alatax_hr_testing alatax-hr-app php scripts/uzun-kosu/seed-scale.php`

| Metrik | Değer |
|---|---:|
| companies | scale-co-1..3 |
| employees | **3000** |
| users_scale | **3000** |
| attendance_records | **1_500_000** |
| leave_requests | **50_001** |
| employee_documents | **20_001** |
| elapsed_sec | **191.6** |

`alatax_hr` dokunulmadı. (Adım 9 `migrate:fresh` sonrası scale silindi — ham sayılar log/JSON’da.)

---

## 3) Sorgu sağlığı (ölçekli veri üzerinde)

Kaynak: `docs/query-health.json`, `docs/explain.json`, `docs/uzun-kosu-group-scope.log`

### Liste / agg (DB::listen)

| Case | query_count | wall_ms | max_query_ms | ≥300ms |
|---|---:|---:|---:|---|
| employees_list_unfiltered | 1 | 3.6 | 3.2 | yok |
| employees_list_filtered | 1 | 2.0 | 1.7 | yok |
| leave_requests_list | 1 | 8.8 | 8.5 | yok |
| attendance_monthly_agg | 1 | 55.6 | 33.6 | yok |

### 11 dataset (company scope, limit 50)

| Dataset | queries | wall_ms | max_query_ms |
|---|---:|---:|---:|
| employees | 8 | 919.8 | 7.2 |
| leave_requests | 6 | 47.4 | 25.8 |
| leave_balances | 2 | 17.6 | 6.0 |
| expense_claims | 6 | 38.3 | 10.8 |
| job_applications | 6 | 41.3 | 11.8 |
| survey_responses | 2 | 34.8 | 8.9 |
| attendance_records | 2 | 199.2 | **183.6** |
| assets | 6 | 20.5 | 3.8 |
| training_participants | 2 | 17.8 | 5.9 |
| employee_documents | 2 | 16.0 | 2.0 |
| payslips | 2 | 29.0 | 6.8 |

**≥300 ms sorgu:** yok (slow_count=0).  
**N+1:** liste uçlarında 1 sorgu; dataset preview 2–8 sorgu (custom field + join — kabul edilebilir).  
employees preview wall 920ms ama SQL sum 28ms → PHP/guard maliyeti.

### EXPLAIN ANALYZE (exec_ms)

| Sorgu | Execution Time (ms) |
|---|---:|
| employees_filter | 0.299 |
| attendance_month | 2.653 |
| leave_list | 4.414 |
| position_group | 0.344 |
| attendance_order_limit | 0.927 |

### Group scope

- İzin yokken: `Grup kapsamı için yetkiniz yok (reports.scope.group).`
- `reports.scope.group` verilince: `company_ids=[1189,1190,1191]`, queries=10, wall=929ms, count=50.

### İndeks adayları (eklenmedi)

| Aday | Gerekçe |
|---|---|
| `(company_id, position)` on employees | filtre + gruplama; şu an ölçekte ms altı ama prod ad→code karışık |
| attendance dataset default `ORDER BY date` + limit without covering | 183ms max; `(company_id, date DESC)` zaten var — SELECT * genişliği maliyet |

---

## 4) PDKS eşzamanlılık

### Service burst (tek süreç, 500 personel)

| Metrik | Değer |
|---|---|
| ok / err | **500 / 0** |
| deadlocks | **0** |
| wall_ms | 5391.5 |
| p50 / p95 / p99 ms | 9.35 / 14.62 / 19.07 |
| records_today | 500 |
| dup_users | **0** |

### Aynı personel 5× punch

| | |
|---|---|
| Beklenen | 1. clock_in, 2. clock_out, 3+ hata → **tek satır** |
| Gerçekleşen | in → out → ×3 `"Bugün zaten giriş yapmışsınız"` → **record_count=1** |
| Not | Out sonrası hata metni “zaten çıkış” değil “zaten giriş” (punch tekrar clockIn’e düşüyor) |

### Parallel proc_open (40 worker)

| Metrik | Değer |
|---|---|
| Sonuç | **altyapı başarısız** — PHP autoload race (`Trait EnumeratesValues not found`); results dosyasında 10 satır |
| deadlock | 0 (gözlenen) |
| dup | 0 |

**Sonuç:** uygulama katmanında çift kayıt / deadlock yok (service burst). Gerçek 500 HTTP paralel için ayrı kiosk-token + curl_multi gerekir (script iskeleti: `pdks-http-parallel.sh`).

---

## 5) Kuyruk ve zamanlanmış işler

Kaynak: `docs/jobs-summary.json`

| İş | elapsed_ms | peak_mb | Sonuç |
|---|---:|---:|---|
| LeaveAccrualBatch (all active) | 517.7 | 26 | processed=3 scale co, failed=0 |
| DocumentExpiryAlert processCompany **WITHOUT** context | 53 | 26 | **CompanyContextMissingException** ✓ |
| DocumentExpiryAlert WITH context | 62.7 | 26 | checked=0 ok |
| DocumentExpiryAlert processAllActive | 87 | 28 | failed=0 (içerde CompanyContext::run) |
| ApprovalEscalation WITHOUT context | 18.3 | 28 | **CompanyContextMissingException** ✓ |
| ApprovalEscalation WITH context | 19 | 28 | ok |
| kvkk:scan-retention --company | 3021.7 | 34 | aday=0 |
| ProcessHeavyReportExportJob companyId=0 | 155.1 | 34 | **CompanyContextMissingException** ✓ |
| ProcessHeavyReportExportJob with company | 2252.6 | 40.5 | report_id ok |
| RunReportScheduleJob | 218.5 | 40.5 | schedule ok |

**Kritik:** Toplu entry-point’ler (`processAll*`, job handle) context set ediyor. `processCompany` doğrudan çağrıda exception atıyor — beklenen güvenlik. Hacim altında da geçerli.

---

## 6) Bellek ve sızıntı (50×)

Kaynak: `docs/memory-summary.json`

| Uç | mem_first→last MB | slope MB | p50 ms | max ms | Not |
|---|---|---:|---:|---:|---|
| employees_preview_50 | 30→32 | +2 | 8.9 | 981.9 | hafif yükseliş |
| attendance_monthly_sql | 32→32 | 0 | 10.4 | 722 | düz; bir spike |
| export_employees_5k | 32→36 | +4 | 12.1 | 14.6 | rows=3000, json≈224 KB, ~12ms |
| position_group_raw_sql | 36→36 | 0 | 1.6 | 2.3 | düz |

**Bulgu:** ReportEngine `group_by: ['position']` + default sort `employee_code` → SQLSTATE 42803 (GROUP BY hatası). Ham SQL gruplama sorunsuz. Export sızıntısı belirgin değil (+4MB/50 koşu).

---

## 7) Ölü kod / SPA tutarlılık

### tsc / lint / build

| App | tsc | lint | build | Ana JS | gzip |
|---|---|---|---|---:|---:|
| company | **FAIL** EmployeesPage.tsx:303 full_name/name ∉ Employee | OK | **FAIL** | — | — |
| portal | OK | OK | OK | **1512 kB** | 492 kB |
| superadmin | OK | OK | OK | 593 kB | 186 kB |

**1 MB borcu:** portal **1.51 MB** — açık.

### Ölü / tutarsız (silinmedi)

| Bulgu | Ham |
|---|---|
| `frontend/_archive_old_app` | dizin adı mevcut (içerik boş/erişilemez sayım=0) |
| Company pages | 105 tsx |
| Portal pages | 20 tsx |
| route:list satır | ~715 |
| Docs sapması | FAZ4_RAPOR “position=serbest metin” — Tur8 code yazıyor; prod hâlâ %100 ad |
| Çift servis | CompanyContextService vs Support\CompanyContext — farklı katman; ikisi de canlı |

---

## 8) Pozisyon karışık veri

### Prod `alatax_hr`

| match_code | match_name_only | match_neither | empty | total |
|---:|---:|---:|---:|---:|
| **0** | **60** | **0** | **2** | **62** |

→ Tüm dolu satırlar **ad**; code 0. Aynı ad birden fazla `positions` satırına düşebiliyor (`as_name_hits` 1–2).

### Ölçek (seed karışık)

| match_code | match_name_only | match_neither |
|---:|---:|---:|
| 334 | 333 | 333 |

`GROUP BY position` → **335 ayrı satır** (DEMO_POS_07 + Yönetici + 333 orphan). Aynı görünen unvan code vs name ile **iki satır** olabilir (prod’da henüz code yok; Tur8 sonrası yeni kayıtlar code yazınca oluşacak).

### Etkilenen yüzeyler

- Dataset: `employees` (position alanı)
- FE: Employees list/detail, EmployeeForm / FormEngine, rapor builder gruplama
- API Resource: `position` + `position_label`

### Geçiş seçenekleri (uygulama yok)

| Seçenek | Maliyet | Risk |
|---|---|---|
| A) Normalize name→code (tek eşleşme) | ~0.5g | aynı adlı 2 pozisyon belirsiz |
| B) `position_id` FK | ~1.5–2g | migration + form + rapor |
| C) Dual-read süresiz | düşük | borç |

---

## 9) Temiz kurulum provası (`alatax_hr_testing`)

Komut: `php scripts/uzun-kosu/clean-install-proof.php` — **total 39.74s**

| Adım | sn | Sonuç |
|---|---:|---|
| migrate:fresh | 9.17 | OK — full_name + panel_access migration’ları koştu |
| db:seed | 4.36 | OK |
| panel_access check | 0.01 | roles=6; employee=false; admin=true; full_name kolonu var |
| demo:seed | 26.19 | sentinel OK |
| demo:align-organizations | 0.01 | org_id=4 üç şirket |
| verify | 0 | single_org=true; admin@demo.test üye [1,2,3] |

**Group scope (demo):** `company_ids=[1,2,3]`, report_scope=group ✓  
**panel_access backfill boş roles:** migrate sırasında roles boş → sonra seeder `panel_access` set ediyor ✓

---

## Bulgu kovaları

### Faz 6’ya girmeden kapatılmalı

1. **Company SPA build kırık** — `Employee` tipine `full_name`/`name` eklenmeli (`EmployeesPage.tsx:303`).
2. **Prod `employees.position` %100 ad** — Tur8 FE code yazıyor; rapor/gruplama yakında çift satır üretecek. Normalize veya dual-read kararı.
3. **ReportEngine GROUP BY + default sort** — `group_by: position` iken `ORDER BY employee_code` → SQL 42803.

### Faz 6 sırasında

1. Portal bundle **1.51 MB** (1 MB borcu) — code-split.
2. Vitest + Testing Library (shared Select) — Tur8 önerisi.
3. Dataset `attendance_records` preview ~184ms / wall ~200ms — alan daraltma / index gözden geçirme.
4. PDKS gerçek 500 HTTP paralel bench (kiosk token + curl_multi) — autoload-safe.

### Backlog

1. `frontend/_archive_old_app` temizliği / repo’dan çıkarma.
2. `processCompany` doğrudan çağrı dokümantasyonu (exception beklenen).
3. Punch 3. hata mesajı metni (“zaten giriş” vs “zaten çıkış”).
4. Suite süresi ~1080s — DemoSeed testlerinin ağırlığı (paralel PHPUnit / seed cache).
5. Docs: FAZ4_RAPOR position ifadesini Tur8 ile hizala.
6. İsteğe bağlı indeks `(employees.company_id, position)`.

---

## Artifact’lar

| Dosya | İçerik |
|---|---|
| docs/uzun-kosu-suite-{1..5}.log | suite logları |
| docs/uzun-kosu-suite-summary.txt | koşu özeti |
| docs/uzun-kosu-scale-seed.log | ölçek seed |
| docs/query-health.json / explain.json | sorgu |
| docs/pdks-summary.json | PDKS service |
| docs/jobs-summary.json | işler |
| docs/memory-summary.json | bellek |
| docs/clean-install.json | kurulum provası |
| docs/uzun-kosu-position-mix.txt | prod position sayımı |
| backend/scripts/uzun-kosu/* | ölçüm script’leri |

**Push yok. Ürün kodu değiştirilmedi.**
