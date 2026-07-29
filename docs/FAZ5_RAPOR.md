# FAZ 5 — Rapor & Analitik Motoru

Branch: `faz4-form-engine`

---

## D1a — Semantic layer + güvenli query builder (BACKEND)

**Tarih:** 2026-07-29  
**Commit:** `feat(faz5): D1a rapor motoru — dataset registry + güvenli query builder`

### ADIM 0 — Teşhis

| Alan | Bulgu |
|------|--------|
| `saved_reports` | Var (personel config jsonb); dataset_key yoktu → eklendi |
| `/analytics` | Sabit `HrAnalyticsController` sorguları; DataScope yok |
| `employee_dashboards` | Widget JSONB; yalnız Employee aggregation |
| Export | BE CSV/PhpSpreadsheet; FE jsPDF; DomPDF yok |
| Semantic layer | **Yoktu** → D1a ile kuruldu |

### Dataset registry (5)

| Key | Alan (yaklaşık) | DataScope | Custom fields |
|-----|-----------------|-----------|---------------|
| `employees` | ~17 + custom | employee | `custom_fields` |
| `leave_requests` | ~9 + custom | user | `custom_fields` |
| `leave_balances` | 8 | user | — |
| `expense_claims` | ~11 + custom | user | `custom_fields` |
| `job_applications` | ~11 + custom | assigned_to | `form_data` |

### Güvenlik katmanları (bypass yok)

1. Dataset / alan / join / operatör / aggregation **whitelist**
2. `BelongsToCompany` global scope
3. `DataScopeService` (viewer kapsamı — paylaşımda sahip miras alınmaz)
4. Alan izni (`employees.salary.view`) — yoksa alan düşer; sum da 422
5. Değerler yalnızca bound parametre

### API

| Method | Path | Permission |
|--------|------|------------|
| GET | `/api/v1/reports/datasets` | `reports.definitions.view` |
| GET/POST | `/api/v1/reports` | view / create |
| GET/PUT/DELETE | `/api/v1/reports/{id}` | view / edit / delete |
| POST | `/api/v1/reports/{id}/run` | `reports.definitions.run` |
| POST | `/api/v1/reports/preview` | `reports.definitions.run` |

### Test

| Suite | Sonuç |
|-------|--------|
| `ReportEngineSecurityTest` | **8 passed** |
| `ReportEngineApiTest` | **5 passed** |
| Tam suite | **501 passed / 0 fail** |
| 3 SPA `tsc` + lint + sentinel | **0 / PASSED** |
| UI | **yok** (D1b) |
| DB wipe | **yok** |

### DUR / sonraki

- D1b: Company UI (dataset seçici + pivot/chart)
- Kalan dataset’ler (puantaj, eğitim, zimmet, anket)
- `/analytics` sabit sorgularını motor üzerine taşıma

---

## D1b — Rapor Builder UI + ECharts / TanStack Table + export

**Tarih:** 2026-07-29  
**Commit:** `feat(faz5): D1b rapor builder UI + echarts/tanstack-table + export`

### Paketler

| Paket | Nerede | Not |
|-------|--------|-----|
| `echarts` + `echarts-for-react` | shared + company | Tree-shake: yalnız Bar/Line/Pie + Grid/Tooltip/Legend |
| `@tanstack/react-table` + `@tanstack/react-virtual` | shared + company | Sanallaştırılmış tablo |
| Nivo / recharts | **dokunulmadı** | `/analytics` + personel BI aynen |

### Sarmalayıcılar (`@shared`)

- `<ReportChart type=… />` — tema token’ları (`--primary`, `--success`, …)
- `<ReportTable … />` — sunucu sıralama/sayfalama + sütun genişliği

### Ekranlar

| Route | İçerik |
|-------|--------|
| `/reports` | Benim / paylaşılan / sistem + ara + çalıştır/düzenle/kopyala/sil |
| `/reports/new`, `/:id/edit` | 3 panel builder + debounce preview (~500ms) |
| `/reports/:id` | Çalıştır / görüntüle |

