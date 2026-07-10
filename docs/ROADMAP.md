# ALATAX HR — ROADMAP v1.0

**Tarih:** 10 Temmuz 2026
**Girdiler:** PROJECT_SNAPSHOT.md (10 Tem 2026) + global HR SaaS pazar/mimari araştırması + proje sahibi hedefleri
**Bağlam:** Solo geliştirici (Cursor + AI destekli), önce Türkiye pazarı, mobil uygulama sonraki fazda, bordro modülü kapsam dışı (ileride).

---

## 1. Vizyon

Türkiye pazarına odaklı, hem cloud (multi-tenant SaaS) hem on-premise çalışabilen, **modül modül satılan**, Zoho seviyesinde özelleştirilebilir, PowerBI mantığında self-servis raporlama sunan, uçtan uca denetlenebilir (audit) bir HR platformu.

Fark yaratacak 4 şey:
1. **Özelleştirme Stüdyosu** — her firmanın form, alan, liste, iş akışı ve bildirimleri kod yazmadan kendine göre şekillendirmesi
2. **Rapor Motoru** — hazır raporların ötesinde, sürükle-bırak self-servis rapor/dashboard oluşturma
3. **Detaylı RBAC + Audit** — modül → sayfa → aksiyon → alan seviyesinde yetki, her işlemin izlenebilirliği
4. **Cloud + On-Prem tek kod tabanı** — Türkiye kurumsal pazarının "veri bende dursun" talebine cevap

---

## 2. Yol Gösterici İlkeler

1. **Önce platform, sonra modül.** Form motoru, yetki, audit, workflow, bildirim ve rapor motorları önce bitirilir; modüller bu motorların üstüne oturtulur. Solo geliştirici için en büyük kaldıraç budur — aynı işi 15 modülde 15 kez yazmak yerine 1 kez motora yazılır.
2. **Modüler monolith.** Mikroservis yok. Tek Laravel uygulaması, net modül sınırları, tek deployment. On-prem kurulumu ve solo bakımı ancak bu basitlikte sürdürülebilir.
3. **API-first.** Company/Portal/SuperAdmin SPA'larının kullandığı API, ileride mobil uygulamanın kullanacağı API'nin ta kendisidir. UI'a özel gizli endpoint yazılmaz.
4. **Tek veritabanı motoru: PostgreSQL.** MySQL/SQLite desteği taşınmaz. JSONB + GIN index, custom field ve audit mimarisinin temelidir.
5. **Kod cloud/on-prem ayrımı bilmez.** Fark yalnızca konfigürasyon (`APP_MODE`) ve lisans katmanındadır.
6. **Derinlik > genişlik.** Pilot için seçilen çekirdek modüller "satılabilir" kaliteye getirilir; kalanlar lisans sisteminde kapalı tutulur. Yarım 15 modül yerine tam 7 modül.
7. **Türkçe-first, i18n-ready.** Arayüz Türkçe; ancak bugünden itibaren yazılan her yeni metin çeviri altyapısından (t()) geçer. Toplu string migrasyonu global açılım öncesine ertelenir.
8. **Her faz test ve dokümantasyonla kapanır.** DoD (Definition of Done) karşılanmadan sonraki faza geçilmez.

---

## 3. Mevcut Durum → Hedef Özeti

| Alan | Bugün (Snapshot) | Hedef |
|------|------------------|-------|
| Veritabanı | SQLite fiilen / MySQL dokümanda; 3 migration çakışması | PostgreSQL 16+, temiz baseline migration seti, JSONB |
| Yetki | Spatie seed'li ama route'ta enforce YOK; type enum'u ile kaba ayrım | Route + Policy enforcement; modül/sayfa/aksiyon/alan + veri kapsamı |
| Audit | activity_logs (controller bazlı, kısmi) | Observer tabanlı otomatik audit; okuma/export logları; immutable |
| Özelleştirme | custom_field_definitions + renderer (tohum) | Form Engine: layout, koşullu görünürlük, alan izinleri, liste görünümleri |
| Workflow | approval_workflows çalışıyor; bildirim TODO | Genel amaçlı motor: tetikleyici + koşul + aksiyon; bildirim entegre |
| Bildirim | In-app var; e-posta/SMS/push kısmi, otomasyon yok | Bildirim Merkezi: şablonlar, kanallar, kullanıcı tercihleri |
| Raporlama | Modül bazlı sabit raporlar + Excel/PDF export | Semantic layer + sürükle-bırak rapor builder + dashboard v2 |
| KVKK | Yok | Rıza, veri ihracı, silme/anonimleştirme, saklama politikaları |
| Deployment | Manuel, Docker yok, CI yok | Docker Compose, CI, on-prem installer, imzalı lisans |
| UI | Desktop odaklı, ekranlar büyük | Kompakt token ölçeği, 1366×768 hedefi, density modu |
| i18n | Yok (TR hardcoded) | Altyapı kurulu, yeni kod t() zorunlu, toplu migrasyon backlog |
| Test | Route testleri ağırlıklı | Her endpoint için auth+permission+happy path feature testi |

