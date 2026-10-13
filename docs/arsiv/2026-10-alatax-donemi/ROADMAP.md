# ALATAX HR — ROADMAP v1.1

**Tarih:** 6 Ağustos 2026 (yeniden çerçeveleme) · önceki: 31 Tem 2026 (DOK-3)
**Girdiler:** Güncel kod + `GUNCEL_DURUM_RAPORU.md` + genel pazar / ölçek aralığı + FAZ_A §A3 holding kararı
**Bağlam:** Solo geliştirici (Cursor + AI destekli), Türkiye first, genel pazar ürünü; Dobedan pilot/doğrulama; bordro motoru kapsam dışı (aktarım paketi var).

---

## 0. Ürün çerçevesi ve ölçek

> Ürün **genel pazara** geliştirilir. Dobedan (veya herhangi bir pilot) **doğrulama ortamıdır**; gereksinimi belirlemez. Tasarım kararları bu bölüme göre verilir.

| Madde | Değer |
|-------|--------|
| Hedef pazar | Türkiye B2B HR SaaS — cloud + on-prem tek kod tabanı |
| Pilot / doğrulama | Dobedan ve benzeri kurulumlar; ürün yolunu kilitlemez |
| Kurulum | Cloud SaaS ve on-prem paket (aynı kod; `APP_MODE` + lisans) |
| Ölçek aralığı | **Tek şubeli ~10 kişiden** → **yüzlerce şubeli / on binlerce personele**; holding’de birden çok şirket (veri karışmaz; raporlama gruba yayılabilir) |
| Yönetim modeli | Merkez İK + şube/şirket bağlamı; tenant izolasyonu zorunlu |
| Bordro | Dış sistemde kalabilir → çıktı = **puantaj / sicil aktarım paketi** (bkz. `ENTEGRASYON_SPEC.md`) |
| PDKS | Donanım opsiyonel; QR/telefon; büyük vardiya başında **yüzlerce eşzamanlı** okutma hedefi |
| İlk sürüm hedefi | **14 modülün tamamı** satılabilir kalitede (kısmi çıkış / “yarım paket” yok) |

**Holding kararı (DOK-3):** FAZ_A §A3 **SEÇENEK 1 onaylandı** (`organizations` / company_groups + DataScope `group`). SEÇENEK 2 (cross-tenant bypass) reddedildi. Önceki “holding park” kararı **iptal**. Uygulama fazı: **Faz G** (Faz 6’dan önce).

**Tema (tutarlılık):** Company / SuperAdmin mevcut davranış; Portal varsayılan **açık tema**.

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
4. **Tek veritabanı motoru (uygulama default): PostgreSQL.** Default connection `pgsql`. MySQL bilgisayardan / Docker'dan **ASLA silinmez** — legacy olarak korunur (devre dışı bırakılabilir ama servis/bağımlılık kalır). SQLite yalnızca acil lokal deneme; CI ve prod pgsql. JSONB + GIN index, custom field ve audit mimarisinin temelidir.
5. **Kod cloud/on-prem ayrımı bilmez.** Fark yalnızca konfigürasyon (`APP_MODE`) ve lisans katmanındadır.
6. **Derinlik > genişlik — ama ilk genel sürümde 14’ün tamamı.** Paket satışında à la carte korunur; pilot ve genel pazarda 14 modül “satılabilir” kalitede çıkar (kısmi çıkış yok). Yarım modül teslim edilmez.
7. **Türkçe-first, i18n-ready.** Arayüz Türkçe; ancak bugünden itibaren yazılan her yeni metin çeviri altyapısından (t()) geçer. Toplu string migrasyonu global açılım öncesine ertelenir.
8. **Her faz test ve dokümantasyonla kapanır.** DoD (Definition of Done) karşılanmadan sonraki faza geçilmez.

---

## 3. Mevcut Durum → Hedef Özeti

| Alan | Bugün (Snapshot) | Hedef |
|------|------------------|-------|
| Veritabanı | **PostgreSQL 16** default (Faz 1); mysql legacy; json→jsonb + string+CHECK | Baseline squash + GIN (Faz 6 / temizlik turu) |
| Yetki | **Route + Policy + alan + data scope** (Faz 2); Spatie admin; company_admin bypass yok | Rol UI v2 polish (Faz 6); Form Engine alan izin bağlama (Faz 4) |
| Audit | **Auditable + observer** (P0 modeller); hassas alan maskeleme | P1 modeller + Geçmiş UI (Faz 6); immutable/retention backlog |
| Özelleştirme | custom_field_definitions + renderer (tohum) | Form Engine: layout, koşullu görünürlük, alan izinleri, liste görünümleri |
| Workflow | approval_workflows + Policy/data scope | Genel amaçlı motor: tetikleyici + koşul + aksiyon; bildirim entegre |
| Bildirim | In-app var; e-posta/SMS/push kısmi, otomasyon yok | Bildirim Merkezi: şablonlar, kanallar, kullanıcı tercihleri |
| Raporlama | Modül bazlı sabit raporlar + Excel/PDF export | Semantic layer + sürükle-bırak rapor builder + dashboard v2 |
| KVKK | Yok | Rıza, veri ihracı, silme/anonimleştirme, saklama politikaları |
| Deployment | Docker Compose + CI (Pint/FE/PHPUnit blocking) | On-prem installer, imzalı lisans |
| UI | Desktop odaklı, ekranlar büyük | Kompakt token ölçeği, 1366×768 hedefi, density modu |
| i18n | Altyapı kurulu (tr); yeni kod t() zorunlu (Faz 0) | Toplu string migrasyonu + dil switcher + EN (backlog) |
| Test | **633 passed** (QA-3, `alatax_hr_testing`), PHPUnit CI blocking | Endpoint auth+permission+happy path + grup izolasyon (Faz G) |

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

### FAZ 0 — Stabilizasyon ve Temeller ✅ KAPANDI (10–11 Temmuz 2026)

**Amaç:** Kırıkları kapatmak, geliştirme altyapısını kurmak. Bu faz bitmeden hiçbir yeni özellik yazılmaz.

**Kapanış özeti:** Başlangıçta git yoktu, build kırık, ~230 lint/tsc hatası, CORS’ta hardcoded IP ve aşırı throttle vardı. Bitişte: temiz frontend (3 SPA lint/tsc 0), GitHub’da yedekli repo, CI korumalı (Pint + ESLint + build), Docker Compose (6 servis), env tabanlı CORS + grup throttle, i18n (tr) altyapısı. **11 commit** (`dab902b`…`ebb59cc`), 10–11 Temmuz 2026.