ModuleRail: Analitik → **Rapor Motoru** (`reports.definitions.view`).

### Export

- `POST /reports/export` + `POST /reports/{id}/export` — query builder, max **50k**, `truncated` meta
- FE: ExcelJS + jsPDF (sunucu satırları); grafik PNG (ECharts `getDataURL`)
- İzin: maaş alanı düşer; department scope satırları sınırlar (testli)

### Bundle

Rapor sayfaları `React.lazy` — echarts/tanstack yalnız `/reports*` chunk’ında.

| Chunk | Boyut (min) | gzip |
|-------|-------------|------|
| `ReportBuilderPage-*.js` | **1031 kB** | **297 kB** |
| `ReportsListPage-*.js` | 4.2 kB | 1.6 kB |
| `reports-*.css` | 2.4 kB | 0.7 kB |

Ana `index-*.js` (~3.6 MB) Nivo/mevcut app; rapor paketleri lazy ayrıldı.

### Test

| Suite | Sonuç |
|-------|--------|
| Export güvenlik (+2) | salary drop + dept scope |
| Tam suite | **503 passed / 0 fail** |
| 3 SPA `tsc` + lint + sentinel | **0 / PASSED** |
| DB wipe | **yok** |

### Not

**KULLANICI GÖRSEL KONTROLÜ BEKLİYOR (borç)**

### DUR / sonraki

- Daha fazla dataset; `/analytics` motora taşıma; zamanlanmış rapor

---

## D1c — Pivot + drill-down + hesaplanan ölçü DSL

**Tarih:** 2026-07-29  
**Commit:** `feat(faz5): D1c pivot + drill-down + hesaplanan ölçü DSL'i`

### ADIM 0 — Teşhis

| Bulgu | Karar |
|-------|--------|
| GROUP BY çok boyutlu zaten var; alt toplam yok | Pivot = GROUP BY + PHP matris |
| Hiyerarşi yoktu | `AbstractDataset::hierarchies()` eklendi |
| Postgres `crosstab` | **Kullanılmadı** — taşınabilirlik + whitelist builder ile aynı yol |

### Pivot

- Satır 1–3, sütun 0–2, ölçü 1–n; `(boş)` etiketi; `date_trunc`/`EXTRACT` grain whitelist
- Kardinalite guard: sütun distinct **>100 → 422**
- Alt / genel toplam opsiyonel (uygulama katmanı)

### Drill

- `org`: şube → departman → pozisyon → sicil; tarih grain hiyerarşisi
- `POST /reports/drill` mode `next` \| `details` — details de query builder (izin + DataScope)

### DSL

- Recursive-descent parser (symfony EL yok — kapalı dil + sıfır bağımlılık)
- Agg: sum/count/count_distinct/avg/min/max · aritmetik · if · karşılaştırma · and/or
- AST → parametreli SQL; `/` → `NULLIF`; izinsiz alan → 422 (çalıştırma anı)
- `report_measures` + `reports.measures.view\|edit` + Auditable

### UI

- Builder: Pivot görünümü + formül doğrulama; `/reports/measures` kütüphane
- PivotMatrix: birleşik başlık, yapışkan satır, hücre → detay

### Güvenlik testleri (örnek)

`;DROP`, union/select, alt sorgu, abs/length, quote/escape, null byte, izinsiz `sum(gross_salary)`

### Test

| Suite | Sonuç |
|-------|--------|
| `MeasureExpressionSecurityTest` | **24 passed** |
| `ReportPivotAndMeasureTest` | **8 passed** |
| Tam suite | **535 passed / 0 fail** |
| 3 SPA tsc + lint + sentinel | **PASSED** |
| DB wipe | **yok** |

### Not

**KULLANICI GÖRSEL KONTROLÜ BEKLİYOR (borç)**

---

## D1d — Dashboard v2 (widget + çapraz filtre + parametreli filtreler)