---

## 4. Hedef Mimari (Özet)

- **Backend:** Laravel 12 modüler monolith. Katmanlar: Controller (ince) → FormRequest → Service → Model. `BelongsToCompany` global scope korunur ve tüm tenant modellerinde zorunludur.
- **DB:** PostgreSQL 16+. Hibrit model: çekirdek alanlar tipli kolon, esnek alanlar JSONB (`custom_fields`, `settings`, `old_values/new_values`, `widgets`). Tüm tenant tablolarında `(company_id, ...)` bileşik indeksler; sık sorgulanan JSONB alanlarına GIN index. (İleri sertleştirme: Postgres Row-Level Security — backlog.)
- **Cache/Queue:** Redis (cloud ve on-prem'de aynı). Queue worker + Laravel Scheduler her ortamda çalışır.
- **Frontend:** Mevcut 3 SPA + shared paket korunur. Server state için TanStack Query standardı (Portal'da zaten var → Company/SuperAdmin'e yayılır); Redux yalnızca auth/theme/ui. Yeni formlar react-hook-form + zod (bağımlılık zaten mevcut) → Form Engine hazır olunca onun üzerinden.
- **Dağıtım modları:**
  - `APP_MODE=cloud` → multi-tenant, SuperAdmin paneli aktif, merkezi lisanslama
  - `APP_MODE=standalone` → on-prem tek firma; SuperAdmin gizlenir, lisans dosyasından modül/limit okunur
- **Rapor motoru:** Kod tarafında tanımlı dataset registry (semantic layer) → whitelist tabanlı güvenli query builder → JSONB rapor tanımları → Nivo/tablolarla render. Kullanıcı asla ham SQL yazmaz/görmez.

---

## 5. FAZ PLANI

> Süreler solo + AI destekli, tam zamanlı çalışma varsayımıyla verilmiştir; kalite kapısıdır, takvim baskısı değildir.

---

### FAZ 0 — Stabilizasyon ve Temeller (1–2 hafta)

**Amaç:** Kırıkları kapatmak, geliştirme altyapısını kurmak. Bu faz bitmeden hiçbir yeni özellik yazılmaz.

- [ ] Migration çakışmalarını çöz: `document_categories` çift create, `announcement_reads` çift şema, `request_types` çift set → etkin şemalar netleştirilip ölü dosyalar temizlenir (kalıcı çözüm Faz 1 squash'ında)
- [ ] `DatabaseSeeder`'a `LicensePackageSeeder` ve `LeaveTypeSeeder` eklenir; temiz kurulum tek komutla ayağa kalkar
- [ ] Frontend bug turu: Portal `state.auth.loading` → `isLoading`; `notificationSlice` store'a kayıt; `ADMIN_ROUTES.LICENSE_PACKAGES` düzeltmesi; boş `components/index.ts`
- [ ] 6 kayıp route kararı: `/onboarding/templates`, `/performance/periods`, `/performance/criteria`, `/training/sessions`, `/assets/categories`, `/assets/assignments` → sayfası yazılacaklar router'a eklenir, yazılmayacaklar sidebar'dan kaldırılır
- [x] Forgot-password / reset-password sayfaları (3 SPA'da login akışına bağlı; API zaten hazır)
- [x] Davet e-postası + şifre sıfırlama bildirimi Mailable'ları (EmployeeController:320/450, UserController:412 TODO'ları)
- [ ] Docker Compose (dev): nginx + php-fpm + postgres + redis + worker + scheduler + 3 vite dev server — on-prem paketinin de temeli
- [ ] CI (GitHub Actions): Pint + ESLint + PHPUnit her push'ta
- [x] CORS temizliği (hardcoded IP kaldırılır, env'den okunur); throttle 500/dk → endpoint grubu bazlı makul limitler (auth: 10/dk, genel: 120/dk, export: 20/dk, public: 20/dk)
- [x] i18n altyapısı: react-i18next + Laravel lang dosyaları kurulur; **kural:** bu commit'ten sonra yazılan her yeni UI metni t() ile
  - Detay: `docs/I18N.md` · TODO(i18n) forgot/reset sayfaları `t()` ile taşındı; diğer eski string'ler backlog
- [ ] Kullanılmayan bağımlılık kararı: react-hook-form + zod **benimsenir** (Form Engine'in temeli olacak), @hookform/resolvers kalır; kullanılmayanlar temizlenir
- [ ] `_archive_old_app/` repo'dan çıkarılır (ayrı branch/arşiv)

**Açık test borçları**
- [ ] **MAIL TESTİ (yapılacak):** Mailtrap SMTP bağla → forgot-password/reset + davet e-postalarını uçtan uca doğrula. Adımlar: (a) Mailtrap değerlerini `.env`'e gir, `MAIL_MAILER=smtp`; (b) `FRONTEND_URL_*` portları doğrula; (c) queue worker çalışsın; (d) `config:clear`; (e) login→şifremi unuttum→Mailtrap inbox'ta Türkçe mail + reset linki testi. Kod hazır, sadece SMTP bağlama + doğrulama kaldı.

**DoD:** Sıfır makinede `docker compose up` + seed ile sistem tam çalışır; CI yeşil; login→şifre sıfırlama→davet uçtan uca çalışır.

---

### FAZ 1 — PostgreSQL Geçişi (1–2 hafta)

**Amaç:** Tek ve doğru veritabanı motoruna geçiş + temiz şema baseline'ı.

- [ ] **Karar noktası:** SQLite'taki 118 MB veri test verisi ise atılır (varsayım: evet). Korunacak veri varsa pgloader ile tek seferlik taşıma.
- [ ] 67 migration → **modül bazlı temiz baseline setine squash** (örn. `0001_core`, `0002_hr`, `0003_leave`...). Çakışmalar bu sırada kalıcı çözülür. Kural: bundan sonra baseline'a dokunulmaz, her değişiklik yeni migration.
- [ ] MySQL/SQLite'a özgü ifadeler ayıklanır; enum kolonlar → string + CHECK constraint (Laravel cast ile PHP enum)
- [ ] JSONB dönüşümü: `custom_fields`, `settings`, `preferences`, `old_values/new_values`, `widgets`, `approval_flow`, `form_fields` vb. tüm JSON kolonları
- [ ] İndeks stratejisi: her tenant tablosunda `(company_id)` + sık sorgu kombinasyonlarına bileşik indeks; `employees.custom_fields` gibi filtrelenecek JSONB'lere GIN
- [ ] `config/database.php` + `.env.example` yalnızca pgsql; README kurulum güncellenir
- [ ] Tüm feature testleri PostgreSQL üzerinde koşacak şekilde CI güncellenir
- [ ] pg_dump tabanlı yedekleme script'i (on-prem'in yedekleme aracının temeli)

**DoD:** `migrate:fresh --seed` PostgreSQL'de hatasız; tüm testler pgsql'de yeşil; SQLite/MySQL referansı repoda kalmaz.

---

### FAZ 2 — Güvenlik ve Yetki Çekirdeği: RBAC v2 + Audit v2 (3–4 hafta)

**Amaç:** "Detaylı rol tabanlı + her şey loglanır" vaadini gerçeğe çevirmek. Platformun güven katmanı.

**RBAC v2**
- [ ] Mevcut `{module}.{page}.{action}` izin formatı korunur; **tüm route'lara** `permission:` middleware'i uygulanır (Spatie alias'ları zaten kayıtlı). RouteComprehensiveTest izin matrisiyle güncellenir → hangi rolün hangi endpoint'e eriştiği test garantisinde
- [ ] Model bazlı Laravel Policy'ler (Employee, LeaveRequest, Document...) — kayıt seviyesi kontroller (örn. yalnızca kendi departmanının iznini onaylama)
- [ ] **Veri kapsamı (data scope):** rol başına `own / team / department / branch / company` kapsamı; `DataScope` servisi + query macro ile BelongsToCompany'ye eklemlenir
- [ ] **Alan seviyesi izin temeli:** `field_permissions` (role_id, entity_type, field_key, can_view, can_edit). API Resource'lar response'u izne göre filtreler (örn. maaş alanı hr dışına kapalı). Form Engine (Faz 4) bu tabloyu tüketecek
- [ ] Rol Yönetimi UI v2: izin matrisi (modül→sayfa→aksiyon ızgarası), veri kapsamı seçimi, alan izinleri sekmesi, rol kopyalama
- [ ] `user.type` enum'u yalnızca super_admin ayrımı için kalır; firma içi tüm ayrım Spatie rollerine taşınır (company_admin = "admin" rolü)

**Audit v2**
- [ ] `Auditable` trait + model observer: create/update/delete otomatik loglanır; yalnızca değişen alanların diff'i JSONB'ye (`old_values/new_values`)
- [ ] Ek olay logları: login/logout/başarısız giriş (var), **hassas veri okuma** (bordro görüntüleme/indirme), export işlemleri, rol/izin değişiklikleri, ayar değişiklikleri, lisans işlemleri
- [ ] Immutable garanti: activity_logs'a update/delete endpoint'i yok; saklama süresi firma ayarı (varsayılan süresiz); aylık partisyon hazırlığı (büyüme için)
- [ ] Audit Görüntüleyici v2: kayıt bazlı zaman çizelgesi (her detay sayfasında "Geçmiş" sekmesi) + global arama/filtre/export

**⚠️ Teknik borç — PHPUnit suite (Faz 0'da CI non-blocking bırakıldı; burada kapatılacak)**
- [ ] **PHPUnit test suite'ini yeşile çek:** eksik factory'ler (`CompanyFactory` vb.) + test veri şeması + enum/tip uyumu; CI'da PHPUnit `continue-on-error`'ı **KALDIR** (blocking yap).
  - Teşhis (2026-07-10, sqlite `:memory:`, düzeltme yok): **41 failed / 2 passed**
  - `Class "Database\Factories\CompanyFactory" not found` (~23 test) — `ExpenseTest`, `SurveyTest`, `TimesheetTest` setUp'ta `Company::factory()` kullanıyor; factory dosyası yok
  - `CHECK constraint failed: type` (~18 test) — `RouteAuthorizationTest` / `RouteComprehensiveTest` `users.type = 'employee'` yazıyor; migration enum'u yalnızca `super_admin | company_admin | user`
  - MySQL ortamında aynı tip uyumsuzluğu `Data truncated for column 'type'` olarak da görülür
  - Mevcut factory'ler: `UserFactory`, `SurveyFactory`, `SurveyQuestionFactory`, `ExpenseClaimFactory`, `ExpenseCategoryFactory`, `AttendanceRecordFactory` — `CompanyFactory` eksik

**Kimlik sertleştirme**
- [ ] Gerçek TOTP 2FA (UserController:744 stub → doğrulama + recovery codes + login akışına entegrasyon)
- [ ] Parola politikası (firma ayarı: uzunluk/karmaşıklık/geçerlilik), Sanctum token süresi netleştirilir (config), oturum listesi/sonlandırma UI polish

**DoD:** Postman ile token kullanan bir "employee" rolü admin endpoint'lerinden 403 alır (test kanıtlı); maaş alanı yetkisiz Resource response'unda görünmez; herhangi bir personel kaydının tüm değişim geçmişi UI'da izlenir; 2FA uçtan uca çalışır.

---

### FAZ 3 — Tasarım Sistemi v2: Kompakt UI (2–3 hafta)

**Amaç:** "Küçük ekran laptop'ta rahat kullanım" (13", 1366×768) hedefi. Form Engine'den ÖNCE yapılır ki yeni motorlar doğru yoğunlukta doğsun.

- [ ] `packages/shared/src/styles/theme.css` token revizyonu: tipografi ölçeği (base 13–14px veri ekranlarında), spacing ölçeği, kontrol yükseklikleri (input/button ~32px), tablo satır yüksekliği (~36px), kart padding'leri
- [ ] **Density modu:** `data-density="compact|comfortable"` (kullanıcı tercihi, themeSlice + preferences'a kaydedilir)
- [ ] DataTable standardizasyonu: sticky header, kolon genişlik/sıra ayarı, yoğunluk desteği, sayfa boyutu tercihi, kayıtlı görünüm altyapısına hazırlık
- [ ] ModuleRail + ContextSidebar toplam genişliği daraltılır; ContextSidebar daraltılabilir olur; 1366px'te ana içerik alanı ≥ 1040px hedefi
- [ ] Form yerleşimi: ≥1280px'te 2 kolon standardı; modal boyut standartları
- [ ] Sayfa taraması: en yoğun 15 ekranda (personel listesi/detayı, izinler, dashboard...) yatay scroll ve taşma sıfırlanır
- [ ] Hardcoded renk/spacing avı → hepsi CSS variable'a (Cursor kuralı olarak da sabitlenir)
- [ ] Portal'daki Bootstrap 5 için karar: kısa vadede kalır; mobil uygulama fazı öncesi shared design system'e geçiş backlog'a

**DoD:** 1366×768'de Company panelinin ana akışları yatay scroll'suz ve rahat; density toggle çalışır; tema token'ları tek dosyadan yönetilir.

---

### FAZ 4 — Platform Motorları: "Zoho Çekirdeği" (6–8 hafta)

**Amaç:** Özelleştirme vaadinin kalbi. Dört motor + tek yönetim merkezi.

**4A. Form Engine (metadata-driven ekranlar)**
- [ ] Veri modeli: mevcut `custom_field_definitions` genişletilir + yeni `form_definitions` (entity_type, layout JSONB: bölümler/satırlar/alan sırası). Standart (sistem) alanlar da tanıma dahil edilir → yeniden adlandırma, zorunluluk, **devre dışı bırakma** (silme yok — Zoho deseni)
- [ ] Alan tipleri v1: text, textarea, number, decimal, date, select, multiselect, checkbox, phone, email, tckn (doğrulamalı), lookup (başka entity'ye referans), file
- [ ] Validasyon tek kaynaktan: alan tanımından backend'de Laravel rules, frontend'de zod şeması üretilir (tanım endpoint'i ile senkron)
- [ ] Koşullu görünürlük v1: "alan X = değer ise alan/bölüm Y görünür"
- [ ] Alan izinleri: Faz 2'deki `field_permissions` Form Engine render'ına bağlanır (görünmez / salt okunur / düzenlenebilir)
- [ ] `FormEngine` React bileşeni (react-hook-form + zod + alan registry) → **ilk geçiş: Personel formu** (en yüksek etki), ardından İzin talebi ve Talep formları
- [ ] **Liste görünümleri:** entity bazlı kolon seçimi, filtre setleri, kayıtlı görünümler (firma geneli + kişisel) — DataTable'a bağlanır
- [ ] Mevcut `application_forms` (form builder) ve `request_types.form_fields` bu motora rapte edilir (iki ayrı form altyapısı kalmaz)

**4B. Workflow Engine v2**
- [ ] Önce borç kapatma: WorkflowService.php:222 + ApprovalRecord.php:184 bildirim TODO'ları (onay isteği/sonucu bildirimleri)
- [ ] Genelleştirme: tetikleyici (kayıt oluştu/güncellendi/durum değişti/tarih geldi) + koşul builder (alan-operatör-değer, custom field dahil) + aksiyonlar (bildirim gönder, alan güncelle, onay akışı başlat, görev/talep oluştur, webhook çağır)
- [ ] Onay akışları mevcut motor üzerinden korunur (approval_workflows/steps/records/delegations); workflow UI'ı Ayarlar Stüdyosu'na taşınır
- [ ] Zamanlanmış tetikleyiciler için Laravel Scheduler devreye alınır (routes/console.php şu an boş): aylık izin hakedişi, devir işlemleri, doküman/sertifika süre uyarıları, deneme süresi hatırlatmaları

**4C. Bildirim Merkezi**
- [ ] Kanal soyutlaması: in-app (var) + e-posta (firma SMTP — var) + SMS (SmsService — var) tek servis arkasında
- [ ] Olay → şablon eşlemesi: firma bazlı düzenlenebilir şablonlar (değişken desteği: {{calisan.ad}}, {{izin.baslangic}}...)
- [ ] Kullanıcı bildirim tercihleri (olay bazında kanal aç/kapa); günlük özet (digest) seçeneği
- [ ] Tüm gönderimler queue üzerinden; gönderim logu

**4D. Ayarlar Stüdyosu (tek yönetim merkezi)**
- [ ] `/settings` tek çatı altında yeniden örgütlenir: Firma & Şubeler / Modüller / **Formlar & Alanlar** (Form Engine UI) / Liste Görünümleri / **İş Akışları** / **Bildirim Şablonları** / Roller & İzinler / İzin-Tatil Politikaları / Görünüm (tema-density-logo) / API & Webhook / Veri (import-export-KVKK)
- [ ] Her modülün ayarı kendi sayfasına gömülü değil, stüdyoda modül sekmesi olarak yaşar (Zoho deseni)

**DoD:** Bir firma admin'i kod olmadan: personel formuna alan ekler/kaldırır/yeniden adlandırır, alanı role kapatır, "5 günden uzun izinler GM onayına gitsin + e-posta atsın" akışını kurar, bildirim şablonunu düzenler — hepsi Ayarlar Stüdyosu'ndan.

---

### FAZ 5 — Rapor & Analitik Motoru (5–7 hafta)

**Amaç:** "PowerBI mantığı" — self-servis rapor + dashboard. Faz 2 (izinler) ve Faz 4 (custom fields) üstüne kurulur.

- [ ] **Semantic layer (dataset registry):** her modül veri setlerini kodda tanımlar (Personel, İzin Kayıtları, İzin Bakiyeleri, Başvurular, Puantaj, Masraflar, Eğitim Katılımları, Zimmetler, Anket Sonuçları...). Her dataset: alanlar (tip + Türkçe etiket), izinli join'ler, boyutlar/ölçüler, izin gereksinimleri. **Custom field'lar otomatik dahil olur**
- [ ] **Güvenli query builder servisi:** yalnızca whitelisted alan/join/aggregation; her sorguya otomatik company scope + veri kapsamı + alan izinleri uygulanır (maaşı göremeyen, maaş raporu da çekemez). Ham SQL asla kullanıcıdan alınmaz
- [ ] Rapor tanımı JSONB: dataset, kolonlar, filtreler (custom field dahil), gruplama, sıralama, özet satırları, grafik konfigürasyonu → mevcut `saved_reports` tablosu genişletilir
- [ ] **Rapor Builder UI:** 3 panel (alan listesi / kanvas / canlı önizleme); tablo + pivot + Nivo grafikleri (bar, line, pie, heatmap, treemap zaten bağımlılıkta); rapor kaydetme, rol/kişi bazlı paylaşım
- [ ] Export: mevcut ExcelJS/jsPDF hattı rapor motoruna bağlanır; **zamanlanmış raporlar** (scheduler + queue + e-posta ekli)
- [ ] **Dashboard v2:** `employee_dashboards` + react-grid-layout altyapısı genelleştirilir → her kayıtlı rapor bir widget olarak dashboard'a eklenebilir; rol bazlı varsayılan dashboard'lar
- [ ] Varsayılan rapor paketi: her modülle gelen 5–10 hazır rapor (turnover, izin kullanım, time-to-hire, eğitim tamamlama, demografi...) — hepsi motorda tanımlı, yani firma kopyalayıp özelleştirebilir
- [ ] `/analytics` (hr-analytics) sayfaları motorun üstüne taşınır (çift altyapı kalmaz)

**DoD:** Admin, "departman bazında son 12 ay izin günleri + custom 'sendika üyesi' alanına göre kırılım" raporunu sürükle-bırak ile kurar, kaydeder, dashboard'a ekler, her pazartesi 09:00'da e-posta ile alır. Yetkisiz kullanıcı aynı raporu açtığında yetkisiz alanlar/satırlar gelmez.

---

### FAZ 6 — Modül Derinleştirme + Türkiye Uyumu (8–12 hafta)

**Amaç:** Modülleri "satılabilir" kaliteye çekmek. Her modül için standart geçiş paketi: **Form Engine'e bağlan + izin matrisi + dataset kaydı + varsayılan raporlar + bildirim olayları + eksikler**.

**6A. Pilot çekirdeği (öncelik sırasıyla — pilot kapısı bu blokta):**
- [ ] **Personel/Özlük:** Türkiye alan seti (TCKN doğrulama, SGK sicil, İŞKUR meslek kodu, eğitim durumu, engel oranı, yabancı çalışma izni, BES katılım); işten çıkış (offboarding) akışı: çıkış nedeni (SGK kodları), çıkış checklist'i, zimmet iadesi entegrasyonu, ibraname şablonu
- [ ] **İzin:** İş Kanunu hakediş kuralları hazır politika olarak (kıdem 1–5 yıl: 14, 5–15: 20, 15+: 26 iş günü; 18 yaş altı / 50 yaş üstü min. 20); yasal izin türleri seed (evlilik 3, babalık 5, vefat 3, doğum 16 hafta, süt izni saat bazlı); resmi tatil takvimi (yıllık seed + yarım gün arefe); accrual scheduler'a bağlanır
- [ ] **Puantaj & Vardiya:** Company panelinde eksik yönetim ekranları; PDKS import arayüzü (CSV/Excel şablonu — cihaz entegrasyonu backlog); fazla mesai hesap kuralları; bordro-hazır aylık puantaj raporu
- [ ] **Masraf:** Company panelinde yönetim/onay sayfaları (şu an sadece Portal'da); kategori limitleri; workflow entegrasyonu
- [ ] **Doküman + Özlük Evrakı:** zorunlu evrak setleri (işe girişte istenenler), süre takibi/uyarıları (sertifika, sağlık raporu), versiyonlama polish

**6B. İkinci halka:**
- [ ] **Performans:** eksik frontend route'ları (periods, criteria); OKR/360 UI'larının tamamlanması; dönem sihirbazı
- [ ] **İşe Alım:** public kariyer sayfası polish (firma slug'lı tema); Kanban aday panosu; teklif (job_offers) UI'ı
- [ ] **Onboarding:** templates sayfası; preboarding token akışının UI'ı; buddy sistemi
- [ ] **Eğitim:** sessions yönetim sayfaları; zorunlu eğitim atama + hatırlatma (workflow ile)
- [ ] **Varlık:** categories/assignments sayfaları; zimmet formu çıktısı (PDF, imza alanlı)
- [ ] **Anket & eNPS:** anonim yanıt garantisi netleştirme; eNPS trend raporu
- [ ] **YENİ — Ücret Yönetimi (bordro değil):** ücret bantları, ücret geçmişi (zam kayıtları), dönemsel zam planlama, toplam gelir görünümü. Bordronun ileride oturacağı veri modelini şimdiden hazırlar
- [ ] **YENİ — Talep/Vaka Yönetimi genişletme:** mevcut employee_requests → SLA, kategori bazlı atama, İK helpdesk görünümü

**6C. KVKK Modülü (çekirdek — lisansla satılmaz, yasal zorunluluk):**
- [ ] Aydınlatma metni versiyonlama + çalışan onay (rıza) kayıtları (portal ilk girişte)
- [ ] Veri ihracı: çalışanın kendi verilerini JSON/PDF alma (portability)
- [ ] Silme/anonimleştirme talebi akışı (workflow motoru kullanır; işten çıkış sonrası saklama süresi dolunca anonimleştirme job'ı)
- [ ] Saklama süresi politikaları (evrak/log/aday verisi bazında firma ayarı — aday verisi için ayrı süre)
- [ ] Kişisel veri envanteri raporu (VERBİS hazırlığına yardımcı)

**DoD (modül başına):** Formları Form Engine'de, izinleri matriste, dataset'i rapor motorunda, kritik olayları bildirimde; feature testleri yeşil; modül lisans sisteminden aç/kapa edilebilir.

---

### FAZ 7 — On-Prem Paketleme + Lisans v1 + GA Hazırlığı (3–4 hafta)

- [ ] `APP_MODE=standalone`: SuperAdmin gizli, tek firma otomatik, kayıt kapalı; kod içinde if/else minimum (config + service provider seviyesinde)
- [ ] On-prem dağıtım paketi: versiyonlu Docker imajları + docker-compose.prod.yml + `install.sh` (env üretimi, key generate, migrate, seed, ilk admin)
- [ ] **Lisans v1:** ed25519 imzalı lisans dosyası (firma adı, modül listesi, kullanıcı limiti, bitiş tarihi) — offline doğrulama; uygulama açılışta + günlük kontrol. *(Gelişmiş/özel lisanslama mekanizması ayrı ve gizli bir iş kalemi olarak bu fazdan sonra ele alınacak — bu belgede detaylandırılmaz.)*
- [ ] Güncelleme mekanizması: `update.sh` (imaj çek → maintenance → migrate → up); sürüm notları düzeni
- [ ] Yedekleme/geri yükleme aracı: pg_dump + storage arşivi, cron'lu; restore prosedürü dokümante
- [ ] Sağlık/izleme: `/up` genişletilir (db, redis, queue, storage, lisans durumu); on-prem admin'e sistem durumu sayfası
- [ ] Production sertleştirme: Telescope prod'da kapalı, debug kapalı, log rotasyonu, dosya upload limitleri, virüs tarama hook'u (opsiyonel)
- [ ] Cloud tarafı: staging ortamı, otomatik deploy, uptime izleme
- [ ] Dokümantasyon: kurulum kılavuzu (on-prem), admin el kitabı, API dokümantasyonu (mevcut api_keys/webhooks müşterileri için)

**DoD:** İnternetsiz bir Ubuntu sunucuya paket + lisans dosyası ile 30 dakikada kurulum; sürüm güncellemesi veri kaybısız; aynı kod cloud'da multi-tenant çalışmaya devam eder. → **GA v1.0**

---

### FAZ 8 — Sonraki Ufuk (GA sonrası, sıralaması pazara göre)

- [ ] **Mobil uygulama:** Portal Capacitor altyapısı üzerinden iOS/Android yayını; push notification (bildirim merkezine 4. kanal); Bootstrap → shared design system geçişi bu fazda
- [ ] **AI katmanı:** doğal dille rapor ("geçen ay departman bazında devamsızlık" → rapor motoru sorgusu), CV ayrıştırma (işe alım), anket yorum özetleme, İK asistanı (politika soru-cevap)
- [ ] **Bordro modülü:** Ücret Yönetimi (6B) veri modeli üzerine; SGK/e-Bildirge entegrasyonları — ayrı ve büyük bir proje olarak planlanır
- [ ] Entegrasyon pazarı: muhasebe (Logo/Mikro/Netsis), takvim (Google/Outlook), SSO (Azure AD/Google — kurumsal on-prem talebi), PDKS cihaz entegrasyonları
- [ ] Toplu i18n string migrasyonu + İngilizce → global açılım
- [ ] PostgreSQL RLS (defense-in-depth), aylık audit partisyonlama, read replica — ölçek geldikçe

---

## 6. Modül Envanteri ve Satış Paketleri

**Çekirdek (her lisansta, ayrıca satılmaz):** Kullanıcı & Rol Yönetimi, Firma/Şube/Departman, Özlük (temel), Self-Servis Portal, Duyurular, Talepler, Audit Log, Bildirim Merkezi, KVKK araçları, temel raporlar.

**Satılabilir modüller (tekil aç/kapa — mevcut `modules` + `company_modules` altyapısı):** İzin Yönetimi · Puantaj & Vardiya · İşe Alım · Onboarding/Offboarding · Performans · Eğitim · Varlık/Zimmet · Masraf · Anket & eNPS · Doküman+ (gelişmiş evrak) · Ücret Yönetimi.

**Premium katman:** Rapor Builder (self-servis BI) · Gelişmiş Workflow Otomasyonu · API & Webhook erişimi. *(Temel hazır raporlar ve temel onay akışları çekirdekte kalır; "kendin kur" seviyesi premium'dur.)*

**Paket önerisi:** Starter (çekirdek + 2 modül) / Professional (çekirdek + 6 modül + Workflow) / Enterprise (hepsi + BI + API + on-prem seçeneği). Mevcut license_packages tablosu bunu zaten taşıyabiliyor.

---

## 7. Kilometre Taşları

| Kapı | Ne zaman | Ne anlama geliyor |
|------|----------|-------------------|
| **M1 — Güvenli Çekirdek** | Faz 2 sonu | İzin sistemi gerçek; demo verilebilir |
| **M2 — Platform Tamam** | Faz 5 sonu | Özelleştirme + BI çalışıyor; dogfooding başlar |
| **M3 — Pilot** | Faz 6A sonu | 1–2 dost firma canlı kullanımda (çekirdek + izin + puantaj + masraf) |
| **M4 — GA v1.0** | Faz 7 sonu | Cloud satış açık + on-prem teklif verilebilir |

Toplam tahmin: **~29–42 hafta (7–10 ay)** tam zamanlı. Pilot geri bildirimi Faz 6B önceliklerini değiştirebilir — bu belge yaşayan bir belgedir, her faz sonunda revize edilir.

---

## 8. Riskler ve Çalışma Kuralları

1. **Kapsam şişmesi (en büyük risk):** 15 modül + platform, solo için deniz. Panzehir: İlke #6 (derinlik > genişlik) ve fazların sırasına sadakat. Yeni fikirler bu dosyanın sonundaki Backlog'a yazılır, araya alınmaz.
2. **AI kod tutarsızlığı:** Cursor uzun projede desen kaybeder. Panzehir: repo köküne `.cursorrules` (ekli dosya) + her faz sonunda "tutarlılık turu" (isimlendirme, ölü kod, konvansiyon taraması).
3. **Test borcu:** DoD'lerde test şartı pazarlıksızdır; özellikle Faz 2 izin matrisi testleri platformun sigortasıdır.
4. **Big-bang refactor tuzağı:** Form Engine ve rapor motoruna geçiş **modül modül** yapılır; eski ekran, yenisi kanıtlanana kadar silinmez.
5. **On-prem destek yükü:** Standart paket dışı kuruluma hayır (yalnızca Docker Compose, yalnızca PostgreSQL). Müşteri özelleştirmesi kod değil, Ayarlar Stüdyosu ile.

---

## 9. Backlog (araya alınmaz, buraya yazılır)

- PostgreSQL Row-Level Security · SSO/SAML · PDKS cihaz canlı entegrasyonu · e-imza entegrasyonu (evrak) · Vardiya optimizasyonu/AI planlama · Çalışan mobil push kampanyaları · Marketplace/eklenti mimarisi · Beyaz etiket (partner) modeli

---

*Sonraki adım: Faz 0'a başlamadan önce bu ROADMAP repo köküne eklenir, `.cursorrules` kurulur ve Faz 0 iş listesi Cursor'a adım adım verilir. Her fazın başında bu belge üzerinden o faza özel Cursor promptları hazırlanır.*