- [x] Migration çakışmalarını çöz: `document_categories` çift create, `announcement_reads` çift şema, `request_types` çift set → etkin şemalar netleştirildi (kalıcı squash Faz 1)
- [x] `DatabaseSeeder`'a `LicensePackageSeeder` ve `LeaveTypeSeeder` eklendi; temiz kurulum tek komutla ayağa kalkar
- [x] Frontend bug turu: Portal `state.auth.loading` → `isLoading`; `notificationSlice` store'a kayıt; `ADMIN_ROUTES.LICENSE_PACKAGES`; boş `components/index.ts`
- [x] tsc + lint temizliği (3 SPA sıfır hata) + Git / GitHub kurulumu
- [x] Forgot-password / reset-password sayfaları (3 SPA) + davet / şifre sıfırlama Mailable'ları
- [x] Docker Compose (dev): app + nginx + mysql + redis + worker + scheduler (6 servis) — on-prem paketinin temeli; Postgres geçişi Faz 1
- [x] CI (GitHub Actions): Pint + ESLint + tsc/build her push'ta; PHPUnit → Faz 2'de blocking yapıldı
- [x] CORS env tabanlı (`CORS_ALLOWED_ORIGINS`); throttle: auth 10/dk, api 120/dk, exports 20/dk, public 20/dk + `AuthThrottleTest`
- [x] i18n altyapısı (tr): react-i18next `@shared/i18n` + Laravel `lang/tr`; **kural:** yeni UI metni `t()` zorunlu (`.cursorrules`, `docs/I18N.md`)
- [x] react-hook-form + zod benimsendi (Form Engine temeli); forgot/reset formlarında kullanılıyor

**Faz 0 — Açık Borçlar** (Faz 1/2 veya backlog’a taşınır; DoD’yi bloklamaz)

- [x] **[Faz 2]** PHPUnit suite yeşile çekme + CI blocking — ✅ kapandı (11 Tem 2026)
- [ ] **[Faz 0-son]** Mailtrap SMTP testi: forgot/reset + davet maillerini uçtan uca doğrula (kod hazır; SMTP bağla → queue → inbox).
- [ ] **[Faz 1]** XAMPP/Docker `.env` locale ikiliği: Docker’a tam geçince `backend/.env` `APP_LOCALE` tekilleşir (compose env şimdilik ezer).
- [x] **[Faz 0 kalıntı → B-4]** 6 kayıp route: 5 bağlandı (tab+BE mevcut), `/assets/assignments` sidebar’dan kaldırıldı (ayrı liste sayfası yok; zimmet varlık detayında)
- [ ] **[Faz 0 kalıntı]** `_archive_old_app/` repo’dan çıkar (ayrı branch/arşiv).
- [ ] **[backlog]** i18n eski string toplu migrasyonu (global açılım öncesi).
- [ ] **[backlog]** i18n dil değiştirici UI + EN locale.

**DoD (kapanış):** `docker compose up` + seed ile sistem ayağa kalkar; CI yeşil (PHPUnit hariç — bilinen borç); login + forgot/reset UI + i18n/CORS/throttle tamam. Mailtrap uçtan uca test ve PHPUnit yeşili açık borçta.

---

### FAZ 1 — PostgreSQL Geçişi ✅ KAPANDI (11 Temmuz 2026)

**Amaç:** Tek ve doğru veritabanı motoruna geçiş + temiz şema baseline'ı.

**Kapanış özeti:** Default DB **pgsql**; Docker app → postgres; CI postgres:16 service. `json`→`jsonb`, `enum`→string+CHECK (`PortableEnum`) + çekirdek PHP enums. `migrate:fresh --seed` **pgsql ve mysql** yeşil. MySQL servisi **silinmedi** (legacy). Migration squash **Faz 2'ye ertelendi**. Branch: `faz1-postgresql` → main merge `bd9f47a`.

- [x] Karar: mevcut SQLite dump test verisi — Docker/pgsql fresh seed ile devam (pgloader gerekmedi)
- [x] MySQL'e özgü ifadeler ayıklandı (employees nullable migration); enum → string + CHECK; JSON → jsonb
- [x] `config/database.php` + `.env.example` / compose default → **pgsql**; README güncellendi
- [x] CI feature/migrate PostgreSQL service container üzerinde
- [ ] ~~67 migration squash~~ → **Faz 2'de de ertelendi** → Faz 6 / ayrı temizlik turu
- [ ] İndeks stratejisi GIN (JSONB) — squash/baseline ile birlikte netleştirilir
- [ ] pg_dump yedekleme script'i — on-prem paketi ile (sonraki faz)

**Faz 1 — Açık Borçlar**

- [ ] **[Faz 6 / temizlik]** Migration squash: 64 dosya → modül bazlı baseline (`0001_core`…); squash öncesi/sonrası `pg_dump` şema diff
- [x] **[Faz 2]** PHPUnit factory'leri + `users.type` uyumu + CI blocking — ✅ kapandı
- [ ] **[kalıcı]** MySQL Docker servisi legacy — **silinmez** (aşağıdaki kural)

**DoD (kapanış):** `migrate:fresh --seed` PostgreSQL'de hatasız ✅; default pgsql ✅; CI pgsql migrate+pint+frontend yeşil ✅; mysql legacy korunur ✅.

---

### FAZ 2 — Güvenlik ve Yetki Çekirdeği: RBAC v2 + Audit v2 ✅ KAPANDI (11 Temmuz 2026)

**Amaç:** "Detaylı rol tabanlı + her şey loglanır" vaadini gerçeğe çevirmek. Platformun güven katmanı.

**Kapanış özeti:** 7 blok / ~10 dalga. Route permission **0 → 343**; Policy + data scope (`own/team/department/company`); alan seviyesi (maaş/TCKN); Audit v2 (Auditable + P0); gerçek TOTP 2FA; `company_admin` Gate bypass kaldırıldı (Spatie `admin`); PHPUnit **186** + CI blocking. Branch `faz2-rbac-audit` → main merge `0d3ecfd`. Detay: `docs/FAZ2_RAPOR.md`.

