# ADIM 03B — Frontend test altyapısı, uçtan uca testler ve CI — Rapor

**Tarih:** 2026-10-15 · **Dal:** `altyapi` · **Push:** yapılmadı (kullanıcıya bırakıldı)

**Özet:** Adım 03B tamamlandı; tüm kabul kriterleri ✅. Bölüm 0'daki dört acil düzeltme (S1–S4) ayrı commit'lerle kapatıldı; her birinin regresyon testi ve gerektiğinde mimari koruması var. Node 22 LTS ve pnpm 9.15.9 tek sürüm olarak sabitlendi. Vitest + RTL + MSW kuruldu: 17 dosyada 291 frontend testi yeşil, satır kapsamı %39,15 (taban %39,00). Route duman testi üç uygulamadaki 159 route'un hepsini kodu okuyarak kapsıyor. API sözleşme kontrolü 587 frontend çağrısının hepsini 674 backend route'uyla eşleştiriyor; eşleşmeyen çağrı yok. Playwright E2E paketi ayrı `dobedan_hr_e2e` veritabanında çalışıyor: 13 senaryo, son iki tam koşuda 13/13 yeşil. Canlı `dobedan_hr` etkilenmedi. E2E senaryoları üç gerçek hata buldu, üçü de düzeltildi: portal izin bakiyesi, yanlış şifrede geri bildirim yokluğu ve evrak başlığının kaybolması. CI altı paralel işe bölündü; iki kapsam mandalı ve PHPStan baseline koruması eklendi; `actionlint` temiz. Backend: 1003 test, 4419 assertion, 0 deprecation. Backend satır kapsamı %58,32'den %59,80'e çıktı ve taban buna çekildi.

---

## 1. Yapılanlar ve dosyalar

### Bölüm 0 — 03A'dan devreden acil düzeltmeler

| # | Yapılan | Ana dosyalar | Commit |
|---|---|---|---|
| S1 | Kişisel ve hassas veri içeren tüm yüklemeler `private` diske taşındı: şirket belgeleri, izin belgeleri ve sağlık raporları, talep ekleri, masraf fişleri, bordro PDF'leri, CV'ler (panel ve açık başvuru). İndirme yalnız yetki kontrollü controller uçlarından yapılıyor. Okuma geriye dönük: eski kayıttaki dosya `public`te ise oradan da okunur (`SensitiveFileStorage`). Mevcut dosyaları taşıyan idempotent komut eklendi: `storage:move-sensitive-to-private --dry-run`. Mimari test `public` diske yazmayı izin listesiyle sınırlıyor (logo, avatar). KVKK toplayıcıları yeni diski okuyor. Company'de CV indirme artık blob olarak yetkili uçtan yapılıyor. | `Services/Files/SensitiveFileStorage.php`, `Console/Commands/MoveSensitiveFilesToPrivateCommand.php`, 11 controller, `config/filesystems.php`, `routes/api.php`, `CvPoolPage.tsx`, `ApplicationDetailModal.tsx`, `downloadCv.ts` | `f2ed76b` |
| S2 | Şirket kapsamlı iki doğrulama kuralı eklendi: `company_exists:tablo[,kolon][,global]` (`ExistsInCompany`) ve `company_member[:active]` (`CompanyMember`). 36 dosyada 80 tenant'sız `exists` kuralı dönüştürüldü. Vekil artık yalnız aynı şirkette aktif üyeliği olan kullanıcı olabilir. Onay yetkisi çözülürken üyelik yeniden doğrulanıyor (`ApprovalDelegation::findActiveDelegate`, `getPendingApprovalsForUser`). Mimari test: tenant tablolarına şirket kapsamsız `exists` yazılamaz. | `Rules/ExistsInCompany.php`, `Rules/CompanyMember.php`, `AppServiceProvider.php`, `Models/ApprovalDelegation.php`, `Workflow/ApprovalController.php` ve 30+ controller/FormRequest | `a95062c` |
| S3 | 9 dosyada 34 hatalı `error()` çağrısı düzeltildi (33 tanesi `error($m, null, KOD)`, 1 tanesi portal profilde `error($m, [..], 422)`). `ApiResponse::error` imzası tip güvenli hale getirildi (`int<400,599> $code`); PHPStan dört yanlış kullanım biçimini yakaladı. Testler sırasında bulunan ek 500'ler de düzeltildi: portal OKR uçlarında var olmayan kolonlar ve geçersiz durum; `LicensePackage` route parametre adı uyuşmazlığı yüzünden silmenin 200 dönüp hiçbir şeyi silmemesi. | `Traits/ApiResponse.php`, 9 controller, `phpstan-baseline.neon` (−10 girdi), `routes/api.php` | `42b624d` |
| S4 | Demo firmadaki adı boş 6 eski personel (DEM-035..040) idempotent komutla dolduruldu: `demo:fill-employee-names [--dry-run]`. Seeder'ın ad üretimi de düzeltildi. | `Console/Commands/DemoFillEmployeeNamesCommand.php`, `DemoDataSeeder.php` | `079b521` |