**Tarih:** 2026-07-29  
**Commit:** `feat(faz5): D1d dashboard v2 — widget + çapraz filtre + parametreli filtreler`

### ADIM 0 — Teşhis / karar

| Bulgu | Karar |
|-------|--------|
| `employee_dashboards` | Personel BI (`/employees/reports`, `employees.reports.*`); widget şeması farklı |
| Yeni yapı | **Ayrı tablolar** `dashboards` + `dashboard_shares` — çakışma yok, personel BI kırılmaz |
| `react-grid-layout` | company’de `^2.1.1` (personel BI grid) — Dashboard v2 aynı paket |
| `/analytics`, ana `/dashboard` | **D1d’de değişmedi** (motora taşıma ayrı iş) |

### Veri modeli

- `dashboards`: company_id, owner_id, name, description, layout jsonb, global_filters jsonb, is_system, created_by
- `dashboard_shares`: user/role + viewer\|editor
- Widget: layout.widgets[] — id, tip, grid, title, report_id \| config, visual, refresh_interval
- Permission: `reports.dashboards.view\|create\|edit\|delete` · Auditable

### Widget tipleri

KPI · Grafik (ReportChart) · Tablo (ReportTable) · Pivot (PivotMatrix) · Metin (XSS escape)  
Kaynak varsayılan: kayıtlı rapor; inline config alternatif. **Motor tüketici** — ayrı sorgu yolu yok.

### Guard / yenileme

| Guard | Değer |
|-------|--------|
| Max widget | **20** |
| Widget timeout | **8 sn** (soft flag) |
| Batch timeout | **45 sn** → kısmi + uyarı |
| Polling min | **30 sn**; sekme gizliyken DURUR |
| WebSocket/Reverb | **Yok** (DUR) |

### Filtreler

- Global: tarih kısayolu / departman / şube (+ tanım alanları) → URL; dataset’te yoksa sessiz atla
- Çapraz: seçim geçici, kaydedilmez; `ignore_cross_filter` widget ayarı
- Paylaşım: **viewer DataScope** (sahip miras alınmaz) — testle kanıtlı

### UI

- `/dashboards`, `/dashboards/:id` · ModuleRail Analitik → **Panolar**
- Düzenleme: react-grid-layout; yetkisiz → salt görüntüleme
- Mobil: tek sütun

### Test

| Suite | Sonuç |
|-------|--------|
| `DashboardV2Test` | **8 passed** (batch izolasyon, kapsam, maaş gizleme, filtre skip, 21. widget 422, 401/403) |
| Tam suite | **543 passed / 0 fail** |
| 3 SPA tsc + lint + sentinel | **PASSED** |
| DB wipe | **yok** |

### Not

**KULLANICI GÖRSEL KONTROLÜ BEKLİYOR (borç)**

---

## D1e — Paylaşım v2 + şeffaf alan gizleme + erişim denetimi + hassasiyet guard

**Tarih:** 2026-07-29  
**Commit:** `feat(faz5): D1e paylaşım v2 + şeffaf alan gizleme + erişim denetimi + hassasiyet guard`

### Paylaşım matrisi

| Seviye | Çalıştır/filtre/export | Tanım düzenle | Sil | Paylaşım | Sahiplik devri |
|--------|------------------------|---------------|-----|----------|----------------|
| viewer | evet | hayır | hayır | hayır | hayır |
| editor | evet | evet | hayır | hayır | hayır |
| owner | evet | evet | evet | evet | evet (+ transfer yetkisi) |

Hedef: kullanıcı / rol / departman. Klasör paylaşımı yok. Offboarding hook yok (DUR). Anonim/public yok.

### hidden_fields

`meta.hidden_fields`: `[{key,label,reason}]` — `field_permission` | `sensitivity` | `dataset_scope`. UI şerit + export notu.

### Access log

`report_access_logs`; filtre değeri ham yazılmaz (hash); append-only; `afterResponse`; 12 ay + purge komutu.

