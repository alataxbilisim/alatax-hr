# ADIM 03B — Frontend Test Altyapısı, Uçtan Uca (E2E) Testler ve CI

**Ön koşul:** `00_ORTAK_KURALLAR.md` okunmuş olmalı. `ADIM_03A_RAPOR.md` tüm kabul kriterleri ✅ olmalı. Bu adım Bölüm 0'daki güvenlik düzeltmeleriyle başlar.
**Dal:** `altyapi`. Görev dosyasını adımın başında commit et.
**Amaç:** Bugün frontend'de **sıfır** test var. Üç uygulama ve shared paket için birim/bileşen test altyapısını kurmak, kritik kullanıcı akışlarını gerçek tarayıcıda doğrulayan E2E katmanını eklemek ve CI'ı tüm bu katmanları her push'ta çalıştıracak hale getirmek.
**Bu adımda YAPILMAYACAKLAR:** UI yeniden tasarımı, React Query / react-hook-form geçişi (Adım 04 frontend mimarisi), bağımlılık güvenlik yükseltmeleri (Adım 04).

---

## Bölüm 0 — 03A'dan devreden acil düzeltmeler (önce bunlar, her biri ayrı commit + regresyon testi)

| # | Sorun | Beklenen çözüm | Test |
|---|---|---|---|
| S1 | **Hassas dosyalar `public` diskte** (KVKK): şirket belgeleri (`Documents/DocumentController`), izin belgeleri/sağlık raporları (`Portal/PortalLeaveController`), CV ve benzeri yüklemeler. `storage:link` kurulu olduğundan URL'yi bilen herkes erişebilir. | Kişisel/hassas veri içeren **tüm** yüklemeler `private` diske. İndirme yalnız yetki kontrollü controller ucu üzerinden (gerekirse süreli imzalı URL). `public` diskte yalnız gerçekten herkese açık varlıklar kalır (şirket logosu, avatar); bu izin listesini mimari teste bağla (`public` diske yazan kod yalnız listedeki yerlerden olabilir). Okuma tarafı geriye dönük: dosya eski kayıtta `public`teyse oradan da okunabilsin. Mevcut dosyaları taşıyan idempotent komut yaz (`storage:move-sensitive-to-private --dry-run`); canlıda önce dry-run çıktısını rapora koy, sonra çalıştır. | Hassas yükleme sonrası dosya `public` diskte yok; `/storage/...` URL'si 404; yetkili kullanıcı indirir, yetkisiz/başka şirket 403/404. |
| S2 | **Vekalette şirket kontrolü yok:** `Workflow/ApprovalController` ~177 `delegate_id => exists:users,id` | Vekil yalnız aynı şirkette (CompanyContext) aktif üyeliği olan kullanıcı olabilir. Onay yetkisi çözülürken de vekilin şirket üyeliği yeniden doğrulanır. Mevcut verilerde başka şirkete işaret eden vekalet var mı tara ve raporla. Aynı deseni (`exists:users,id`, `exists:employees,id` vb. tenant'sız `exists` kuralları) **tüm** validasyonlarda ara, düzelt; mimari test ekle (tenant tablolarına `exists` kuralı şirket kapsamı olmadan yazılamaz). | Başka şirket kullanıcısını vekil atama → 422; vekil başka şirkete taşınırsa onay veremez. |
| S3 | **Yanlış argümanlı hata çağrıları (33 yer):** kullanıcı 400/403/404 yerine 500 görüyor, çoğu portalda. | Hepsini düzelt. `ApiResponse::error` imzasını tip güvenli hale getir ki yanlış kullanım PHPStan'de yakalansın. | Her düzeltilen uç için doğru durum kodu testi (gruplu olabilir). |
| S4 | Adı boş 6 eski demo personel | Kullanıcı onayladı: canlı veride doldur (idempotent komut veya düzeltilmiş seeder mantığıyla). Öncesi/sonrası rapora. | — |

**Kapsam alt sınırı (kullanıcı onayladı):** Backend satır kapsamı %58,32 taban çizgisinin altına düşemez. Bu bir "mandal"dır: CI'da ölçülen değer tabandan düşükse job kırılır; değer yükselirse taban `docs/operasyon/TEST.md` ve CI yapılandırmasında yeni değere çekilir (elle, ayrı commit). Frontend için aynı mekanizmayı bu adımda ölçülen ilk değerle kur.

## Bölüm A — Araç sürümlerinin sabitlenmesi
1. Denetimde tespit edilen uyumsuzluk: CI `pnpm 10 / Node 20`, geliştirme makinesi `pnpm 9.15.9 / Node 22`. Tek bir sürüm seç (Node LTS + pnpm sürümü). Seçimi `package.json` → `packageManager` ve `engines`, `.nvmrc` / `.node-version` ve CI'da sabitle.
2. `frontend/package-lock.json` (pnpm reposundaki npm kilidi) ve git'te izlenen `vite.config.js` / `vite.config.d.ts` derleme çıktılarını kaldır, `.gitignore`'a ekle.
3. `pnpm install --frozen-lockfile` temiz geçmeli.

## Bölüm B — Birim ve bileşen testleri (Vitest)
1. **Kurulum:** Vitest + `@testing-library/react` + `@testing-library/user-event` + `@testing-library/jest-dom` + jsdom + **MSW** (API taklidi). Kök seviyede workspace yapılandırması; her uygulama ve `packages/shared` kendi test komutuna sahip. `pnpm test` hepsini çalıştırır, `pnpm test:coverage` kapsam üretir.
2. **Test yardımcıları** (`packages/shared/src/test/`): Redux store, Router, i18n (TR çeviriler yüklü) ve tema sağlayıcılarıyla `renderWithProviders`; MSW handler fabrikaları (`ApiResponse` zarfı `{success, message, data, errors, timestamp}` ile); oturum açmış kullanıcı/rol/izin fixture'ları; şirket ve şube bağlamı.
3. **İlk test seti (zorunlu):**
   - `services/api.ts`: token başlığı, `X-Company-Id` / `X-Branch-Id` başlıkları, 401'de oturum kapatma/yönlendirme, 403 ve 422 hata eşlemesi, 2FA challenge akışı.
   - Auth slice ve izin yardımcıları (`hasPermission`, modül erişimi, wildcard izinler).
   - Ortak bileşenler: `Select` (lookup), `DataTable` (sayfalama, sıralama, boş durum), form alanları, `CustomFieldRenderer`, Form Engine çekirdeği.
   - **Route duman testi:** Üç uygulamanın `App.tsx` içindeki **her** route için: MSW ile boş ama geçerli API yanıtlarıyla sayfa çökmeden render oluyor, konsolda hata yok. Yetkisiz kullanıcıyla korumalı route'a gidince doğru yönlendirme oluyor. Bu test, route listesini kodu okuyarak üretmeli; yeni route eklendiğinde otomatik kapsasın.
   - **Menü–route tutarlılığı:** Sidebar/navigasyon tanımlarındaki her bağlantının bir route'u var; route'suz menü bağlantısı yok.
4. **Sözleşme testi (backend ↔ frontend):** `api.ts` içindeki her (method, path) çağrısının backend `route:list` çıktısında karşılığı var mı kontrol eden bir betik/test (`scripts/check-api-contract.mjs`). Backend route listesi CI'da JSON olarak üretilip bu betiğe verilsin. Eşleşmeyen çağrı → hata. (Denetimde 15 eşleşmeyen çağrı bulunmuştu; Adım 03A sonrası sıfır olmalı.)

## Bölüm C — Uçtan uca testler (Playwright)
1. `e2e/` klasörü (kök seviyede, ayrı pnpm paketi). Mevcut `scripts/gorsel-kontrol` Playwright kurulumunu incele; uygunsa birleştir, değilse gerekçesini raporla.
2. **Ayrı veritabanı:** E2E testleri canlı geliştirme DB'sine (`dobedan_hr`) **asla** dokunmaz. `dobedan_hr_e2e` veritabanı + deterministik `E2eSeeder` (sabit şirketler, roller, kullanıcılar, personeller, izin türleri; şifreler test ortamına özgü). Testlerden önce `migrate:fresh --seed --seeder=E2eSeeder` yalnız bu DB'ye karşı çalışır; DB adı `_e2e` ile bitmiyorsa komut reddeder.
3. **İlk senaryolar (zorunlu):**
   - Üç uygulamada giriş ve çıkış; yanlış şifre; 2FA'lı kullanıcı girişi.
   - Company: personel oluştur → listede gör → düzenle.
   - İzin akışı uçtan uca: personel portaldan izin talep eder → yönetici company panelinden onaylar → bakiye düşer → portalda durum "onaylandı".
   - Tenant izolasyonu: A şirketi kullanıcısı B şirketinin personel URL'sine doğrudan gidince erişemez.
   - Süper admin: şirket listesi ve modül aç/kapa → company panelinde menü değişir.
   - Belge yükle/indir (Adım 03A K2 düzeltmesinin tarayıcıdan doğrulaması).
4. Hata durumunda ekran görüntüsü, video ve trace saklansın (`e2e/test-results/`, git dışı).
5. Komutlar: `pnpm e2e` (Docker yığını ayaktayken), `pnpm e2e:ui`.

## Bölüm D — CI (GitHub Actions)
Mevcut `.github/workflows/ci.yml`'i yeniden düzenle. İş paralel job'lara bölünsün:
1. **backend-quality:** Pint `--test`, PHPStan (baseline; baseline büyürse kırılır), mimari testler, `composer audit` (rapor; şimdilik kırmasın).
2. **backend-tests:** PostgreSQL 16 servis container'ı, paralel PHPUnit, Clover kapsam çıktısı artifact olarak, kapsam mandalı kontrolü.
3. **frontend-quality:** `pnpm install --frozen-lockfile`, lint, `tsc --noEmit` (3 uygulama + shared), `check:brand`, API sözleşme kontrolü.
4. **frontend-tests:** Vitest + kapsam artifact.
5. **frontend-build:** 3 uygulamanın production build'i; bundle boyutlarını özet olarak yazdır.
6. **e2e:** Docker Compose ile tam yığın + `dobedan_hr_e2e` + Playwright. Şimdilik `main`e PR'da ve elle tetiklemede (`workflow_dispatch`) çalışsın; her push'ta zorunlu değil. Başarısızlıkta trace artifact.
7. Cache'ler (composer, pnpm store, Playwright tarayıcıları), `concurrency` iptali korunur.
8. CI'ı yerelde doğrulayamıyorsan YAML'ı `actionlint` ile kontrol et ve raporda belirt. Push olmadan CI koşmayacağı için "push sonrası ilk koşuda doğrulanacak" maddesini kullanıcıya bırak.

## Bölüm E — Dokümantasyon ve kurallar
1. `docs/operasyon/TEST.md`: frontend testleri, MSW kalıpları, route duman testi, sözleşme kontrolü, E2E kurulumu ve çalıştırma, CI job'larının açıklaması.
2. `.cursorrules` DoD'a ekle: yeni sayfa → route duman testinde otomatik kapsanır, ek olarak en az bir davranış testi; yeni API çağrısı → sözleşme kontrolünden geçer; kritik iş akışı → E2E senaryosu.
3. `DURUM.md`: frontend test sayıları, kapsam taban çizgisi, E2E senaryo listesi.

## Doğrulama
1. `pnpm test` ve `pnpm test:coverage` yeşil. Test sayısı ve kapsam rapora.
2. Route duman testi üç uygulamadaki tüm route'ları kapsıyor (sayıyı route sayısıyla karşılaştır).
3. `node scripts/check-api-contract.mjs` exit 0.
4. `pnpm e2e` tüm senaryolar yeşil. Ardından canlı `dobedan_hr` satır sayılarının değişmediğini kanıtla.
5. `pnpm lint`, `pnpm build`, `pnpm check:brand` exit 0.
6. Backend suite hâlâ yeşil.

## Kabul kriterleri
- [ ] S1–S4 kapandı, regresyon testleri ve mimari korumalar var; hassas dosya `public` diskte kalmadı.
- [ ] Backend ve frontend kapsam mandalı CI'da aktif.
- [ ] Node/pnpm sürümleri tek ve sabit; npm kilidi ve derleme çıktıları depodan çıktı.
- [ ] Vitest + RTL + MSW kurulu; zorunlu ilk test seti yazıldı ve yeşil.
- [ ] Route duman testi tüm route'ları otomatik kapsıyor; menü–route tutarlılık testi var.
- [ ] API sözleşme kontrolü var ve sıfır eşleşmeyen çağrı.
- [ ] Playwright E2E ayrı DB ile çalışıyor; zorunlu senaryolar yeşil; canlı DB etkilenmedi.
- [ ] CI job'ları yeniden düzenlendi ve `actionlint` temiz.
- [ ] `TEST.md`, `.cursorrules`, `DURUM.md` güncel.
- [ ] Rapor: `docs/cursor-gorevleri/raporlar/ADIM_03B_RAPOR.md`.

## Kullanıcıya bırakılacaklar
- Push ve ilk CI koşusunun sonucunu kontrol etmek.