**S1 canlı çıktı.** Dry-run: `documents.file_path` tablosunda 3 yol vardı. Taşınacak 0, zaten private 0, diskte yok 3, çakışma 0. Diğer kaynakların hepsi 0, kayıtsız dosya 0. Gerçek çalıştırma aynı sonucu verdi (taşınan 0). Sonuçta `public` diskte yalnız `.gitignore` kaldı.

**S2 canlı tarama.** `approval_delegations` tablosu boş (0 kayıt), sorunlu kayıt 0.

**S4 öncesi ve sonrası.** Öncesinde 6 personelin `full_name` alanı NULL'dı (durum `terminated`, hesap yok). Yedek alındıktan sonra dry-run 6 kayıt listeledi, gerçek çalıştırma 6 kaydı doldurdu (Nilüfer Ergin, Cem Uçar, Şeyma Korkmaz, Arda Sezer, Esra Bilgin, Berkay Tunç). Tekrar çalıştırma 0 kayıt etkiledi. Adı boş personel kalmadı.

### Bölüm A — Araç sürümleri (`74fcdd8`)

- Tek sürüm: **Node 22 LTS + pnpm 9.15.9**. Sabitlendiği yerler: `frontend/package.json` (`packageManager` ve `engines`; Node `>=22.22.2 <23`, jsdom 30'un şartı), kökte `.nvmrc` / `.node-version`, `frontend/.npmrc` (`engine-strict`) ve CI.
- Depodan çıkarılanlar: `frontend/package-lock.json` ve üç uygulamadaki `vite.config.js` / `vite.config.d.ts` derleme çıktıları. Bunlar `.gitignore`'a eklendi. Bayat `vite.config.js` sorunluydu: Vite `.ts` yerine onu okuyordu. `tsconfig.node` derleme çıktısı artık `node_modules/.tmp` altına yazılıyor.
- `pnpm install --frozen-lockfile` temiz geçiyor.

### Bölüm B — Vitest + RTL + MSW (`a108966`, `f7b7ae3`, `78f333e`)

- **Araçlar:** vitest 5.0.4 (+ coverage-v8), jsdom 30.1.3, msw 3.0.3, `@testing-library/react` 16.3.3, `user-event` 14.6.8, `jest-dom` 7.0.1. Kök `vitest.config.ts` dört projeyi (3 uygulama + shared) birlikte çalıştırıyor; her paketin kendi `test` komutu da var.
- **Yardımcılar** (`packages/shared/src/test/`): `renderWithProviders` (Redux, Router, i18n TR, tema), MSW zarf fabrikaları (`ApiResponse` biçiminde), kullanıcı/rol/izin fixture'ları, şirket ve şube bağlamı, eşleşmeyen isteği hata sayan MSW sunucusu (`onUnhandledFrame: 'error'`), konsol hatasını test hatasına çeviren koruma.
- **Zorunlu ilk set:** `api.ts` (token, `X-Company-Id` / `X-Branch-Id`, 401 yönlendirme, 403/422 eşleme, 2FA), auth slice, izin yardımcıları (wildcard, modül), `Select`, `DataTable`, `CustomFieldRenderer`, Form Engine çekirdeği ve bileşeni, route guard'ları.
- **Route duman testi:** Route listesi her uygulamanın `App.tsx` dosyasını okuyarak üretiliyor; ayrıştırıcı, bulduğu route sayısını kaynaktaki `path=` sayısıyla karşılaştırıyor ve bir route'u kaçırırsa test düşüyor. Her route boş ama geçerli MSW yanıtlarıyla çökmeden ve konsol hatası vermeden render ediliyor. Yetkisiz senaryolar: oturumsuz kullanıcı login'e, izinsiz kullanıcı "Erişim Engeli"ne, `must_change_password` olan kullanıcı şifre değiştirme sayfasına, company_admin superadmin login'ine yönleniyor.
- **Menü–route tutarlılığı:** Üç uygulamada her menü bağlantısının bir route'u olduğu doğrulanıyor.
- **Duman testinin bulduğu hata:** `EmployeeReportsPage` iki kardeş modalı aynı `key` ile render ediyordu; düzeltildi.
- **Sözleşme:** `scripts/check-api-contract.mjs` frontend'deki her (method, path) çağrısının `route:list --json` çıktısında karşılığı olup olmadığını kontrol ediyor. Negatif deneme: iki route listeden çıkarılınca 2 eşleşmeyen çağrı raporladı ve exit 1 verdi.

### E2E'nin bulduğu gerçek hatalar (ayrı commit'ler)

| Commit | Hata | Düzeltme ve test |
|---|---|---|
| `552c9fc` | Portal izin talebi `pending_days`'e gün eklemiyordu, ama onay, red ve iptal oradan düşüyordu. Sonuç: onayda `used=2, pending=-2` oluyor ve kalan bakiye değişmiyordu; iptalde pending negatife düşüyordu. | `PortalLeaveController` store'da `addPending` çağrılıyor. Update'te eski günler düşülüp yeni günler ekleniyor. `PortalLeaveApprovalWorkflowQa2Test` dosyasına 3 test eklendi. Canlı DB salt okunarak tarandı: negatif `pending_days` 0 satır, veri düzeltmesi gerekmiyor. |
| `c5b4697` | Yanlış şifrede üç uygulamada da hiçbir geri bildirim yoktu (login sayfasındaki 401 sessizce yutuluyordu). | `api.ts` yalnız `/auth/login` 401'inde backend mesajını, yoksa `auth:login.failed` çevirisini toast olarak gösteriyor. 2FA ucu etkilenmiyor. `api.test.ts` +1 test. |
| `dbce8fa` | `DocumentUpload` başlığı `title` alanıyla gönderiyordu, backend ise `name` bekliyor. Girilen evrak başlığı sessizce kayboluyor, yerine dosya adı yazılıyordu. | FormData alanı `name` yapıldı. E2E evrak senaryosu başlığı doğruluyor. |

### Bölüm C — Playwright E2E (`19a24b1`, `26ee09d`, `ac03a37`)

- **Ayrı DB:** `dobedan_hr_e2e` veritabanı ve `app-e2e` servisi (`docker compose --profile e2e`, port 8100). Bu servis aynı imajı, `APP_ENV=e2e`'yi, dizi önbellek ve oturumu, senkron kuyruğu ve ayrı bir özel disk kökünü kullanıyor.
  - `e2e:reset` komutu `migrate:fresh --seed --seeder=E2eSeeder` çalıştırıyor. DB adı `_e2e` ile bitmiyorsa reddediyor (`E2eDatabaseGuard`). `AppServiceProvider`, `E2eSeeder`'ı hedefleyen herhangi bir artisan komutunu (örneğin elle yazılmış `migrate:fresh --seeder=E2eSeeder`), hedef `_e2e` değilse tablolar düşürülmeden önce `CommandStarting` olayında durduruyor.
  - Postgres init betiği yeni volume'larda e2e DB'sini oluşturuyor; hazırlık betiği DB yoksa kendisi oluşturuyor.
- **`E2eSeeder`:** Deterministik veri: A ve B şirketleri, superadmin, A yöneticisi, A personeli, 2FA'lı A yöneticisi, B yöneticisi, B'nin gizli personeli, yıllık izin türü ve 14 günlük bakiye. Şifre ve TOTP sırrı test ortamına özgüdür ve bu rapora yazılmadı. Testler: `E2eSeederTest`, `E2eDatabaseGuardTest`.
- **Paket:** Kökte `e2e/`, ayrı pnpm paketi. Playwright 1.64.1, Chromium, 1 işçi, tekrar deneme yok (`retries: 0`). Senaryolar veriyi değiştirdiği için tekrar deneme farklı bir durumdan başlardı.
  - `globalSetup` hazırlığı çalıştırıyor: DB + `app-e2e` + önbellek + `e2e:reset`.
  - `webServer` üç SPA'yı 4101/4102/4103 portlarında `VITE_API_URL=http://localhost:8100/api/v1` ile açıyor.
  - Hata halinde ekran görüntüsü, video ve trace `e2e/test-results/` altına yazılıyor (git dışı).
- **Komutlar:** `pnpm e2e` ve `pnpm e2e:ui` (`frontend/` içinde ya da `pnpm --dir e2e ...`).
- **Senaryolar (13 test, 6 dosya):**
  - `auth.spec.ts` (8 test): üç uygulamada giriş ve çıkış, üç uygulamada yanlış şifre, 2FA'lı kullanıcının şifre + TOTP ile girişi, 2FA'da yanlış kod.
  - `employees.spec.ts`: Company'de personel oluştur, listede ara ve gör, düzenle.
  - `leave-flow.spec.ts`: Portalda izin talebi (bakiye 14) → company'de onay → portalda durum "Onaylandı" ve bakiye 12.
  - `tenant-isolation.spec.ts`: A kullanıcısı B'nin personel URL'lerini (`/employees/{id}`, `/{id}/edit`) açamıyor. API 403/404 dönüyor, B'nin verisi sayfada görünmüyor.
  - `superadmin-modules.spec.ts`: Superadmin firma listesinde iki şirketi görüyor. Eğitim modülü A için kapatılınca company menüsünden kayboluyor, açılınca geri geliyor (`finally` ile geri açılır).
  - `documents.spec.ts`: Evrak yükleniyor (başlık doğrulanıyor) ve indirilen dosyanın içeriği yüklenenle bire bir aynı.
- **`scripts/gorsel-kontrol` neden birleştirilmedi:** Canlı demo verisini ve demo şifresini kullanıyor, npm kilidi var (pnpm değil), çıktısını `docs/` altına yazıyor ve E2E'nin gerek duymadığı `sharp` / `otplib` bağımlılıklarına dayanıyor. Ayrı bir görsel kontrol aracı olarak kalıyor; `TEST.md` §15'te belgelendi.
- **Kararlılık düzeltmesi (`ac03a37`):** Son doğrulamada önbelleksiz `app-e2e` her istekte config'i ve ~700 route'u bind mount'tan yeniden yüklüyordu. Eşzamanlı sayfa yüklemelerinde istekler 15 saniyeyi aştı ve personel ile izin senaryoları rastgele düştü: iki koşuda 11/13 ve 12/13. Trace'te `OPTIONS` istekleri 2,7 saniye, `POST /employees` ise 15 saniyeden uzun sürüyordu.
  - Çözüm: config, route ve event önbellekleri container içine (`APP_*_CACHE=/tmp/...`) yönlendirildi ve her hazırlıkta güncel koddan yeniden üretiliyor. Yollar `/tmp` altında değilse hazırlık duruyor; böylece paylaşılan `bootstrap/cache`'e asla yazılmıyor.
  - Etki: 24 eşzamanlı istek 2,5 saniyeden 0,2 saniyeye indi. Ardından iki tam koşu 13/13 yeşil geçti. Geliştirme `app` container'ında config, route ve event önbelleği hâlâ "NOT CACHED".

### Bölüm D — CI (`5a41a14`)

`.github/workflows/ci.yml` yeniden yazıldı. Tetikleyiciler: `main`, `faz*` ve `altyapi` dallarına push, `main`e PR ve `workflow_dispatch`. `concurrency` iptali korundu, izinler `contents: read`.

| İş | İçerik |
|---|---|
| `backend-quality` | PG 16 servisi, migrate, `pint --test`, `composer analyse`, PHPStan baseline koruması (`scripts/check-phpstan-baseline.mjs`; PR'da `origin/<base>`, push'ta `event.before`, baseline büyürse iş kırılır), `composer test:arch`, `composer audit` (rapor, kırmaz). |
| `backend-tests` | `docker run postgres:16 -c max_locks_per_transaction=256`. Servis container'ı komut argümanı alamadığı için bu yol seçildi; paralel testler bu ayarı gerektiriyor. pcov, iki migrate, paralel PHPUnit + Clover, kapsam artefaktı, **backend kapsam mandalı**. |
| `frontend-quality` | mojibake, `check:brand`, mandal tabanlarının `ci.yml` ile `TEST.md` arasında senkron kontrolü, portal route duman betiği, `pnpm install --frozen-lockfile`, lint, `typecheck` (3 uygulama + shared, `tsc -b`), backend `route:list --json` + **API sözleşme kontrolü**. |
| `frontend-tests` | `pnpm test:coverage`, kapsam artefaktı, **frontend kapsam mandalı**. |
| `frontend-build` | Üç uygulamanın production build'i; bundle boyut özeti (`scripts/bundle-size-summary.mjs`) adım özetine yazılıyor. |
| `e2e` | Yalnız `main`e PR'da ve `workflow_dispatch` ile çalışıyor. `.env` örnekten kopyalanıp rastgele `APP_KEY` üretiliyor, compose `postgres` + `app-e2e`, Chromium, `pnpm --dir frontend e2e`. Hata halinde `app-e2e` logları ve `e2e-results` artefaktı (trace, video, rapor) toplanıyor. |

- **Cache'ler:** composer, pnpm store, Playwright tarayıcıları.
- **Kapsam mandalı** (`scripts/check-coverage.mjs`): Tabanlar `ci.yml` env değişkenlerinde (`BACKEND_COVERAGE_FLOOR`: önce 58.32, adım sonu ölçümüyle 59.80; `FRONTEND_COVERAGE_FLOOR=39.00`) ve `TEST.md` işaretlerinde tutuluyor; `sync` alt komutu ikisinin eşitliğini denetliyor. Ölçüm tabanın altındaysa iş kırılıyor. Tabanın belirgin üstündeyse betik, tabanın ayrı bir commit'le yükseltilmesini hatırlatıyor.
- `scripts/check-mojibake.mjs`: arşivdeki kasıtlı örnek dosya atlandı. Bu dosya yüzünden kökten çalıştırılan tarama zaten düşüyordu.

### Bölüm E — Dokümantasyon (`e514feb`, `ac03a37`, `6f8c268`)

- **`docs/operasyon/TEST.md`:**
  - Değişen bölümler: backend sayıları (§1), mandal metni ve backend taban işareti (§5), baseline 891 → 856 ve CI baseline koruması (§6).
  - Yeni bölümler: Frontend/Vitest ve frontend taban işareti (§11), MSW kalıpları (§12), route duman ve menü testleri (§13), API sözleşmesi (§14), E2E kurulumu, komutlar, 13 senaryo, kurallar ve önbellek notu (§15), CI işleri tablosu (§16).
- **`.cursorrules` DoD eklemeleri:**
  - kapsam mandalı
  - yeni sayfa → route duman testi otomatik kapsar, ayrıca en az bir davranış testi ve menü bağlantısı
  - yeni API çağrısı → sözleşme kontrolü
  - kritik iş akışı → E2E senaryosu
  - `pnpm lint/typecheck/test/build/check:brand`
- **`docs/DURUM.md`:**
  - §5a, Adım 03B sonuna göre yeniden yazıldı: backend 1003 test, frontend 291 test, kapsam tabanları, route sayıları, sözleşme, E2E senaryo listesi, CI.
  - B3 ve B13 notları güncellendi.
  - Borç listesinde #3, #10, #28, #29, #33 ve #34 kapandı; #35–#40 yeni.

---

## 2. `git log --oneline`

```
6f8c268 ci: backend kapsam tabani 58.32 -> 59.80
ac03a37 fix(e2e): app-e2e config/route/event onbellegi container icinde
e514feb docs: test belgeleri, DoD ve durum Adim 03B
5a41a14 ci: paralel isler, kapsam mandali, baseline korumasi ve E2E
26ee09d test(e2e): Playwright paketi ve zorunlu senaryolar
19a24b1 test(e2e): izole dobedan_hr_e2e veritabani, E2eSeeder ve DB adi korumasi
dbce8fa fix(documents): evrak yuklemede girilen baslik kaydedilir
c5b4697 fix(frontend): yanlis sifrede giris sayfasi hata mesaji gosterir
552c9fc fix(portal): izin talebinde bekleyen gun bakiyeye islenir
78f333e test(contract): frontend API cagrilari icin backend route sozlesme kontrolu
f7b7ae3 test(frontend): route duman testi ve menu-route tutarliligi
a108966 test(frontend): Vitest + RTL + MSW altyapisi ve ilk birim test seti
74fcdd8 build(frontend): Node 22 LTS + pnpm 9.15.9 sabitlendi, derleme ciktilari depodan cikti
079b521 fix(data): bos demo personel adlarini doldur (S4)
42b624d fix(api): yanlis argumanli error() cagrilari ve tip guvenli imza (S3)
a95062c fix(security): vekalet ve exists kurallarinda sirket kapsami (S2)
f2ed76b fix(security): hassas yuklemeler private diske (S1)
acfe985 docs: Adim 03B gorev dosyasi (Bolum 0 ve kapsam mandali eklendi)
```

(Bu rapor ayrı bir commit olarak eklenir.)

---

## 3. Doğrulama

| Kontrol | Komut | Sonuç |
|---|---|---|
| Frontend testleri | `pnpm --dir frontend test:coverage` | **17 dosya, 291 test yeşil** (~59 sn). Dağılım: shared 77; company 17 birim + 127 duman + 3 menü; portal 43 duman + 4 menü; superadmin 18 duman + 2 menü |
| Frontend kapsam | `node scripts/check-coverage.mjs frontend frontend/coverage/coverage-summary.json` | Satır **%39,15** (5959/15220), taban %39,00 → OK. Duman testi zamanlaması yüzünden koşudan koşuya ±0,05 oynuyor (%39,14–39,19) |
| Route kapsamı | duman testi ve bağımsız `path=` sayımı | company **124** (123 somut + `*`), portal **24** (23 + `*`), superadmin **11** (10 + `*`). Toplam 159; manifest ile bağımsız sayım eşit |
| Portal route betiği | `node scripts/portal-route-smoke.mjs` | OK, 17 rota |
| Menü–route | menü testleri (company 3, portal 4, superadmin 2) | yeşil; route'suz menü bağlantısı yok |
| API sözleşmesi | `node scripts/check-api-contract.mjs <route-json>` | 674 backend route, 587 frontend çağrısı, dinamik 0, **eşleşmeyen 0**, exit 0 |
| E2E | `pnpm --dir frontend e2e` | Düzeltmeden sonra iki ardışık tam koşu: **13 passed (4,5 dk)** ve **13 passed (5,5 dk)**. Düzeltme öncesindeki iki koşu (11/13 ve 12/13) zaman aşımıydı; nedeni ve çözümü §1 Bölüm C'de |
| Canlı DB satır sayısı | `satir_sayilari_oncesi_adim03b.txt` ↔ `satir_sayilari_sonrasi_adim03b.txt` (`docs/backups/`, git dışı) | 155 tablonun 152'si aynı. Farklar: `activity_logs` 503 → 509 (S4'teki 6 personel güncellemesinin audit kaydı, beklenen); `telescope_entries` 118844 → 122234 ve `telescope_entries_tags` 56108 → 56171 (geliştirme ortamının Telescope log'u) |
| E2E sızıntısı | canlı ve e2e DB'de sayım | Canlı `dobedan_hr`: e2e kullanıcı 0, şirket 0, personel 0, negatif `pending_days` 0. `dobedan_hr_e2e`: 5 kullanıcı, 2 şirket, 5 personel |
| Lint | `pnpm --dir frontend lint` | exit 0 |
| Tip kontrolü | `pnpm --dir frontend typecheck` | exit 0 |
| Build | `pnpm --dir frontend build` | exit 0 (superadmin 5,9 sn, portal 15,6 sn, company 27,8 sn) |
| Marka | `pnpm --dir frontend check:brand` | exit 0 |
| Mojibake | `node scripts/check-mojibake.mjs` | exit 0 |
| Backend paralel suite | `docker compose exec app composer test:parallel` | **OK (1003 test, 4419 assertion)**, 0 deprecation, 07:09 (Adım 03A: 968 test) |
| Backend kapsam | `composer test:coverage` + `check-coverage.mjs backend` | OK (1003 test, 4419 assertion), 08:52. Satır **%59,80** (17553/29350), Adım 03A tabanından +1,48 puan. Taban mandal kuralı gereği ayrı commit'le 59,80'e çekildi (`6f8c268`) |
| Mimari testler | `composer test:arch` | 26 test, 166 assertion, yeşil |
| Pint | `vendor/bin/pint --test` | 878 dosya PASS |
| PHPStan | `composer analyse` | `[OK] No errors`, exit 0 |
| Baseline | `node scripts/check-phpstan-baseline.mjs acfe985` | **856** (adım başı 891) → OK. Negatif deneme (+2 girdi) → exit 1 |
| Kapsam mandalı betiği | yapay clover ile | %59,00 → OK (tabanın yükseltilmesini hatırlatıyor); %57,99 → FAIL, exit 1 |
| Taban senkronu | `node scripts/check-coverage.mjs sync` | backend 58,32 ve frontend 39,00; `ci.yml` = `TEST.md` |
| actionlint | `docker run rhysd/actionlint:1.7.12` | exit 0, bulgu yok |

**Bundle boyutları** (`scripts/bundle-size-summary.mjs`):

| Uygulama | JS | JS (gzip) | CSS | CSS (gzip) |
|---|---|---|---|---|
| superadmin | 579,1 KB | 181,8 KB | 52,2 KB | 9,7 KB |
| company | 4811,5 KB | 1400,6 KB | 105,3 KB | 19,1 KB |
| portal | 1485,2 KB | 482,5 KB | 300,8 KB | 43,4 KB |

**Yedekler** (`docs/backups/`, git dışı): `oncesi_adim03b_20261015-1100.dump` (adım başı) ve `oncesi_adim03b_s4_20261015.dump` (S4 canlı veri düzeltmesinden önce). Geri alınamaz hiçbir işlem (DROP, volume silme, canlıda `migrate:fresh`) yapılmadı. `migrate:fresh` yalnız `dobedan_hr_e2e`'ye karşı, koruma altında çalıştı. `APP_KEY` değişmedi.

---

## 4. Kabul kriterleri

| Kriter | Durum | Kanıt |
|---|---|---|
| S1–S4 kapandı, regresyon testleri ve mimari korumalar var; hassas dosya `public` diskte kalmadı | ✅ | S1: `SensitiveFileStorageTest` (6 test, 70 assertion) + `PublicDiskWriteGuardTest`; canlıda `public` diskte yalnız `.gitignore` var. S2: `DelegationTenantTest` (6), `TenantExistsValidationTest` (2) + `TenantExistsRuleGuardTest`. S3: `ErrorResponseStatusTest` (6) + `ApiResponseErrorBaselineGuardTest` + tip güvenli imza. S4: `DemoFillEmployeeNamesCommandTest`; canlıda adı boş personel 0 |
| Backend ve frontend kapsam mandalı CI'da aktif | ✅ | `backend-tests` ve `frontend-tests` işlerinde `check-coverage.mjs`. Tabanlar: backend 59,80 (ölçüm %59,80; ilk taban 58,32), frontend 39,00 (ölçüm %39,15). `sync` OK; yapay clover ile negatif deneme exit 1 |
| Node/pnpm sürümleri tek ve sabit; npm kilidi ve derleme çıktıları depodan çıktı | ✅ | Node 22 + pnpm 9.15.9 (`packageManager`, `engines`, `.nvmrc`, `.node-version`, `engine-strict`, CI); `package-lock.json` ve `vite.config.js/.d.ts` silinip `.gitignore`'a eklendi (`74fcdd8`) |
| Vitest + RTL + MSW kurulu; zorunlu ilk test seti yazıldı ve yeşil | ✅ | 17 dosya, 291 test yeşil. `DataTable` sıralama testi yazılamadı çünkü bileşende sıralama özelliği yok; sayfalama ve boş durum test edildi (bulgu §6) |
| Route duman testi tüm route'ları otomatik kapsıyor; menü–route tutarlılık testi var | ✅ | Liste `App.tsx`'ten üretiliyor ve `path=` sayısıyla çapraz kontrol ediliyor; 124 / 24 / 11 = bağımsız sayım. Üç uygulamada menü testi var |
| API sözleşme kontrolü var ve sıfır eşleşmeyen çağrı | ✅ | 587 çağrı / 674 route / 0 eşleşmeyen / 0 dinamik; CI'da `frontend-quality` işinde |
| Playwright E2E ayrı DB ile çalışıyor; zorunlu senaryolar yeşil; canlı DB etkilenmedi | ✅ | `dobedan_hr_e2e` + `_e2e` adı koruması; 13/13 yeşil (iki ardışık koşu); canlı DB satır karşılaştırması ve e2e kayıt sayımı 0 |
| CI job'ları yeniden düzenlendi ve `actionlint` temiz | ✅ | 6 paralel iş, cache'ler, `concurrency`; actionlint 1.7.12 exit 0. Gerçek koşu push sonrası doğrulanacak (§5) |
| `TEST.md`, `.cursorrules`, `DURUM.md` güncel | ✅ | `e514feb`, `ac03a37` |
| Rapor | ✅ | bu dosya |

---

## 5. Kullanıcının elle yapacakları

1. **Push ve ilk CI koşusu:** `git push origin altyapi`. Push tetikleyicisi beş işi çalıştırır: `backend-quality`, `backend-tests`, `frontend-quality`, `frontend-tests` ve `frontend-build`.
   - İlk koşu PHPStan baseline'ını `event.before` ile karşılaştırır. Dal uzaktaki son hâlinden çok ilerideyse bu beklenen bir durumdur.
   - Clover ve frontend kapsam artefaktlarının oluştuğunu, iki mandalın "OK" yazdığını kontrol edin.
   - Backend tabanı yerel ölçüme eşit (%59,80). CI ortamı ölçümü küçük bir farkla altta bulursa (ör. %59,7x), `BACKEND_COVERAGE_FLOOR` ile `TEST.md` işaretini CI'ın ölçtüğü değere eşitleyen tek satırlık bir commit yeterli.
2. **E2E işi:** Push'ta çalışmaz. GitHub Actions → CI → "Run workflow" (`workflow_dispatch`) ile elle tetikleyin ya da `main`e PR açın. Hata olursa `e2e-results` artefaktındaki trace dosyası `pnpm --dir e2e exec playwright show-trace <trace.zip>` ile açılır.
3. **Branch koruması (isteğe bağlı):** `main` için zorunlu kontroller olarak beş push işini, PR'larda ayrıca `e2e`'yi seçin.
4. **Yerel E2E:** Docker yığını ayaktayken `pnpm --dir frontend e2e`. İlk kurulumda `pnpm --dir e2e install` ve `pnpm --dir e2e exec playwright install chromium` gerekir.

---

## 6. Kapsam dışı bulgular ve açık sorular

**Bulgular** (DURUM.md borç listesine işlendi: #35–#40):

1. **Portal izin bakiyesi (#35):** Portal talebinde bakiye yeterlilik kontrolü yok; company tarafı 422 "Yetersiz izin bakiyesi" veriyor, portal vermiyor. Portal süreyi "2.00 gün" diye ondalıklı gösteriyor (kozmetik).
2. **Firma bazlı modül arayüzü yok (#36):** Superadmin'de firma bazında modül açıp kapatan bir ekran yok. Yalnız global modül sayfası var ve global `modules.is_active` bayrağı firma menüsünü etkilemiyor (`Company::activeModules()` buna bakmıyor). `adminApi` içinde `/admin/companies/{id}/modules` ucu tanımlı ama kullanan ekran yok. E2E senaryosu aç/kapatmayı API ile yapıyor, sonucu UI'da doğruluyor.
3. **Çoklu dosya yükleme (#37):** Evrak yükleme input'u çoklu seçime izin veriyor ama backend tek `file` alıyor; birden fazla dosya seçilirse yalnız biri yükleniyor.
4. **Portalda indirme arayüzü yok (#38):** Portalda izin belgesi, talep eki ve masraf fişi indirme arayüzü yok (S1'de yetkili uçlar hazırlandı). KVKK silme toplayıcıları bu dosyaları silmiyor.
5. **Erişilebilirlik ve tablo (#39):** `CustomFieldRenderer` radio ve checkbox etiketlerinde `htmlFor` yok. `DataTable`'da sıralama özelliği yok.
6. **Login sayfaları (#40):** Login sayfaları Redux'taki `auth.error`'u göstermiyor (geçici çözüm: `api.ts` toast'ı). Company login'de superadmin yönlendirmesi `localhost:3001` olarak sabit yazılmış.
7. **KVKK dışa aktarım birikimi:** `private` disk altında çok sayıda `kvkk-exports` / `kvkk-responses` dosyası birikmiş (büyük ihtimalle test ve deneme kaynaklı). Saklama ve temizlik politikası gerekiyor.
8. **Testler gerçek disklere yazıyor:** Bazı backend testleri `Storage::fake` kullanmadan gerçek disklere yazıyor.
9. **Panel ucu eksik:** Personel talepleri için panel tarafında bir controller yok; talepler yalnız portal ve onay akışından yönetiliyor.
10. **Superadmin Vite uyarısı:** Superadmin'de `@tanstack/react-query` için `optimizeDeps` uyarısı çıkıyor (zararsız).
11. **Unit testlerde olay:** Laravel `CommandStarting` olayı unit testlerde yönlendirilmiyor (bilgi).
12. **Yerel E2E hızı:** Windows bind mount üzerinde `app-e2e` hâlâ CI'dan yavaş; izin akışı senaryosu yaklaşık 1 dakika sürüyor. Önbellek düzeltmesiyle kararlı hale geldi, ama zaman aşımı payı daralırsa ilk bakılacak yer burası.
13. **PowerShell yönlendirmesi:** PowerShell'de `>` UTF-16 yazdığı için yerelde route JSON'u üretirken `Out-File -Encoding utf8` gerekiyor (`TEST.md` §14'te not var; CI'yı etkilemez).

**Açık sorular:**

1. **pnpm 12:** Şu an 9.15.9'a sabitlendi. Yükseltme ayrı bir adımda yapılsın mı?
2. **Firma bazlı modül arayüzü:** Superadmin'e firma bazında modül aç/kapa ekranı eklensin mi? Global `is_active` firma erişimini kesmeli mi? (ürün kararı)
3. **Portal izin bakiyesi:** Portal talebinde bakiye yetersizse company tarafı gibi 422 verilsin mi, yoksa eksi bakiyeye izin veren bir politika mı isteniyor?