**RBAC v2**
- [x] `{module}.{page}.{action}` + **tüm route'lara** `permission:` (343); Gate::before hiyerarşik wildcard; Wave 1–4 testleri
- [x] Model Policy'ler (Employee, LeaveRequest, Document, EmployeeDocument, ExpenseClaim, PerformanceReview, ApprovalRecord) + kayıt seviyesi
- [x] **Veri kapsamı (data scope):** rol `data_scope` + `DataScopeService` (`own / team / department / company`)
- [x] **Alan seviyesi izin:** `employees.salary.view` / `tckn.view` + EmployeeResource filtre; Form Engine bağlama → Faz 4
- [ ] Rol Yönetimi UI v2: izin matrisi ızgarası, alan izinleri sekmesi, rol kopyalama → **[Faz 6]** polish
- [x] firma içi yetki Spatie rollerinden; `company_admin` = Spatie `admin` (Gate type bypass kaldırıldı); `super_admin` type bypass kaldı

**Audit v2**
- [x] `Auditable` + `AuditObserver`: create/update/delete diff → JSONB; hassas alan maskeleme
- [x] P0 modeller + Spatie pivot log; login/2FA/rol olayları
- [ ] Immutable garanti / saklama süresi / partisyon → **[backlog]**
- [ ] Audit Görüntüleyici v2 (kayıt "Geçmiş" sekmesi tüm modüllerde) → **[Faz 6]** frontend

