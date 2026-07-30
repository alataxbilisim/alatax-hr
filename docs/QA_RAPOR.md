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
| 15–16 | KVKK 9 sekme / saklama | ✅ karar | MODUL_SPEC 9 sekme; seeder politika `active=true` (aday 0 = süre penceresi) |
| 17 | Bloklanan derin UI | ⏭ kısmi | Liste/motor/KPI/branding doğrulandı; pivot/export/çapraz filtre/KVKK paket derin UI QA-2b’ye |

### Yeni ekranlar
- `19-portal-leaves-fixed.png` — liste + bakiye 12
- Redirect kanıtı: `/management/workflows` → `/settings/workflows`

### Kalan açık
- Talepler / başvuru / onboarding → motor (DUR)
- Rapor builder derin (pivot, ECharts, Excel/PDF içerik), rapor@ gizlendi şeridi UI, pano çapraz filtre, KVKK paket JSON+PDF UI, yasal 422 UI, 1366 taşma tam tarama
