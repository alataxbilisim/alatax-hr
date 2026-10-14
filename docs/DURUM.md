# Dobedan HR — Güncel Durum (tek kaynak)

**Son güncelleme:** 2026-10-14 (Adım 03A) · **Çalışma dalı:** `altyapi` · **Geri dönüş noktası:** tag `alatax-son`

Bu dosya projenin **tek durum belgesidir**. Eski durum/yol haritası belgeleri (`GUNCEL_DURUM_RAPORU`, `ROADMAP`, `BURADAN_BASLA`, faz raporları) `arsiv/2026-10-eski-ad-donemi/` altındadır ve yalnızca tarihçe içindir. Kaynaklar: eski `GUNCEL_DURUM_RAPORU.md` + `ROADMAP.md` ve iki bağımsız denetim (`denetim/2026-10-13/`).

> **Okuma notu:** Hiçbir modül ve hiçbir sayfa "bitti" değildir. Aşağıdaki tablolar abartısız gerçek durumu gösterir. Eski belgelerdeki "✅ KAPANDI" işaretleri faz kapsamının *çekirdeği* içindir; ürün kalitesinde bitmiş anlamına gelmez.

---

## 1. Ürün çerçevesi

| Madde | Değer |
|---|---|
| Ürün | **Dobedan HR** — Türkiye odaklı, kurumsal, çok kiracılı (multi-tenant) B2B İK SaaS. Kod, paket, Docker ve veritabanı adları Adım 02'de Dobedan'a çevrildi (`dobedan-hr`, `dobedan_hr`, `@dobedan/*`). |
| Dağıtım | Cloud SaaS + on-prem; tek kod tabanı (`APP_MODE`) |
| Ölçek hedefi | 10 kişilik tek şubeden on binlerce personelli, yüzlerce şubeli holdinglere. Tasarım sorusu: *40 modül, 50.000 personel, 500 şirkette çalışır mı?* |
| Modül sayısı | Bugün 14 operasyonel modül + 7 çekirdek platform modülü; hedef çok daha fazla |
| Lisans | Her modül ayrı satılabilir (à la carte; `modules` + `company_modules`) |
| Bordro | Dış sistem; çıktı = aktarım paketi (`spec/ENTEGRASYON_SPEC.md`) |
| Pilot | Dobedan ve benzeri kurulumlar doğrulama ortamıdır; ürün gereksinimini tek başına belirlemez |

**Teknik sabitler:** Laravel 12 / PHP 8.2 (`/api/v1`), PostgreSQL 16, Redis, Docker Compose (6 servis: app, queue, scheduler, nginx, postgres, redis). Frontend pnpm monorepo: React 19 + TypeScript, 3 uygulama (superadmin, company, portal) + `@dobedan/shared`.

**Sayılarla (2026-10-13):** 666 API rotası · 155 tablo · 116 model · 114 controller · 117 test dosyası / 771+ test · ön yüzde 159 rota satırı (123 TAMAM, 7 KISMİ, 1 İSKELET, 2 BOZUK, 26 yönlendirme — denetim tanımına göre "API çağrısı kırık değil" anlamında TAMAM).

---

## 2. Modüller — gerçek durum

