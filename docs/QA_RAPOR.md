# QA Raporu — ALATAX HR

## QA-1 — Demo veri seeder + uçtan uca görsel/işlevsel süpürme

**Tarih:** 2026-07-30  
**Branch:** `faz4-form-engine`  
**Kapsam:** Teşhis only (ürün bug fix yok; seeder kendi hataları düzeltildi)  
**Suite:** `604 passed (2437 assertions)` — tek koşu yeşil  
**Komut:** `php artisan demo:seed` (production’da `RuntimeException`; `--fresh-demo` yok)

### Özet sayılar

| Ağırlık | Adet |
|---------|------|
| 🔴 kırık | 2 |
| 🟠 hatalı | 6 |
| 🟡 kozmetik | 8 |
| 🔵 iyileştirme | 3 |
| **Toplam** | **19** |

Ekran görüntüsü (anlamlı adlandırılmış): **16** (`docs/qa/e2e/01`…`18`, arada eksik numaralar var)

---

### Bulgular

| # | Ekran | Bulgu | Ağırlık | Ekran görüntüsü |
|---|-------|-------|---------|-----------------|
| 1 | Portal → İzinler | API’de personelin izinleri var (`GET /portal/leaves` 200, id 55/56/57) ama UI “Henüz izin talebiniz yok” boş state gösteriyor; liste senkronu kırık. | 🔴 | `11-portal-leaves-list.png` |
| 2 | Portal izin oluşturma → Onay motoru | 3g + 15g yıllık izin oluşturuldu (`pending`); `approval_instances` **0 satır**, `approval_workflow_id=null`. Koşullu adım (`total_days > 10`) motor tarafında doğrulanamadı — editördeki akış çalıştırılmıyor. | 🔴 | `18-workflows-list.png` |
| 3 | Portal → İzinlerim | Bakiye satırı `… gün kaldı` — sayı yok (API `remaining_days=12`). | 🟠 | `11-portal-leaves-list.png` |
| 4 | Portal → Yeni İzin Talebi | Form “Talep Oluştur” bazen sessizce kapanıyor / boş state’e dönüyor (en az bir kayıt #55 oluştu; UI yine boş). | 🟠 | `10` / `11` |
| 5 | Company dashboard | KPI “Açık Pozisyon: 0” (seed’de 3 ilan var; status/publish eşleşmesi şüpheli). | 🟠 | (önceki dashboard oturumu) |
| 6 | Lazy sayfalar (Rapor/Pano/KVKK ilk paint) | Suspense fallback `â€¦` mojibake (UTF-8 ellipsis bozulması). | 🟠 | `05-dashboards-mojibake.png`, kısa süre `12`/`15` |
| 7 | Company login | Demo ipucu hâlâ `test@test.com` / `Password123!` — seeder hesaplarıyla uyumsuz. | 🟡 | login ekranı |
| 8 | Company branding | Başlık/favicon metni “DOBEDAN HOTELS”; portal “ALATAX”. | 🟡 | login / panel |
| 9 | Dashboard karşılama | “Hoş Geldiniz, Demo !” — gereksiz boşluk + ünlem. | 🟡 | dashboard |
| 10 | Nav rail | Uzun etiketler kırpılıyor (“Ücret &…”, “Varlık &…”). | 🟡 | `01-nav-rail.png`, `02-nav-rail-with-pdks.png` |
| 11 | Portal rail | “Ana Sayfa” iki satıra bölünüyor. | 🟡 | `08-portal-login-or-home.png` |
| 12 | Davranış ayarları | Başlıklar TR, açıklamalar EN hardcode (“Allows carryover…”). | 🟡 | `16-settings-registry.png` |
| 13 | `/management/workflows` | Eski/yanlış URL → “Sayfa bulunamadı”; doğru yol `/settings/workflows`. | 🟡 | (404 ekranı) |
| 14 | Raporlar Sistem (erken oturum) | İlk süpürmede Sistem sekmesi boştu; `SystemReportPackageSeeder` + re-seed sonrası **50 sistem raporu** görünür. | 🟡 | `04` (eski) vs `13-reports-sistem-tab.png` |
| 15 | KVKK sekmeleri | Spec’te 6 sekme; UI’da **9** (İmha Kuyruğu/Kayıtları/Hukuki Tutma eklendi — D2c sonrası beklenen olabilir). | 🔵 | `15-kvkk-tabs.png` |
| 16 | KVKK saklama | Seed politika `active=false`; `kvkk:scan-retention --company=demo-firma` → “Toplam aday: 0”. Aktifleştirmeden aday listesi beklenmez (dry-run UI tam süpürülmedi). | 🔵 | — |
| 17 | Genel | 1366×768 taşma, Excel/PDF export, pivot/grafik, çapraz filtre, portal koyu tema kalıcılığı, yasal taban 422 — bu dalgada UI’da tam koşulamadı; QA-2’de tamamlanmalı. | 🔵 | — |

---

### Konsol hataları

Browser otomasyonunda kalıcı `console.error` kancası tam listelenemedi (oturumlar arası storage/clear). Gözlenen UI bozulmaları:

| Sayfa | Gözlem |
|-------|--------|
| `/reports`, `/dashboards`, `/kvkk` (ilk paint) | Merkezde `â€¦` (Suspense encoding) |
| Portal `/leaves` | Boş state vs dolu API — olası React Query / render hatası (kırmızı konsol doğrulanamadı) |
| Diğer gezilen sayfalar (dashboard, workflows, settings/registry, PDKS redirect) | Görünür kırmızı toast yok |

---

### Doğrulanan güvenlik davranışları

| Senaryo | Sonuç |
|---------|-------|
| `personel@demo.test` company `:3002` girişi | Firma paneline kalıcı oturum açılmadı; portal `:3003/login` yönü / portal-only davranış. Company `auth/login` ile personel token alınamadı. |
| `mudur@demo.test` rail | Yönetim / Analitik / Eğitim / İletişim / Oryantasyon gizli; operasyonel modüller görünür (`07-mudur-rail-filtered.png`). |
| `rapor@demo.test` + `GET /api/v1/reports/datasets` | **200**, gövdede salary/maaş/ücret alanı **YOK**. |
| `admin@demo.test` rapor yetkisi | Seeder’a `PermissionSeeder` çağrısı eklendikten sonra `reports.definitions.view=yes`, `admin` rolü 442 izin. |
| Eski URL `/attendance` | `/pdks` yönlendirmesi çalışıyor (`03-pdks-attendance-redirect.png`). |
| KVKK portal aydınlatma modalı | Yayınlanmış “Demo Aydınlatma Metni” personel girişinde çıkıyor (`08`). |
| `kvkk:scan-retention` | Veriye dokunmaz; pasif politikada aday 0 (beklenen). Gerçek imha **yapılmadı**. |
| Production guard | `DemoSeedCommand`: `APP_ENV=production` → exception. |

---

### Kapsam notu (tamamlanamayan UI adımları)

Aşağıdakiler API/kısmi UI ile sınırlı kaldı; **ürün düzeltilmeden** (QA-2) yeniden koşulmalı:

- Rapor builder: 4 alan + filtre + önizleme + kaydet; pivot matris; ölçü formülü; ECharts; Excel/PDF içerik
- `rapor@` ile maaşlı kayıtlı raporda “gizlendi” şeridi (katalog gizleme API’de OK)
- Zamanlanmış rapor UI oluşturma
- Pano düzenleme / global filtre / dilim çapraz filtre / ModuleInsightsBar derin test
- Workflow stüdyoda adım ekleme + UI’dan koşullu kanıt (motor instance üretmediği için bloklandı)
- KVKK: yayın sonrası edit engeli, kimlik doğrulama → paket JSON+PDF, ERTELE gerekçesi
- Ayar: yasal asgari altına 422; izin sayfası ⚙ bağlamsal panel davranış değişimi
- Portal: &lt;1024 alt çubuk, koyu tema refresh kalıcılığı, bordro ekranı

---

### Demo kullanıcılar (dev)

Şifre (hepsi): **`Demo1234!`**

| E-posta | Rol / amaç |
|---------|------------|
| `admin@demo.test` | company_admin (`admin`) — tüm yetkiler |
| `ik@demo.test` | `hr_specialist` — İK / maaş scope |
| `mudur@demo.test` | `manager` — departman scope |
| `personel@demo.test` | `employee` — yalnız portal |
| `rapor@demo.test` | `demo_report_viewer` — rapor var, maaş yok |
| `hr@demo.test` | legacy `hr_manager` |
| `sube-ist@demo.test` / `sube-ank@demo.test` | `branch_manager` |

Firma: **Demo Firma AŞ** (`slug=demo-firma`) — ~40 personel, 32 izin, 300 puantaj, 50 sistem raporu, 9 sistem panosu.

---

### Seeder düzeltmeleri (bu dalgada izinli)

1. Training / KVKK consent CHECK uyumu  
2. Eksik modüller → `ModuleSeeder`  
3. Sistem raporları → `SystemReportPackageSeeder`  
4. Admin’in rapor panosu 403 → `PermissionSeeder` çağrısı (`admin` tüm izinler)

---

## QA-2 — Bulgu düzeltmeleri + bloklanan kontroller

**Tarih:** 2026-07-30  
**Branch:** `faz4-form-engine`  
**Suite:** `609 passed` (tek + 3× ardışık + random seed `1785420488`) · sentinel_ok · tsc 0 · lint 0 · mojibake CI OK (probe kırmızı doğrulandı)

### ADIM 1 — Onay motoru teşhisi (EN BAŞA)

**Sonuç: (b) — Portal yolunda motor hiç çağrılmıyordu**

`PortalLeaveController@store` yalnız `LeaveRequest::create(status=pending)` yapıyordu; `WorkflowService::startWorkflow` yoktu. Company `LeaveRequestController@store` zaten motora bağlıydı. Demo’da aktif koşullu workflow vardı → sorun seed eksikliği (a) veya sessiz catch (c) değildi.

| entity | Motora bağlı? | Giriş |
|--------|---------------|-------|
| leave_request (portal) | **✅ artık evet** | `PortalLeaveController@store` → `WorkflowService::startWorkflow` |
| leave_request (company) | Evet | `LeaveRequestController@store` |
| expense_claims | Evet (submit) | `PortalExpenseController@submit` |
| requests (EmployeeRequest) | **Hayır — DUR** | ayrı dalga |
| job_application | **Hayır — DUR** | ayrı dalga |
| onboarding | **Hayır — DUR** | ayrı dalga |

Bu dalgada yalnız izin bağlandı. Kanıt: `PortalLeaveApprovalWorkflowQa2Test` (instance + kısa skip + uzun koşullu + liste sözleşmesi).

### Bulgu durumu

| # | QA-1 bulgu | Durum | Not |
|---|------------|-------|-----|
| 1 | Portal izin listesi boş | ✅ | `data.data.data` → `extractListData`; ortak portal listeler |
| 2 | Onay instance yok | ✅ | portal → motor; feature test |
| 3 | Bakiye sayı yok | ✅ | `remaining_days` + `leave_type.name` |
| 4 | Form sonrası boş | ✅ | aynı kök (liste unwrap) |
| 5 | Açık Pozisyon 0 | ✅ | KPI `published` → `JobPositionStatus::Active`; `open_positions=3` |
| 6 | Mojibake `â€¦` | ✅ | App.tsx + collectors + `scripts/check-mojibake.mjs` CI |
| 7–14 | Kozmetik/branding/redirect | ✅ | ALATAX HR, DEV-only demo hint, shortLabel, greeting, `/management/workflows` → `/settings/workflows` |
| 15–16 | KVKK 9 sekme / saklama | ✅ karar | MODUL_SPEC 9 sekme; seeder `active=true` — **D2c ihlali → QA-4** (DOK-3) |
| 17 | Bloklanan derin UI | ⏭ kısmi | Liste/motor/KPI/branding doğrulandı; pivot/export/çapraz filtre/KVKK paket derin UI QA-2b’ye |

### Yeni ekranlar
- `19-portal-leaves-fixed.png` — liste + bakiye 12
- Redirect kanıtı: `/management/workflows` → `/settings/workflows`

### Kalan açık
- Talepler / başvuru / onboarding → motor (DUR)
- Rapor builder derin (pivot, ECharts, Excel/PDF içerik), rapor@ gizlendi şeridi UI, pano çapraz filtre, KVKK paket JSON+PDF UI, yasal 422 UI, 1366 taşma tam tarama

---

## QA-2b — Derin UI süpürmesi (rapor / pano / onay / KVKK / ayar / portal)

**Tarih:** 2026-07-31  
**Branch:** `faz4-form-engine`  
**Kapsam:** Teşhis odaklı (🔴/🟠 yapısal bulgular düzeltilmedi; ayrı dalga)  
**Mutlak:** DB silinmedi · gerçek imha yok (yalnız dry-run / aday=0) · suite’e dokunulmadı  
**Ortam notu:** Vite varsayılan `localhost:8000` tarayıcıda `Failed to fetch`; yerel `.env.local` → `VITE_API_URL=http://127.0.0.1:8000/api/v1` (gitignore `*.local`, commit yok)

### Özet sayılar

| Ağırlık | Adet |
|---------|------|
| 🔴 kırık | 3 |
| 🟠 hatalı | 5 |
| 🟡 kozmetik | 4 |
| 🔵 iyileştirme / bloklanan | 6 |
| **Toplam** | **18** |

Ekran görüntüsü: **`21`…`38`** (+ `qa2b-dsr-data.json`) — `docs/qa/e2e/`

---

### Bulgular

| # | Ekran | Bulgu | Ağırlık | Görüntü |
|---|-------|-------|---------|---------|
| 1 | `/dashboards` → sistem panoları (Puantaj #3, İzin #2) | Tüm widget’lar «Widget raporu bulunamadı veya erişim yok»; ham i18n anahtarları (`timesheet.*`, `leaves.*`). Sistem panoları veri göstermiyor. | 🔴 | `28-dashboard-puantaj-widgets-missing.png`, `29-dashboard-izin-widgets.png` |
| 2 | Portal `/profile` | Sayfa sık beyaz ekran (`#root` boş); tema UI buradan test edilemedi. `GET /portal/profile` ≥12 sn (yavaş). | 🔴 | `38-portal-profile-blank.png` |
| 3 | Portal talepler + W1 `employee_request` | Workflow #5 API ile tanımlandı; `request_types` demo’da **0 satır** → `GET /portal/requests/types` boş → talep açılamıyor → `approval_instances` **0** (Stüdyo→gerçek instance kanıtı yok). | 🔴 | — |
| 4 | Rapor builder toolbar | `t('reportEngine.measures')` → *KEY 'REPORTENGİNE.MEASURES (TR)' RETURNED AN OBJECT…* (i18n nesne vs string). | 🟠 | `23-report-builder-preview-filter.png` |
| 5 | Employees dataset | Katalogda custom field yok (`CUSTOM_COUNT=0`); «biri custom field olsun» adımı seçilemedi. | 🟠 | — |
| 6 | Rapor pivot UI | 2D matris (satır=dept × sütun=cinsiyet) UI’da flaky; cinsiyet count (19/21) görüldü. API pivot + alt toplam OK. Drill / detay satırları UI’da tamamlanamadı. | 🟠 | `24-report-pivot-partial.png` |
| 7 | `/reports/schedules` | Alıcı seçimi `window.prompt(user_id)` — UX kırılgan; kayıt API ile schedule #1 oluştu. | 🟠 | — |
| 8 | Portal bordro | Liste var; `has_file=false` → «Bordro dosyası bulunamadı» (PDF açılamıyor). | 🟠 | — |
| 9 | Ayar registry modül grupları | Grup etiketlerinde ham slug (`kvkk`, `leaves`, `reports`). | 🟡 | `32-settings-registry.png` |
| 10 | `rapor@` maaş raporu #52 | Gizlenen sütun şeridi OK; seçili kolon listesinde `gross_salary` / `net_salary` ham key. | 🟡 | `36-rapor-hidden-columns-banner.png` |
| 11 | KVKK 9. sekme screenshot | `31-kvkk-9-tabs.png` kimi oturumlarda siyah/boş kare (CDP metin: 9 sekme doğrulandı). | 🟡 | `31-kvkk-9-tabs.png` |
| 12 | Zamanlama / ölçü UX | Ölçü DSL hata mesajları TR ve net; schedule UI prompt’a bağımlı. | 🟡 | `25` / `26` |
| 13 | Pano düzenleme / çapraz filtre | Widget taşı-boyutlandır-kaydet, global filtre, dilim çapraz filtre UI derinliği bu dalgada tamamlanamadı (widget veri yokluğu blokladı). | 🔵 | `27-dashboards-sistem.png` |
| 14 | Excel / PDF export içerik | FE client-side; API `POST …/reports/51/export` JSON satır döndü (8 IK). Açılmış .xlsx/.pdf dosya içeriği bu oturumda dosya olarak doğrulanamadı. | 🔵 | — |
| 15 | ECharts grafik modu | Builder grafik modu derin UI doğrulanamadı. | 🔵 | — |
| 16 | KVKK saklama dry-run UI | Aktif politika + `kvkk:scan-retention --company=demo-firma` → **aday 0**; ERTELE / dry-run önizleme UI’si adaysız koşulamadı. İmha onaylanmadı. | 🔵 | — |
| 17 | Ayar departman scope badge | Yasal taban 422 OK; «bu departman için özel» göstergesi bu ayarda scope yasak → derin test eksik. İzin davranışı değişimi tam kanıtlanmadı. | 🔵 | — |
| 18 | Ortam | Host `localhost` vs `127.0.0.1` API fetch kırığı — yerel `.env.local` ile aşıldı (ürün default’u tartışmalı). | 🔵 | `21-login-attempt.png` |

---

### Doğrulanan yetenekler

| Yetenek | Sonuç | Not |
|---------|-------|-----|
| Builder: dataset + 4 alan + filtre + önizleme + kaydet | ✅ | Departman=İK → **8 satır**; kayıt **#51** `QA2b Personel IK` (`23`) |
| Custom field seçimi | ❌ | Katalogda yok |
| Pivot matris + alt toplam | ⚠️ | API ✅; UI kısmi (`24`) |
| Drill / detay satırları | ❌ | UI tamamlanamadı |
| Ölçü DSL validate + hatalı formül | ✅ | `sum(olmayan_alan)` net TR; kayıt `qa2b_dept_ratio` (`25`/`26`) |
| ECharts grafik | ❌ | Derin UI yok |
| Excel içerik (açık dosya) | ⚠️ | API export dolu; .xlsx açılmadı |
| PDF Türkçe karakter | ⚠️ | Dosya açılmadı |
| «X sütun gizlendi» şeridi (`rapor@`) | ✅ | `2 sütun… gizlendi: Brüt Maaş, Net Maaş` (`36`) |
| Zamanlanmış rapor | ⚠️ | API schedule #1; UI `prompt` |
| Sistem panoları listeleniyor | ✅ | 9 pano (`27`) |
| Widget veri | ❌ | «rapor bulunamadı» (`28`/`29`) |
| Pano düzenleme kalıcılığı | ❌ | Bloklandı |
| Global / çapraz filtre | ❌ | Bloklandı |
| ModuleInsightsBar (3 modül) | ✅ | `/leaves`, `/recruitment/applications`, `/pdks` — Pano \| Raporlar (`37`) |
| Onay stüdyo (adım/koşul/paralel) | ✅ | Workflow #3 Demo QA Koşullu İzin (`30`) |
| `employee_request` → instance | ❌ | `request_types` boş; instance 0 |
| Onaycı bildirim + durum | ❌ | Önceki adıma bağlı |
| KVKK 9 sekme | ✅ | CDP/UI |
| Yayınlı aydınlatma edit engeli | ✅ | Net TR mesaj |
| Kimlik yokken paket engeli | ✅ | «Kimlik doğrulanmadan ihraç paketi üretilemez.» |
| DSR paket kapsamı (yalnız kişi) | ✅ | ZIP `data.json`+`ozet.html`; yalnız **DEM-020** / Demo Personel; başka DEM/admin yok (`qa2b-dsr-data.json`) |
| Saklama dry-run UI | ⚠️ | Aday 0 — UI yok |
| Yasal taban 422 TR | ✅ | `leaves.retention.months=1` → «Yasal asgari 12.» |
| Portal liste + bakiye | ✅ | Liste dolu; **12 gün** (`34`) — QA-2 doğrulaması |
| Portal ≥1024 rail / &lt;1024 alt çubuk | ✅ | Alt çubuk 5 sekme+QR (`35`) |
| Portal koyu tema kalıcılığı | ⚠️ | `localStorage.theme` + `data-theme` reload’da kalır; Profil UI beyaz ekran |
| 1366×768 yatay taşma | ✅ | Gezilen sayfalarda genel yok |

---

### Konsol hataları (sayfa başına)

| Sayfa | Hata / gözlem |
|-------|----------------|
| Company login (API `localhost:8000`) | `Failed to fetch` / ağ hatası — `.env.local` sonrası düzeldi |
| `/reports` (builder) | i18n: `REPORTENGİNE.MEASURES` object-as-string |
| `/dashboards/:id` (sistem) | Widget yükleme başarısız mesajı (UI); ham key toast/label |
| `/reports` `rapor@` #52 | Şerit OK; seçili kolon ham key (konsol kırmızısı yok) |
| `/settings/workflows/3` | Görünür kırmızı yok |
| `/kvkk` | Görünür kırmızı yok |
| `/settings/registry` | Görünür kırmızı yok |
| `/leaves`, `/pdks`, recruitment | ModuleInsightsBar OK; konsol kritik yok |
| Portal `/dashboard`, `/leaves` | Kritik kırmızı yok; bazı API &gt;3 sn |
| Portal `/profile` | Beyaz ekran; `portal/profile` ~12 s; React ağacı boş |
| Portal bordro | «Bordro dosyası bulunamadı» (iş kuralı / seed dosya eksik) |

---

### &gt;3 sn yükleme (gözlenen)

| Kaynak | Süre (yaklaşık) |
|--------|-----------------|
| `GET /portal/profile` | 5–12 sn |
| `GET /portal/dashboard` + timesheet/privacy | ~4.8 sn |
| `GET /v1/notifications` | 3–9 sn |

---

### Kalan açık maddeler (sonraki dalga)

1. Sistem pano widget ↔ kayıtlı/sistem rapor bağları (🔴)  
2. Portal `/profile` crash + yavaşlık (🔴)  
3. Demo `request_types` seed + W1 `employee_request` uçtan uca instance/onay (🔴)  
4. `reportEngine.measures` i18n nesne hatası (🟠)  
5. Employees custom field → rapor kataloğu (🟠)  
6. Pivot UI 2D + drill + ECharts + Excel/PDF dosya açma (🟠/🔵)  
7. Pano edit / global / çapraz filtre (widget fix sonrası)  
8. Schedule alıcı seçici (prompt yerine)  
9. Bordro PDF seed/dosya  
10. KVKK saklama adayı üretip dry-run + ERTELE gerekçe UI  
11. Ayar departman scope badge + izin ayarı davranış kanıtı  

**İmha:** onaylanmadı. **DB:** silinmedi.

---

## QA-3 — Kritik bulgu düzeltmeleri + seed içerik guard

**Tarih:** 2026-07-31  
**Branch:** `faz4-form-engine`  
**Kapsam:** QA-2b 🔴 düzeltme + yapısal guard (DB silinmedi; imha yok)

### ADIM 1 — Kök neden (EN BAŞA)

**Sonuç: (c) — runtime report resolve**

Sistem panoları `report_id` ile **global şablon** raporlara (`company_id NULL`, `is_system=true`) bağlanıyor. `DashboardService::runWidget` ise:

```php
SavedReport::query()->where('company_id', $companyId)->whereKey($reportId)
```

kullanıyordu. `BelongsToCompany` global scope + firma filtresi şablonları gizledi → her widget «Widget raporu bulunamadı veya erişim yok».

D1d/D1g yeşildi çünkü testler **firma-içi** `report_id` / inline config kullanıyordu; sistem paket batch run’ı yoktu.

İkincil: bazı dataset join’leri (`trainings`→`training_sessions`, `personDistinctColumn`→`survey_submissions`) eksik bağlanıyordu → guard kırmızıya düşürdü; `ReportQueryBuilder::applyJoins` bağımlılık + topo sıra ile düzeltildi.

**(a)/(b)/(d) değil:** seeder `report_id` şeması doğru; demo verisi (izin/puantaj) mevcut; sorun resolve + join runtime.

### ADIM 3 — Portal `/profile` JS hatası

**Stack / semptom:** React `#root` boş; konsol tipi: *Objects are not valid as a React child (found: object with keys {id, name, …})*.

**Kök:** `PortalProfileController` `EmployeeResource` döndürüyordu → `employee.department` **nesne**. `ProfilePage` bunu JSX’te doğrudan yazıyordu.

**Başka sayfa?** Portal’da aynı pattern yok (yalnız `ProfilePage`). Rota smoke + API smoke eklendi.

### Yapısal guard’lar

| Guard | Dosya |
|-------|--------|
| Sistem rapor dataset/alan + run + pano batch | `SystemReportPackageContentGuardTest` |
| Sistem pano → sistem report_id regresyon | `DashboardV2Test::test_system_dashboard_widgets_resolve_system_report_ids` |
| Join bağımlılığı | `ReportQueryJoinDependencyTest` |
| Portal profil sözleşmesi (department string) | `PortalProfileContractTest` |
| Portal API smoke | `PortalRouteApiSmokeTest` |
| Portal rota dosya/App.tsx smoke (CI) | `scripts/portal-route-smoke.mjs` + `portalProtectedRoutes.ts` |

### Doğrulama (tarayıcı / API)

| Kanıt | Sonuç |
|-------|--------|
| İzin Panosu widget veri | ✅ (`40-izin-pano-widgets-data.png`) — örn. pending **11** |
| Puantaj Panosu | ✅ (`41`) — status **25**, hours **517** |
| Portal profil | ✅ (`39`) — «Satış Temsilcisi — Satış», beyaz ekran yok |
| Demo request_types + instance | ✅ 3 tip; 3 `approval_instances` `EmployeeRequest` in_progress (`42`) |

### Kalan (bilinçli)

- Pano düzenleme / global / çapraz filtre derin UI (kısmi — widget artık dolu; ayrı polish)
- Widget başlıkları seed’de rapor adına çekildi (yeniden seed sonrası)
- QA-2b 🟠 i18n `reportEngine.measures`, custom field katalog, Excel/PDF dosya açma, bordro PDF

### Test / CI

- Tek koşu: **633 passed** (2813 assertions) — `alatax_hr_testing`
- 3× ardışık + random seed `1785420999` + sentinel + portal-route-smoke + 3 SPA tsc/lint → Actions

---

## DOK-3 → QA-4 (belge teşhisi; kod yok)

| # | Bulgu | QA-4 |
|---|--------|------|
| 1.1 | `DemoSeeder` + `DemoDataSeeder` ikisi `admin@demo.test` / `demo-firma` | Tek kaynak veya net öncelik |
| 1.2 | Retention seed `active=true` (D2c ihlali) | Seed’de `active=false` (dry-run ayrı) |

Detay: `GUNCEL_DURUM_RAPORU.md` § DOK-3.

---

## Açık görsel / manuel borçlar (tek liste — DOK-3)

QA-1 / 2 / 2b / 3’te doğrulananlar ilgili raporlarda **✅ QA’da doğrulandı** işaretlendi. Aşağıdakiler hâlâ açık:

| # | Madde | Kaynak |
|---|--------|--------|
| 1 | Rapor builder: pivot / ECharts derin UI, çapraz + global filtre polish | QA-2b |
| 2 | Excel/PDF export — dosya içeriği açılıp doğrulanmadı | QA-2b 🟠 |
| 3 | i18n `reportEngine.measures` + custom field katalog etiketleri | QA-2b 🟠 |
| 4 | Bordro PDF görsel / içerik | QA-2b |
| 5 | 1366×768 tam taşma tarama | QA-1 #17 |
| 6 | Yasal taban 422 UI mesajı | QA-1 |
| 7 | KVKK paket JSON+PDF derin UI | QA-2 |
| 8 | Pano düzenleme UI polish (widget veri ✅ QA-3) | QA-3 kalan |
| 9 | Bundle &lt;1MB | FE borç |
| 10 | Onaylı izin → puantaj “izinli gün” (wire yok) | DOK-3 §1.3 / Faz 6 |

Manuel smoke: `TEST_TURU.md`. Otomatik tur kanıtı: bu dosya + `demo:seed`.