**Teknik borç (Faz 1'den)**
- [ ] ~~Migration squash~~ → **HÂLÂ ertelendi** — **[Faz 6 veya ayrı temizlik turu]**
- [x] PHPUnit yeşil (pgsql) + CI `continue-on-error` **KALDIRILDI** (blocking)

**Kimlik sertleştirme**
- [x] Gerçek TOTP 2FA (pragmarx/google2fa + challenge token + recovery codes)
- [ ] Parola politikası / token süresi UI polish → backlog / Faz 6

**Faz 2 — Açık borçlar / ertelenenler**

- [ ] **[Faz 6]** P1 modelleri Auditable yap (Branch, Asset, Survey, Payslip, Recruitment)
- [ ] **[Faz 6]** diğer modüllerin "Geçmiş" sekmesi (frontend)
- [ ] **[backlog]** DB-level audit immutable, audit retention/partisyon
- [ ] **[backlog]** audit senkron→queue (yük olursa)
- [ ] **[Faz 6 / temizlik]** migration squash (Faz 2 kapanış notu: HÂLÂ ertelendi)

**DoD (kapanış):** employee rolü admin endpoint'lerinden 403 ✅; maaş yetkisiz response'ta yok ✅; Employee Geçmiş (audit) ✅; 2FA uçtan uca ✅; company_admin bypass yok ✅; 186 test + CI blocking ✅.

---

### FAZ 3 — Tasarım Sistemi v2: Kompakt UI ✅ KAPANDI (çekirdek; cilalama borçları Faz 4’e)

**Amaç:** "Küçük ekran laptop'ta rahat kullanım" (13", 1366×768) hedefi. Form Engine'den ÖNCE yapılır ki yeni motorlar doğru yoğunlukta doğsun.

- [x] `packages/shared/src/styles/theme.css` token revizyonu (spacing/tipografi/kontrol yükseklikleri)
- [x] **Density modu:** `data-density` + kullanıcı tercihi
- [x] DataTable ortak bileşen + yoğunluk uyumu
- [x] ModuleRail / ContextSidebar kompakt kullanım
- [x] Hardcoded renk/spacing → CSS variable kuralı (`.cursorrules`)
- [ ] Portal Bootstrap → shared design system (backlog)
- [ ] Dropdown/Select görsel cilalama → Faz 4 borç

**DoD (çekirdek):** tema token’ları tek dosyadan; density çalışır; Company ana akışlar kompakt token’larla.

---

### FAZ 4 — Platform Motorları: "Zoho Çekirdeği" (6–8 hafta)

**Amaç:** Özelleştirme vaadinin kalbi. Dört motor + tek yönetim merkezi.

**Ayarlar Stüdyosu vizyonu (kullanıcı):** İki üst menü (modül alanında):
(1) **AYARLAR** = kişisel (kullanıcı kendi hesabı: profil, şifre, 2FA, bildirim tercihi, tema/density, dil).
(2) **YÖNETİM (Ayarlar Stüdyosu)** = firma özelleştirme, rol bazlı. Tüm modüller sırayla, her modülün özelleştirilebilir sayfaları. Buradan: özel alan aç, picklist/combobox içeriklerini yönet (izin türleri, kanban aşamaları/isimleri, kategori listeleri, durum seçenekleri), form düzeni. Firma default gelen listeleri kendine göre tasarlar → satış sonrası destek yükü azalır (Zoho mantığı). Firma/program ayarları da burada, gruplandırılmış. Rol bazlı: herkes her sayfayı göremez.
**Sıra:** (1) özel alan + picklist → (2) izin/kanban özelleştirme → (3) Ayarlar Stüdyosu iskeleti → (4) form düzeni/koşullu görünürlük.

**Lookup / picklist borçları (Faz 4):**
- [ ] **CASCADING / DEPENDENT PICKLIST (Faz 4 sonu, yayılım sonrası):** `parent_lookup_id` altyapısı hazır (kolon var). Lookup'lar birbirine bağlanacak: departman→pozisyon, şehir→ilçe, kategori→alt kategori. Bir alanın değeri seçilince bağlı alanın seçenekleri filtrelenir (Zoho *Map Dependency* deseni). **Ön koşul:** ilgili lookup'lar tekil olarak sisteme bağlı olmalı → bu yüzden yayılım **SONRASI**. Lookup Engine'i tamamlayan son parça — unutma.

**Faz 3/4 görsel cilalama (borç — şimdi uygulama yok):**
- [ ] **DROPDOWN/SELECT GÖRSEL:** dropdown'larda uzun etiketler sığmıyor (kesiliyor/taşıyor), açık menü tasarımı zayıf. Ortak Select/Dropdown bileşeninde çözülecek — Lookup Engine yayılımında ortak bileşen kullanılacağı için **tek yerde** düzeltilince tüm dropdown'lar düzelir. Uçtan uca test turu görsel bulgularıyla birlikte ele alınacak. **Öncelik ayrımı:** "yazı okunamıyor/kesiliyor" = kullanılabilirlik (erken); "çirkin/boşluk" = ince ayar (en son).

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

#### YAPILANDIRILABILIR ONAY ZİNCİRİ (Workflow Engine v2) — kullanıcı vizyonu

**Yeri:** Lookup Engine + Ayarlar Stüdyosu iskeleti sonrası; Faz 4’ün **2. büyük parçası** (Form Engine ile yan yana / hemen ardından). Bu Faz 4’ün **en karmaşık** bileşeni — ayrı ve dikkatli ele alınır. Bildirim Merkezi (**4C**) ile entegre edilir.

**Vizyon:** Firma **her** onay akışını Ayarlar Stüdyosu → **Modül Ayarları** altında **elle** tasarlar (maksimum esneklik). Akış örnekleri: izin, işe alım, masraf, puantaj/shift, personel değişikliği, doküman onayı…

| Yetenek | Açıklama |
|---------|----------|
| Çok adımlı zincir | Her adıma onaycı: **ROL** (`hr_manager`), **KİŞİ** (belirli kullanıcı), veya **DİNAMİK** (“talep edenin departman yöneticisi”, “bir üst yönetici”, “CEO’ya kadar”) |
| Sıra modeli | Araştırma tercihi: **hibrit** — seviyeler arası **sıralı**, seviye içi **paralel** |
| Koşullu adımlar | Örn. “izin > 10 gün → GM adımı”, “pozisyon direktör → CEO onayı” (Horilla/Treegarden: departman + eşik) |
| Eskalasyon | Onaycı X saatte yanıtlamazsa üstüne yükselir |
| Vekalet | Onaycı yoksa (izinde) vekiline yönlenir — Faz 2 `delegation` temeli |
| Reddetme | Talep sahibine gerekçeyle döner; yeniden gönderilebilir |
| Departman kapsamı | Departman yöneticisi kendi departman taleplerini görür/onaylar — Faz 2 DataScope `department` **hazır** |

**Mevcut temel (Faz 2):** `ApprovalRecord` + Policy (atanan/vekil), `WorkflowService::canApprove`, delegation, DataScope (`own` / `team` / `department` / `branch` / `company`). Bugün **tek seviyeli**; v2 → **çok seviyeli + firma-yapılandırılabilir + koşullu**.

**Motor gereksinimi (yalnızca şema değil):** talep → zinciri başlat → sıradaki onaycıya bildir → onay/red → sonraki adım / geri dön → koşul değerlendir → eskalasyon/vekalet. Kurumsal workflow engine; 4C bildirimleriyle olay üretimi zorunlu.

**Departman erişimi (ilgili vizyon — bağlama Faz 6):** Departman yöneticisi/yetkilisi **talep halinde** yönetim alanına erişir; DataScope `department` + rol ile kendi dept kapsamında: CV havuzu, ilan aksiyonları, personel, puantaj/shift. Her modüle bağlama → **Faz 6** derinleştirme.

**UI yeri:** Ayarlar Stüdyosu → Modül Ayarları → ilgili akışın zincir editörü (sürükle-bırak adım + koşul builder).

**Teknik checklist (korunan + genişleyen):**
- [x] **B0:** `findApprover` Employee.manager; `approval_instances`; default leave seed; leave store/approve köprü; `ApprovalRequested` stub; Policy testleri yeşil
- [x] **B1:** Sıralı çok adım; red + `resubmit` yeni instance; adım sırası 403
- [x] **B2:** `dynamic_manager` / `dynamic_skip_manager` / role / user; unresolved → hr_manager (atlanmaz); vekalet motor testi
- [x] **B3:** Adım `condition` jsonb whitelist evaluator (`>`, `<`, `=`, `in`); koşul tutmayan SKIPPED
- [x] **B4:** `parallel_group` + `completion_policy` runtime; eskalasyon SLA job (hatırlatma + üst bildirim; yetki devretmez)
- [ ] **B5:** Ayarlar Stüdyosu akış tasarımcısı (Modül Ayarları)
- [x] Bildirim TODO stub: `ApprovalRequested` → database notification (tam 4C değil)
- [x] Zincir veri modeli genişletildi (condition / parallel_group kolonları); paralel runtime B4
- [x] Çalıştırma motoru (izin): başlat → bildir → onay/red → sonraki → koşul → vekalet → paralel/eskalasyon (B4)
- [ ] Genelleştirme (üst katman): tetikleyici + aksiyon kataloğu
- [ ] UI Ayarlar Stüdyosu’na taşınır (B5)
- [x] Zamanlanmış tetikleyiciler / eskalasyon job’ları (B4: `approvals:process-escalations`)
- [x] Feature testler: B0–B3 motor + LeaveRequestPolicy regresyon

**4C. Bildirim Merkezi**
- [ ] Kanal soyutlaması: in-app (var) + e-posta (firma SMTP — var) + SMS (SmsService — var) tek servis arkasında
- [ ] Olay → şablon eşlemesi: firma bazlı düzenlenebilir şablonlar (değişken desteği: {{calisan.ad}}, {{izin.baslangic}}...)
- [ ] Kullanıcı bildirim tercihleri (olay bazında kanal aç/kapa); günlük özet (digest) seçeneği
- [ ] Tüm gönderimler queue üzerinden; gönderim logu

**4D. Ayarlar Stüdyosu (tek yönetim merkezi)**
- [ ] `/settings` tek çatı altında yeniden örgütlenir: Firma & Şubeler / Modüller / **Formlar & Alanlar** (Form Engine UI) / Liste Görünümleri / **İş Akışları** / **Bildirim Şablonları** / Roller & İzinler / İzin-Tatil Politikaları / Görünüm (tema-density-logo) / API & Webhook / Veri (import-export-KVKK)
- [ ] Her modülün ayarı kendi sayfasına gömülü değil, stüdyoda modül sekmesi olarak yaşar (Zoho deseni)
- [x] **D4a Settings Registry** — davranış parametreleri motoru (`setting_values`, kapsamlı çözümleme, merkezi + bağlamsal ⚙ UI). Pilot: İzin + Rapor. Lookup ile birleştirilmez. Detay: `docs/AYAR_MOTORU.md`.
- [ ] **D4b Yardım motoru** → **Faz 8** (en son). İçerik borcu ertelenmez — aşağı DoD.

**DoD:** Bir firma admin'i kod olmadan: personel formuna alan ekler/kaldırır/yeniden adlandırır, alanı role kapatır, "5 günden uzun izinler GM onayına gitsin + e-posta atsın" akışını kurar, bildirim şablonunu düzenler — hepsi Ayarlar Stüdyosu'ndan.

**DoD (Settings Registry):** Her modül dalgası, modülün ayarlarını Settings Registry'ye kaydetmeden ve bağlamsal ⚙ panelinde göstermeden **BİTMİŞ SAYILMAZ**.

**DoD (Personal Data Collector):** Kişisel veri tutan her yeni modül, `PersonalDataCollector`'ını kaydetmeden **BİTMİŞ SAYILMAZ**.

**DoD (Yardım içeriği — D4b motorundan bağımsız):** Her modül dalgası, kendi yardım içeriğini `docs/help/{modul}/{sayfa}.md` olarak **YAZAR**. Yardım motoru (Faz 8) bu dosyaları render eder; içeriksiz modül dalgası bitmiş sayılmaz.

---

### FAZ 5 — Rapor & Analitik Motoru ✅ KAPANDI (2026-07-29)

**Amaç:** "PowerBI mantığı" — self-servis rapor + dashboard. Faz 2 (izinler) ve Faz 4 (custom fields) üstüne kurulur. Detay: `docs/FAZ5_RAPOR.md`. Şartname: `MODUL_SPEC` B14 Analitik.

#### Kapanış özeti

D1a–D1g tamam. **11 dataset**, whitelist query builder, builder UI, pivot/DSL, dashboard v2, paylaşım/gizlilik (min hücre), zamanlama+cache, hazır sistem rapor/pano paketi (`module_key` + `system_key`), `/analytics` → motor panosu. Suite: **561 passed**. Ek tablolar (özet): `saved_reports`/`dashboards` genişletme, `report_shares`, `report_access_logs`, `report_schedules`, `report_measures`, `role_default_dashboards`, vb. — bkz. `FAZ5_RAPOR.md`.

#### Alt dalgalar (D1a–D1g)

| Dalga | Kapsam | Durum |
|-------|--------|--------|
| **D1a** | Semantic layer (dataset registry) + güvenli query builder + rapor tanımı API | ✅ |
| **D1b** | Rapor Builder UI (3 panel) + grafik/tablo + export | ✅ |
| **D1c** | Pivot + drill-down + hesaplanan ölçü DSL + ölçü kütüphanesi | ✅ |
| **D1d** | Dashboard v2 (`dashboards` + paylaşım + çapraz/global filtre + widget guard) | ✅ — görsel kontrol borç |
| **D1e** | Paylaşım v2 + şeffaf alan gizleme + erişim logu + hassasiyet/min hücre | ✅ |
| **D1f** | Zamanlanmış rapor + abonelik + sonuç cache + performans | ✅ |
| **D1g** | Dataset yayılımı + hazır paket + modül panoları + `/analytics` motora taşıma | ✅ |

**Korunan vizyon maddeleri:**
- [x] Semantic layer + whitelist query builder + company/DataScope/alan izni
- [x] Rapor tanımı JSONB + Builder UI + export hattı
- [x] Pivot / drill / ölçü DSL
- [x] Dashboard v2 (employee_dashboards dokunulmaz; ayrı tablolar)
- [x] Dataset yayılımı + modül başına hazır rapor/pano (`module_key`)
- [x] Zamanlanmış raporlar (link varsayılan; special ek kapalı)
- [x] `/analytics` motor panosu; rol varsayılan `role_default_dashboards`

**DoD:** Admin, "departman bazında son 12 ay izin günleri + custom alan kırılımı" raporunu sürükle-bırak ile kurar, kaydeder, panoya ekler, zamanlanmış teslim alır. Yetkisiz kullanıcıda yetkisiz alan/satır gelmez. ✅ (görsel kullanıcı kontrolü borç)

**Açık borç (Faz 6+):** görsel kontroller · `HrAnalyticsController` kaldırma · `employee_dashboards` birleştirme · WebSocket · SCORM / performans / duyuru dataset.

---

### FAZ G — Grup / Holding mimarisi (Faz 6’dan ÖNCE)

**Karar (DOK-3):** FAZ_A §A3 **SEÇENEK 1 ONAYLANDI**. SEÇENEK 2 (cross-tenant bypass) **reddedildi**. Önceki “holding park / ertelendi” kararı **iptal**.

**Gerekçe (kısa):** 14 modül tek-şirket varsayımıyla yazılırsa grup katmanı sonradan hepsini yeniden yazdırır. Rapor motoru bugün 11 dataset iken genişletmek ucuz; modül derinleştirmeden sonra pahalı.

⚠️ **RİSK NOTU (en kritik):** Cross-company **veri sızıntısı**. Her `company_id` filtresi, Policy, BelongsToCompany ve rapor sorgusu grup kapsamında yanlışlıkla genişleyebilir.

**DoD (pazarlıksız):**
- Mevcut tüm DataScope / Policy testleri **yeşil kalır**
- Yeni **grup izolasyon test paketi** yeşil (grup A kullanıcısı grup B / yabancı şirket verisini göremez; `group` scope yalnızca organization altındaki şirket setini görür)

#### Kapsam taslağı (kod yok — plan)

| Parça | Not |
|-------|-----|
| `organizations` (veya `company_groups`) tablosu | Holding / grup kökü |
| `companies.organization_id` | Şirket → grup FK |
| DataScope `group` seviyesi | `group` > `company` > `branch` > … |
| BelongsToCompany | “erişilebilir şirket seti” modeli (tek `company_id` yerine kontrollü set) |
| FE şirket bağlamı seçici | Merkez İK hangi şirket(ler) üzerinde çalışıyor |
| Grup kapsamlı rapor / pano | Dataset’ler organization setiyle |
| Audit’te şirket ayrımı | Her olayda hangi `company_id` net |
| Lisans / modül seviyesi | Şirket mi grup mu? → **karar Faz G içinde verilir** |

**Sıra kuralı:** Faz G DoD karşılanmadan Faz 6 modül dalgalarına girilmez (W2 iade iskeleti Faz 6 başında; G tamamlanmış olmalı).

---

### FAZ 6 — Modül Derinleştirme + Türkiye Uyumu (8–12 hafta)

**Amaç:** 14’lü ana modül haritasını (`MODUL_SPEC` B1–B14) “satılabilir” kaliteye çekmek. Her modül geçiş paketi: **Form Engine + izin matrisi + dataset + modül panosu (`module_key`) + bildirim olayları + KVKK sınıfı + yardım md + eksikler**.

**Önkoşul:** Faz G DoD ✅.

**Departman erişimi (Faz 4B bağlama):** DataScope `department` + rol ile dept yöneticisi kendi kapsamında CV/ilan/personel/PDKS görür. Zincir motoru 4B; ekran bağlama bu fazda.

#### Faz 6 önerilen sıra (bağımlılık)

| # | Odak | Gerekçe |
|---|------|---------|
| **W2** | **İade (`returned`) akışı** — Faz 6 **başı** | Onaycı “iade et” → talep sahibine (gerekçe zorunlu) → düzeltip yeniden gönder → akış kaldığı adımdan devam. `AKIS_SPEC` §0 durum makinesi. `approval.returned` bildirim tetikleyici burada bağlanır. |
| **0** | **PORTAL-2a — Portal tasarım sistemi** (Liquid Glass temel malzeme + token; `TASARIM_REHBERI` §10) | PDKS, LMS, İSG portal ekranları getirecek; sistem önce kurulmazsa o ekranlar **iki kez** yapılır. Company etkilenmez. |
| 1 | **Navigasyon + B1 Organizasyon** | Menü/rail 14’lü yapıya hizalanır; şube/dept/pozisyon/norm kadro omurgası |
| 2 | **B4 PDKS** | Günlük operasyon + puantaj kartı + vardiya/mesai; bordro aktarım paketinin üreticisi |
| 3 | **B3 İzin derinleştirme** | Onaylı izin → puantaja otomatik akış; TR hakediş UI + tatil API borçları |
| 4 | **B5 Ücret & Ödemeler** | Masraf birleşimi, avans-borç, harcırah; aktarım paketi tüketicisi |
| 5 | **B9 Eğitim (LMS)** | İçerik/atama/portal öğrenme; İSG eğitim köprüsünün önkoşulu |
| 6 | **B10 İSG** | Mevzuat parametreleri lookup’ta; LMS + personel + org sonrası |
| 7 | **Kalanlar** | B2 (disiplin/vekalet), B6 İşe Alım, B7 Oryantasyon & Çıkış, B8 Performans (+kariyer/yedekleme/kalibrasyon), B11–B14, A4 SLA, A7 KVKK — pilot’a göre |
| **8** | **PORTAL-2b** (hareket/derinlik) → **PORTAL-3** (kalan sayfalar + Bootstrap kaldırma) → **PORTAL-4 PWA** | Modül portal yüzleri oturduktan sonra; PWA mağazasız, düşük maliyet — Capacitor’dan önce |

#### 6A. Pilot çekirdeği (sıra 0–4 ile hizalı)
- [ ] **PORTAL-2a:** Liquid Glass temel malzeme (alt çubuk, başlık, sheet) + token; SF yasak / ikon seti kararı; içerik opak + WCAG AA (`PORTAL_RAPOR`, `TASARIM_REHBERI` §10)
- [ ] **B1 Organizasyon:** şube/dept/pozisyon + şema + norm kadro + kadro talebi (workflow); Ayarlar kısayolları buraya
- [ ] **B2 Personel (pilot dilim):** TR alan seti; çıkış sihirbazi; 🆕 disiplin & ödül + vekalet (tamamı 6B’de tamamlanabilir)
- [ ] **B4 PDKS:** günlük takip, puantaj kartı (dönem×dept/şube), onay zinciri (puantör→gözetmen→İK), kilit, vardiya/rotasyon/yasal kontrol, mesai, kurallar, QR/cihaz kaydı, manuel audit düzeltme, ziyaretçi, aktarım paketi (generic); canlı cihaz → Faz 8
- [ ] **B3 İzin:** İş Kanunu hakediş (14/20/26 + yaş); yasal tür seed; tatil; accrual; **onaylı izin → puantaj yansıması**
  - [ ] **A1 BORÇ:** Hakediş kuralları yönetim UI (seed var, UI yok)
  - [ ] **A1 BORÇ:** Dini bayram tarihleri API’den (kod sabiti kalkar)
- [ ] **B5 Ücret & Ödemeler:** ücret bantları/geçmiş/zam + masraf company UI/limit + 🆕 avans-borç (taksit/faiz/icra/öncelik) + harcırah
- [ ] **B13 Doküman+:** zorunlu set + süre takibi + versiyonlama polish
- [x] **A3 holding** → **Faz G’ye taşındı** (SEÇENEK 1 onaylı; park iptal)

#### 6B. İkinci halka (sıra 5–8)
- [ ] **B9 Eğitim (LMS):** katalog/kurs/video (yükleme+YouTube/Vimeo, indirme yok)/soru bankası/sertifika; öğrenme yolu; portal oynatıcı; ölçme; eğitmen & maliyet. SCORM → Faz 8
- [ ] **B10 İSG:** yapılandırma (NACE/tehlike/ekip/takvim), risk, olay/DÖF, sağlık gözetimi (özel nitelikli), LMS köprüsü, KKD/denetim, kurul, taşeron, İBYS hazırlık. **Mevzuat parametreleri kodda değil — ayar/lookup**
- [ ] **B8 Performans:** periods/criteria route; OKR/360; 🆕 kariyer yolları + yedekleme + kalibrasyon
- [ ] **B6 İşe Alım:** kariyer polish; Kanban DnD + kişiselleştirme; teklif UI
- [ ] **B7 Oryantasyon & Çıkış:** şablonlar; preboarding; buddy; çıkış checklist ortak motor
- [ ] **B11 Varlık:** categories/assignments; zimmet PDF. Araç & filo → Faz 8
- [ ] **B12 Anket & eNPS:** anonimlik garantisi; eNPS trend
- [ ] **B14 Analitik:** Faz 5 motorunu modül panoları + lisansla paketleme
- [ ] **A4 Talep/Vaka:** SLA + kategori atama + helpdesk görünümü
- [ ] **PORTAL-2b:** kaydırmaya duyarlı saydamlık, geçişler, renk emme, katmanlı derinlik (`prefers-reduced-*`)
- [ ] **PORTAL-3:** kalan portal sayfaları + Bootstrap kaldırma (shared design system)
- [ ] **PORTAL-4 — PWA:** kurulabilir web uygulaması (mağaza yok, düşük maliyet); Capacitor’dan hemen önce

#### 6C. KVKK (çekirdek — satılmaz)
- [x] **D2a** Aydınlatma versiyonlama + portal rıza + veri envanteri
- [x] **D2b** Veri sahibi talepleri + kişisel veri ihracı (`docs/KVKK_RAPOR.md`)
- [x] **D2c** Silme/anonimleştirme (destruction_pending)
- [ ] Saklama politikaları job'ları
- [ ] Modül bazlı KVKK sınıfı enforcement turu — özellikle İSG sağlık, PDKS biyometri/konum

**DoD (modül başına):** Form Engine + izin matrisi + dataset + `module_key` pano + bildirim olayları + KVKK sınıfı + `docs/help/{modul}/…` içerik; feature testleri yeşil; lisans aç/kapa.

---

### FAZ 7 — On-Prem Paketleme + Lisans v1 + GA Hazırlığı (3–4 hafta)

> **Sıra:** Modül derinleştirme (Faz 6) **sonra**, canlıya çıkış **önce**. On-prem pilot kurulumu bu fazın DoD’sine bağlıdır.

- [ ] `APP_MODE=standalone`: SuperAdmin gizli, tek firma/organization otomatik, kayıt kapalı; kod içinde if/else minimum (config + service provider seviyesinde)
- [ ] On-prem dağıtım paketi: versiyonlu Docker imajları + docker-compose.prod.yml + `install.sh` (env üretimi, key generate, migrate, seed, ilk admin)
- [ ] **Lisans v1:** ed25519 imzalı lisans dosyası (firma/grup, modül listesi, kullanıcı limiti, bitiş tarihi) — offline doğrulama; uygulama açılışta + günlük kontrol. *(Gelişmiş/özel lisanslama mekanizması ayrı ve gizli bir iş kalemi olarak bu fazdan sonra ele alınacak — bu belgede detaylandırılmaz.)*
- [ ] ⚠️ **Not:** On-prem’de sunucu müşterinindir; SuperAdmin panelini gizlemek **koruma değildir**. Gerçek koruma **imzalı lisans + sözleşme**dir (Faz 7/8 konusu).
- [ ] Güncelleme mekanizması: `update.sh` (imaj çek → maintenance → migrate → up); sürüm notları düzeni
- [ ] Yedekleme/geri yükleme aracı: pg_dump + storage arşivi, cron'lu; restore prosedürü dokümante
- [ ] Sağlık/izleme: `/up` genişletilir (db, redis, queue, storage, lisans durumu); on-prem admin'e sistem durumu sayfası
- [ ] Production sertleştirme: Telescope prod'da kapalı, debug kapalı, log rotasyonu, dosya upload limitleri, virüs tarama hook'u (opsiyonel)
- [ ] Cloud tarafı: staging ortamı, otomatik deploy, uptime izleme
- [ ] Dokümantasyon: kurulum kılavuzu (on-prem), admin el kitabı, API dokümantasyonu (mevcut api_keys/webhooks müşterileri için)

**DoD:** İnternetsiz bir Ubuntu sunucuya paket + lisans dosyası ile 30 dakikada kurulum; sürüm güncellemesi veri kaybısız; aynı kod cloud'da multi-tenant çalışmaya devam eder. → **GA v1.0**

---

### FAZ 8 — Sonraki Ufuk (GA sonrası; yardım motoru EN SONDA)

- [ ] **D4b Yardım motoru** — `docs/help/{modul}/{sayfa}.md` dosyalarını render eder (içerik Faz 6 dalgalarında yazılmış olmalı). UI: bağlamsal yardım paneli.
- [ ] **Mobil (Capacitor) paketleme + mağaza yayını** (`MODUL_SPEC` §D2): tek müşteri build (sunucu adresi config’te; seçim ekranı yok; çok kiracılı mimari korunur) · APK/AAB + iOS (macOS/Xcode) · push (FCM/APNs) · biyometrik giriş · QR/kamera · çevrimdışı · dosya paylaşımı · derin bağlantı — mağaza incelemesi için native yetenekler zorunlu. *(Web/PWA ve Bootstrap kaldırma PORTAL-2…4 / Faz 6’da biter.)*
- [ ] **AI katmanı:** doğal dille rapor, CV ayrıştırma, anket özet, İK asistanı
- [ ] **Bordro modülü:** B5 Ücret & Ödemeler + aktarım paketi üzerine; SGK/e-Bildirge — ayrı büyük proje
- [ ] **Entegrasyon pazarı:** Logo/Mikro/Netsis **adaptörleri** (standart paket v1’de tasarlanır — `MODUL_SPEC` §F); takvim; SSO; canlı PDKS cihaz; İBYS
- [ ] **Backlog:** Yemekhane/Kantin · Bütçe Simülasyonu · Sendika · Araç & Filo · SCORM · MDM / müşteriye özel build
- [ ] Toplu i18n + EN → global açılım
- [ ] PostgreSQL RLS, audit partisyon, read replica

---

## 6. Modül Envanteri ve Satış Paketleri

> Kaynak: `docs/MODUL_SPEC.md` (14’lü yapı). **Karar (DOK-3):** Her modül **ayrı satılabilir** (à la carte); SuperAdmin’den açılır/kapanır. Mevcut `modules` + `company_modules` (+ `license_packages` paket önerisi) korunur. İSG / PDKS / LMS dahil **tüm** operasyonel modüller tekil aç/kapa.

### 6.1 Çekirdek platform (her lisansta, ayrıca satılmaz — kapatılamaz)

| Bileşen | Not |
|---------|-----|
| Kullanıcı & Rol (RBAC v2) | Spatie; data scope; alan izni |
| Self-Servis Portal | Personel yüzü |
| Duyurular | İç iletişim |
| Talep & Vaka (temel) | SLA polish satılabilir genişleme olabilir |
| Bildirim Merkezi | Şablon + kanal |
| Audit & Log | Yazma izlenebilirliği |
| KVKK araçları | Yasal zorunluluk — kapatılamaz |
| Temel hazır raporlar | İlgili açık modülün dataset’inden; builder ayrı |
| Form / Workflow / Liste motorları | Platform; “gelişmiş tasarımcı” premium’da |

**Not:** Organizasyon ve Personel/Özlük artık **ana operasyonel modül** (B1/B2) olarak sayılır; Starter’da varsayılan açık gelir, kapatılmaları edge-case’tir.

### 6.2 Ana operasyonel modüller (14 — tekil aç/kapa)

| # | Modül | Eski karşılık / not |
|---|--------|---------------------|
| 1 | Organizasyon | Yönetim’den taşındı; norm kadro + kadro talebi |
| 2 | Personel / Özlük | + Disiplin & Ödül, Vekalet |
| 3 | İzin Yönetimi | PDKS’e onaylı izin akışı |
| 4 | PDKS | Eski puantaj/vardiya + QR/ziyaretçi/aktarım |
| 5 | Ücret & Ödemeler | Masraf + ücret + avans-borç + harcırah |
| 6 | İşe Alım | — |
| 7 | Oryantasyon & Çıkış | Eski onboarding/offboarding |
| 8 | Performans | + Kariyer / yedekleme / kalibrasyon |
| 9 | Eğitim (LMS) | Video gömme; SCORM → Faz 8 |
| 10 | İSG | Yeni; mevzuat parametreleri lookup’ta |
| 11 | Varlık / Zimmet | Filo → Faz 8 |
| 12 | Anket & eNPS | — |
| 13 | Doküman+ | Gelişmiş evrak |
| 14 | Analitik | Panolar + rapor motoru + ölçü kütüphanesi |

**Ertelenen (Faz 8 / backlog — ana modül değil):** Yemekhane/Kantin · Bütçe Simülasyonu · Sendika · Araç & Filo.

### 6.3 Premium eklentiler

| Eklenti | Açıklama |
|---------|----------|
| Gelişmiş Workflow Otomasyonu | Tetikleyici + aksiyon kataloğu + stüdyo tasarımcısı (temel onay zincirleri modülde kalır) |
| API & Webhook | Anahtar kapsamı + dış olay |
| On-prem seçeneği | İmzalı lisans dosyası (Faz 7) |

Analitik (B14) Professional’da “hazır pano + sınırlı builder”, Enterprise’da tam self-servis BI olarak paketlenir (aşağıdaki tablo).

### 6.4 Lisans paket önerisi

| Paket | Dahil ana modüller | Premium | Tipik müşteri |
|-------|-------------------|---------|---------------|
| **Starter** | Çekirdek + **Organizasyon + Personel + İzin + Doküman+ (temel kullanım)** | — | Küçük ofis; özlük + izin |
| **Professional** | Starter + **PDKS + Ücret & Ödemeler + İşe Alım + Oryantasyon & Çıkış + Performans + Eğitim (LMS)** + Analitik (hazır panolar + kopyalanabilir raporlar) | Temel workflow zincir editörü | KOBİ / ölçeklenen İK |
| **Enterprise** | **14’ün tamamı** (+ İSG + Varlık + Anket + Analitik tam builder/ölçü/zamanlama) | Gelişmiş Workflow + API & Webhook + on-prem seçeneği + aktarım adaptörleri (Faz 8) | Kurumsal / fabrika / holding adayı |

**Tekil / à la carte:** SuperAdmin (veya imzalı lisans dosyası) her modülü bağımsız aç/kapa — İSG, PDKS, LMS dahil. Paket tablosu (Starter/Pro/Enterprise) yalnızca **önerilen demet**; zorunlu kilit değildir. Genel sürüm / pilot hedefi: 14’ü de açık gelebilir.

**SuperAdmin:** Müşteri kurulumunda görünmez kalır (davranış değişmez). On-prem’de gizlilik ≠ güvenlik; koruma imzalı lisans + sözleşmedir (Faz 7).

**Aktarım:** Standart bordro aktarım paketi PDKS/Ücret açıkken; Logo/Netsis/Mikro adaptörleri Faz 8.

---

## 7. Kilometre Taşları

| Kapı | Ne zaman | Ne anlama geliyor |
|------|----------|-------------------|
| **M1 — Güvenli Çekirdek** | ✅ Faz 2 sonu (11 Tem 2026) | İzin sistemi gerçek; demo verilebilir |
| **M2 — Platform Tamam** | ✅ Faz 5 sonu (2026-07-29) | Özelleştirme + BI çalışıyor; dogfooding başlar |
| **M2.5 — Grup hazır** | Faz G sonu | Organization + DataScope `group` + izolasyon testleri |
| **M3 — Pilot doğrulama** | Faz 6 + Faz 7 | 14 modül + on-prem kurulum + aktarım paketi (ör. Dobedan) |
| **M4 — GA v1.0** | Faz 7 sonu (cloud paket) | Cloud satış + on-prem teklif |

Sıra özeti: **G1 → Faz 6 (W2→modüller) → Faz 7 → Faz 8**. Bu belge yaşayan bir belgedir.

---

## 8. Riskler ve Çalışma Kuralları

1. **Kapsam şişmesi (en büyük risk):** 15 modül + platform, solo için deniz. Panzehir: İlke #6 (derinlik > genişlik) ve fazların sırasına sadakat. Yeni fikirler bu dosyanın sonundaki Backlog'a yazılır, araya alınmaz.
2. **AI kod tutarsızlığı:** Cursor uzun projede desen kaybeder. Panzehir: repo köküne `.cursorrules` (ekli dosya) + her faz sonunda "tutarlılık turu" (isimlendirme, ölü kod, konvansiyon taraması).
3. **Test borcu:** DoD'lerde test şartı pazarlıksızdır; özellikle Faz 2 izin matrisi testleri platformun sigortasıdır.
4. **Big-bang refactor tuzağı:** Form Engine ve rapor motoruna geçiş **modül modül** yapılır; eski ekran, yenisi kanıtlanana kadar silinmez.
5. **On-prem destek yükü:** Standart paket dışı kuruluma hayır (yalnızca Docker Compose, yalnızca PostgreSQL). Müşteri özelleştirmesi kod değil, Ayarlar Stüdyosu ile.

---

## 9. Backlog (araya alınmaz, buraya yazılır)

- PostgreSQL Row-Level Security · SSO/SAML · PDKS cihaz canlı entegrasyonu · e-imza · Vardiya AI planlama · Yemekhane/Kantin · Bütçe Simülasyonu · Sendika · Araç & Filo · SCORM · Mobil push kampanyaları · Marketplace · Beyaz etiket

---

*Faz 0–3 ve Faz 5 kapandı; Faz 4 kısmen. Sıradaki mimari: **Faz G**. Sonra Faz 6 (W2 iade → 14 modül) → Faz 7 (on-prem/lisans) → Faz 8 (yardım motoru + ufuk). Branch: `faz4-form-engine`. DOK-3: müşteri bağlamı + holding SEÇENEK 1.*