### Hassasiyet + min hücre

`personal` / `special` / `anonymous_source` / `normal`. Min hücre eşik 5. Alt/genel toplam: `mask_when_any_leaf_masked`.

### Test

| Suite | Sonuç |
|-------|--------|
| `ReportSharingPrivacyTest` | **7 passed** |
| Tam suite | **550 passed / 0 fail** |
| 3 SPA tsc + lint + sentinel | **PASSED** |
| DB wipe | **yok** |

### Not

**KULLANICI GÖRSEL KONTROLÜ BEKLİYOR (borç)**

---

## D1f — Zamanlanmış rapor + abonelik + cache/performans

**Tarih:** 2026-07-29  
**Commit:** `feat(faz5): D1f zamanlanmış rapor + abonelik + cache/performans katmanı`

### ADIM 0 — Teşhis

| Alan | Bulgu |
|------|--------|
| Scheduler | compose `schedule:work`; `routes/console.php` (Kernel yok) |
| Queue | compose `queue:listen` timeout 90s; Redis |
| Redis | queue + session + cache store; app kodunda Cache:: yoktu → D1f ekledi |
| Mail/C4 | `NotificationService` + queued `NotificationMail`; rapor olayı yoktu → eklendi |
| Export | Senkron 50k; ağır export için `ProcessHeavyReportExportJob` + `async=1` |

### Zamanlama şeması (`report_schedules`)

company_id, report_id | dashboard_id, owner_id, cadence (daily|weekly|monthly|cron), hour/minute/day, timezone, format (link|excel|pdf), recipients jsonb, filters jsonb, only_if_data, active, last_run_at, last_status, failure_count, next_run_at.

Permission: `reports.schedules.view|create|edit|delete`. Auditable. Komut: `reports:run-schedules` (her dakika).

### Kapsam / teslim kararları

- Her alıcı **kendi DataScope + alan izinleriyle** üretilir (sahip sonucu kopyalanmaz).
- Max **50** alıcı → 422 + daraltın mesajı.
- Yetkisi olmayan alıcı: `skipped_no_access` (gönderilmez).
- Varsayılan teslim: **link** (e-postada veri/ek yok; uygulamaya giriş). Anonim erişim yok.
- `special` / `anonymous_source` alan → ek (excel/pdf) **tamamen kapalı** (ayar açık olsa bile).
- 3 ardışık hata → `active=false` + sahibe `reports.scheduled.disabled`.
- Access log: `action=scheduled`.

### Cache anahtar formülü

`report_result:sha256({ report_id, dashboard_id, widget_id, config, scope_sig, global_ver })`

`scope_sig = sha256({ company_id, user_id, data_scope, scope_values, field_perms[] })`

TTL varsayılan **300 sn** (rapor `cache_ttl_seconds`; 0=kapalı). Tanım/ölçü değişince version bump. Kaynak tablo yazımında genel flush **YOK** (DUR — TTL yeter).

UI: `meta.computed_at` + `bypass_cache` / Şimdi yenile.

### Performans

- `SET LOCAL statement_timeout` (30s) rapor çalıştırmalarında.
- Yavaş sorgu logu: ≥5 sn → `report.slow_query`.
- Ağır export: `POST .../export` body `{async:true}` → job + bildirim.
- Ek indeksler: `employees(company_id, department_id, status)`, `leave_requests(company_id, status, start_date, end_date)`.

### Test

| Suite | Sonuç |
|-------|--------|
| `ReportScheduleAndCacheTest` | **6 passed** (cache izolasyon, zamanlama kapsam, 51 alıcı 422, 3 hata pasif, special ek engeli, bypass) |
| Tam suite | **556 passed / 0 fail** |
| 3 SPA tsc + lint + sentinel | **PASSED** |
| DB wipe | **yok** |

### Not

**KULLANICI GÖRSEL KONTROLÜ BEKLİYOR (borç)**