Durum ölçeği: **Tam** (spec kapsamı + test eksiksiz) · **Kısmi** (çalışan çekirdek var, spec'in önemli kısmı eksik) · **İskelet** (tablo/rota/sayfa var, iş mantığı çok az) · **Yok**. Kapsam: `spec/MODUL_SPEC.md`.

### 2a. Çekirdek platform (A)

| # | Modül | Backend | Frontend | Test | Not |
|---|---|---|---|---|---|
| A1 | Kullanıcı & Rol | Kısmi | Kısmi | Kısmi | Spatie RBAC çalışıyor. 2FA portal/admin atlatma açığı Adım 03A'da kapandı (mimari testle korunuyor). Şirkete özel rol yok. |
| A2 | Self-servis Portal | Kısmi | Kısmi | Kısmi | 20 sayfa. `private` disk Adım 03A'da tanımlandı; belge/bordro indirme gerçek diskle test ediliyor. |
| A3 | Duyurular | Kısmi | Kısmi | Yok | Adı geçen test dosyası yok. |
| A4 | Talep & Vaka | Kısmi | Kısmi | Kısmi | |
| A5 | Bildirim Merkezi | Kısmi | Kısmi | İskelet | In-app + şablon; push yok; `approval.returned` yok. |
| A6 | Audit & Log | Kısmi | Kısmi | İskelet | `Auditable` 28/116 modelde. |
| A7 | KVKK | Kısmi | İskelet | Kısmi | 15 PersonalDataCollector; saklama job'ları eksik. `private` disk Adım 03A'da tanımlandı. |

### 2b. Operasyonel modüller (B, 14)

| # | Modül | Backend | Frontend | Test | Not |
|---|---|---|---|---|---|
| B1 | Organizasyon | Kısmi | Kısmi | Kısmi | Şube/departman/pozisyon/organizasyon şeması; holding (organizations) çekirdeği. |
| B2 | Personel / Özlük | Kısmi | Kısmi | Kısmi | CRUD, özel alan, Form Engine. Belgeler sekmesi `private` disk sorunu Adım 03A'da giderildi. İstatistik/rapor SQL'i Adım 01'de PostgreSQL'e çevrildi; pano KPI (`age`/`tenure`) ve filtre beyaz listesi Adım 03A'da düzeltildi. |
| B3 | İzin | Kısmi | Kısmi | Kısmi | Talep/tür/bakiye/onay. Onaylı izin → puantaj bağlantısı yok; iade (`returned`) akışı yok. |
| B4 | PDKS | Kısmi | Kısmi | Kısmi | Kayıt + QR/kiosk. Puantaj kartı, onay zinciri, vardiya/rotasyon, yasal kontroller büyük ölçüde yok. |
| B5 | Ücret & Ödemeler | Kısmi | Kısmi | Kısmi | Ücret bandı/dönem, masraf. Bordro yükle/indir `private` disk sorunu Adım 03A'da giderildi. |
| B6 | İşe Alım | Kısmi | Kısmi | İskelet | `reports/trends` SQL'i Adım 01'de düzeltildi. |
| B7 | Oryantasyon & Çıkış | Kısmi | Kısmi | Kısmi | Şablon kopyalama ve görev atlama uçları Adım 03A'da eklendi. |
| B8 | Performans | Kısmi | Kısmi | İskelet | |
| B9 | Eğitim (LMS) | İskelet | İskelet | İskelet | LMS kapsamı yok. |
| B10 | İSG | Yok | Yok | Yok | |
| B11 | Varlık / Zimmet | Kısmi | Kısmi | Yok | |
| B12 | Anket & eNPS | İskelet | Kısmi | İskelet | |
| B13 | Doküman+ | Kısmi | Kısmi | İskelet | Rapor SQL'i Adım 01'de düzeltildi. Doküman dosyaları hâlâ `public` diskte (§6 borç 10). |
| B14 | Analitik | Kısmi | Kısmi | Kısmi | Rapor motoru (11 dataset) + pano. Eski modül rapor controller'ları ayrı ve kural dışı ham SQL içeriyor. |

---

## 3. Platform motorları ve registry'ler

| Motor | Olgunluk | Not |
|---|---|---|
| Lookup (liste değerleri) | Kısmi | Çalışıyor; cascading picklist yok. |
| Form Engine | İskelet → Kısmi | 3 entity (personel, izin, masraf) Form Engine sayfasıyla; tam geçiş ve alan izni bağlama yok. |
| Workflow / onay | Kısmi | Motor + editör var; iade (`returned`) yok; genel tetikleyici/koşul/aksiyon motoru değil. |
| Bildirim | Kısmi | In-app + şablon; e-posta uçtan uca doğrulanmadı; push yok. |
| Rapor | Kısmi | `ReportQueryBuilder` + `ReportPivotService`, 11 dataset, grup kapsamı. |
| Ayar (Settings Registry) | İskelet → Kısmi | Pilot: izin + rapor; modüllerin çoğu kayıtlı değil. |
| Dataset registry | Kısmi | 11 dataset. |
| PersonalDataCollector registry | Kısmi | 15 collector. |
| Approval entity registry | Kısmi | |
| Yardım içeriği (`docs/help/`) | Yok | Klasör hiç yok; `.cursorrules` DoD'u karşılanmıyor. |

---

## 4. Kilitli mimari kararlar

1. **Modüler monolith**, tek Laravel uygulaması; mikroservis yok.
2. **Veritabanı yalnızca PostgreSQL 16. MySQL tamamen kaldırıldı** (Adım 01: compose servisi, `pdo_mysql`, config bağlantıları, env anahtarları, MySQL'e özgü SQL). MariaDB ve SQLite da yasak; `DatabaseDriverGuardTest` bunu korur.
3. **API-first** `/api/v1`; tüm SPA'lar ve ileride mobil aynı API'yi kullanır. Yanıt formatı `ApiResponse`.
4. **Tenancy:** `company_id` + `BelongsToCompany` global scope; panel bağlamı `CompanyContext`; portal bağlamı personelin kendi şirketi. `company_id` istemciden alınmaz.
5. **Holding:** SEÇENEK 1 — `organizations` + DataScope `group`; operasyonel ekranlar tek aktif şirket, rapor/pano grup kapsamı.
6. **RBAC:** Spatie Permission, `{module}.{page}.{action}`; her rota izinle korunur.
7. **Enum:** string kolon + PHP backed enum + CHECK constraint (`PortableEnum`). Esnek veri `jsonb`.
8. **Lisans:** modül bazlı à la carte; SuperAdmin'den aç/kapa.
9. **Cloud + on-prem tek kod** (`APP_MODE`).
10. **Personel adı** tek kaynak `employees.full_name`.
11. **Frontend standardı:** TanStack Query, react-hook-form + zod, `theme.css` token'ları, `t()` ile i18n. *(Standart karar; uygulamada henüz yaygın değil — §6.)*
12. **Bordro** dış sistemde; ürün aktarım paketi üretir.

---

## 5. Bilinen kritik hatalar

Numaralar Adım 03A görev dosyasındaki K1–K8'dir. Her biri ayrı commit ve regresyon testiyle kapandı (ayrıntı: `cursor-gorevleri/raporlar/ADIM_03A_RAPOR.md`).

| # | Hata | Durum |
|---|---|---|
| K1 | 2FA challenge token portal ve süper admin gruplarında kabul ediliyordu | **Adım 03A'da kapandı** — tüm `auth:sanctum` route'larda `deny.2fa.challenge`; tek istisna `auth/2fa/verify` (`TwoFactorRouteGuardTest`) |
| K2 | `private` disk tanımsız → belge/bordro yükle-indir 500 | **Adım 03A'da kapandı** — disk tanımlı (`PRIVATE_DISK_DRIVER` ile değiştirilebilir); gerçek diskle akış testi + disk adı mimari testi |
| K3 | Pano filtresinde kolon adı enjeksiyonu / hassas alan sızıntısı | **Adım 03A'da kapandı** — dataset tabanlı beyaz liste + alan izni (bilinmeyen alan 422, yetkisiz hassas alan 403); 5 controller'da sıralama beyaz listesi |
| K4 | Pano KPI `age`/`tenure` olmayan kolonlarda ortalama | **Adım 03A'da kapandı** — PostgreSQL `age()` ifadesi |
| K5 | 2 kırık onboarding ucu (duplicate/skip) | **Adım 03A'da kapandı** — uçlar eklendi; 13 ölü `api.ts` metodu silindi |
| K6 | İşten çıkmış personel "Unknown" adıyla | **Adım 03A'da kapandı** — kök neden boş `full_name`; idempotent backfill (54 satır), `display_name` geri dönüşü |
| K7 | Betiklerde sabit veritabanı şifresi | **Adım 03A'da kapandı** — env'den okunuyor |
| K8 | `public/storage` symlink'i yok | **Adım 03A'da kapandı** — entrypoint'te idempotent `storage:link` |

Önceki listeden: MySQL'e özgü SQL Adım 01'de, test DB otomasyonu Adım 03A'da (B1) kapandı. **Açık:** bağımlılık açıkları — composer 46 advisory / 15 paket (14 high), pnpm 137 (2 critical `jspdf`) → Adım 04.

### 5a. Test ve kalite durumu (Adım 03A sonu)

| Ölçüt | Değer |
|---|---|
| Backend testleri | **968 test, 4143 assertion**; deprecation 0 (Adım 02: 785) |
| Süre | paralel (8 süreç) **6:27**; seri 23:33 (adım başında 837 test seri 21:44) |
| Kapsam taban çizgisi (`app/`) | satır **%58,32**, metot %38,44, sınıf %14,25 |
| Factory | 116/116 model (istisna yok) |
| PHPStan (Larastan seviye 5) | baseline **891 hata** (617 kayıt, 180 dosya); büyümez |
| Pint | `pint --test` temiz |

Komutlar ve kurallar: `operasyon/TEST.md`.

---

## 6. Teknik borç (tek liste)

| # | Borç | Sahip / adım |
|---|---|---|
| 1 | ~~Ürün adının Dobedan'a çevrilmesi (kod, paket adları, UI, Docker, DB)~~ | **Adım 02'de tamamlandı** |
| 2 | ~~Kritik hatalar K1–K8~~ (bağımlılık açıkları hariç → Adım 04) | **Adım 03A'da tamamlandı** |
| 3 | ~~Test altyapısı: test DB otomasyonu, paralel suite, kapsam, factory, mimari testler~~ | **Adım 03A'da tamamlandı**; CI'a paralel/kapsam/PHPStan eklenmesi Adım 03B |
| 4 | Telescope varsayılan açık + prune yok; Sanctum token süresiz | Atanmadı |
| 5 | Herkese açık `auth/register` (self-signup) — ürün kararı gerekli | Atanmadı (karar) |
| 6 | Stored XSS riski: `TextNoteWidget` HTML basıyor | Atanmadı |
| 7 | localStorage'da kişisel veri (user/employee nesnesi) | Atanmadı |
| 8 | Admin şifre sıfırlamada düz şifre e-postası | Atanmadı |
| 9 | Hata yanıtlarında istisna mesajı istemciye dönüyor (22 controller) | Atanmadı |
| 10 | Hassas yüklemeler `public` diskte (CV, doküman, izin belgesi, masraf fişi, talep eki). Adım 03A'da `storage:link` kurulduğundan bu dosyalar artık web'den doğrudan erişilebilir — **KVKK riski, öncelikli** | Atanmadı (öneri: Adım 04) |
| 11 | FormRequest: 242 inline validate / 16 FormRequest; API Resource 1 | Atanmadı |
| 12 | Kural dışı ham SQL (~73 yer, rapor motoru dışı) | Atanmadı |
| 13 | Frontend: React Query 0, react-hook-form 0; hardcode renk/inline style; hardcoded `localhost` URL'leri | Atanmadı |
| 14 | 311 indekssiz FK; 20 ölü tablo (16'sı `company_id`'li ve modelsiz — `TenantModelGuardTest::TABLES_WITHOUT_MODEL`); kullanılmayan `Timesheet` modeli (Adım 03A'da `BelongsToCompany`'ye alındı) | Atanmadı |
| 15 | Hatalı FE API metodları (13 ölü metot Adım 03A'da silindi; kalanlar yeniden sayılmalı); 104 kullanılmayan backend ucu | Atanmadı |
| 16 | Büyük dosyalar (BE 12, FE 36 dosya 500+ satır); company bundle 2,17 MB | Atanmadı |
| 17 | Onaylı izin → puantaj bağlantısı; iade (`returned`) akışı | Atanmadı (modül derinleştirme) |
| 18 | Form Engine tam geçiş, cascading picklist, Settings Registry yayılımı | Atanmadı |
| 19 | Yardım içeriği `docs/help/` hiç yok | Atanmadı |
| 20 | `storage/logs/laravel.log` rotasyonsuz (235 MB); app/queue/scheduler healthcheck yok (`storage:link` Adım 03A'da çözüldü) | Atanmadı |
| 21 | `main` dalı çok geride; üretim dalı / merge stratejisi belirsiz | Atanmadı (karar) |
| 22 | E-posta doğrulama uçtan uca (Mailtrap) | Atanmadı |
| 23 | i18n EN + dil seçici | Backlog |
| 24 | Şirkete özel rol (`company_user.role_id`) | Backlog |
| 25 | ~~`EmployeeDashboardController::getKPIData` `age`/`tenure` AVG~~ | **Adım 03A'da tamamlandı (K4)** |
| 26 | ~~`EmployeeDashboardController::getWidgetData` filtre kolon adları whitelist'siz~~ | **Adım 03A'da tamamlandı (K3)** |
| 27 | PHPStan (Larastan seviye 5) baseline: **891 hata** (617 kayıt, 180 dosya). Baseline büyümez; düzeltildikçe küçültülür | Sürekli |
| 28 | 33 yerde `$this->error('...', null, NNN)` çağrısı: imza `error(string, int, $errors)` olduğundan TypeError → kullanıcıya 400/403/404 yerine **500** (Portal Duyuru/İzin/Performans/Profil/Eğitim/Pano, `LeaveRequestController`, `CompanyLedgerController`, `LicensePackageController`) | Atanmadı (öneri: Adım 04) |
| 29 | `POST approvals/delegations`: `delegate_id` yalnız `exists:users,id`; vekilin aynı şirkette olduğu doğrulanmıyor (tenant) | Atanmadı |
| 30 | `EmployeeDashboardController` yanıtları `ApiResponse` yerine `response()->json` | Atanmadı |
| 31 | App container yeniden oluşturulunca nginx eski upstream IP'sini tutup 502 dönüyor (`docker compose restart nginx` gerekiyor) | Atanmadı |
| 32 | Testler `storage/app/private` altında KVKK export/yanıt dosyası bırakıyor (341 dosya birikmiş) | Atanmadı |
| 33 | Demo verisinde kullanıcısız 6 eski personelin (DEM-035…040) `full_name`'i boş; listede personel kodu görünüyor. `DemoDataSeeder` düzeltildi, canlı veriye yeniden seed kararı bekliyor | Karar |
| 34 | CI yalnız `pint --test` + seri suite koşuyor; paralel, kapsam ve `composer analyse` yok | Adım 03B |

`GEÇİCİ` işaretli çözümler bu listeye eklenir.
