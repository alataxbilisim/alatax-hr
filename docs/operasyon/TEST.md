# Testler — kurulum, komutlar, kurallar

Dobedan HR test altyapısının tek başvuru belgesi: backend (§1–10), frontend (§11–14), uçtan uca E2E (§15) ve CI (§16). Tamamlanma tanımı (DoD) `.cursorrules` içindedir; bu belge nasıl yapılacağını anlatır.

Tüm backend komutları container içinde çalışır:

```bash
docker compose exec app <komut>
```

---

## 1. Suite yapısı

| Suite | Dizin | İçerik |
|---|---|---|
| `Unit` | `tests/Unit` | Saf iş mantığı (hakediş, veri kapsamı, ölçü ifadeleri). |
| `Feature` | `tests/Feature` | HTTP uçları, servisler, factory'ler; gerçek PostgreSQL üzerinde. |
| `Architecture` | `tests/Architecture` | Kalıcı korumalar: route güvenliği, 2FA, tenant, disk adları, kod kuralları, factory kapsamı, marka/DB sürücüsü. |
| `Security` | `tests/Security` | Yetki/izolasyon/2FA/girdi beyaz listesi senaryoları. |

Sayılar (Adım 03B sonu): **1003 test, 4419 assertion**, deprecation 0 (paralel 7:09).

## 2. Test veritabanı

- Test DB adı: `dobedan_hr_testing` (canlı: `dobedan_hr`). `phpunit.xml` ve `tests/bootstrap.php` adı zorlar.
- **Yeni Postgres volume'ü:** `docker/postgres/init/01-test-db.sh` ilk açılışta `${POSTGRES_DB}_testing`'i oluşturur.
- **Mevcut volume:** `composer test:db` (`php artisan db:create-testing`) — yoksa oluşturur, varsa dokunmaz.
- **Canlı DB koruması:** `Tests\TestCase` her testte ve `migrate:fresh` öncesinde bağlı DB adının `_testing` (paralelde `_testing_test_N`) ile bittiğini doğrular; değilse test başlamadan durur. `TestDatabaseSafetyTest` bu kalıbı kilitler.
- Testler `Tests\Concerns\RefreshDatabase` kullanır (Laravel trait'inin advisory-lock sarmalı); `Illuminate\Foundation\Testing\RefreshDatabase` doğrudan kullanılmaz.

## 3. Komutlar

| Komut | Ne yapar |
|---|---|
| `composer test` | Seri tam suite (~24 dk; son ölçüm 23:33, 968 test). |
| `composer test:parallel` | Paralel tam suite (8 süreç, ~7 dk). Günlük kullanım budur. |
| `composer test:arch` | Yalnız `Architecture` suite'i. |
| `composer test:security` | Yalnız `Security` suite'i. |
| `composer test:coverage` | Paralel suite + pcov; HTML, Clover ve metin özeti `storage/coverage/` altına (git dışı). ~15 dk (HTML üretimi ~6 dk). |
| `composer test:db` | Test DB'sini yoksa oluşturur. |
| `composer analyse` | Larastan şema görüntüsünü üretir + PHPStan seviye 5 (baseline ile). |
| `vendor/bin/pint --test` | Kod stili kontrolü (düzeltmek için `vendor/bin/pint`). |
| `php artisan test --filter=Ad` | Tek test/sınıf. |

## 4. Paralel çalıştırma

- `brianium/paratest` ile `php artisan test --parallel`. Her süreç kendi veritabanını kullanır: `dobedan_hr_testing_test_1` … `_test_8` (Laravel ilk koşuda oluşturur, sonraki koşularda yeniden kullanır; şema değiştiyse `--recreate-databases`).
- Ölçüm: seri **21:44** (837 test) → paralel **6:27** (968 test, 8 süreç).
- Postgres `max_locks_per_transaction=256` (`docker-compose.yml`): her süreç `migrate:fresh`'te ~200 tabloyu tek transaction'da düşürür; varsayılan kilit tablosu 8 süreçte "out of shared memory" ile taşar.
- Paralel güvenli test yazımı: sabit DB adı beklemeyin (`/^dobedan_hr_testing(_test_\d+)?$/`), dosya yollarını test başına benzersiz yapın, sıraya bağımlı veri varsaymayın.

## 5. Kapsam (coverage)

- Docker dev imajında `pcov` yüklü ama **varsayılan kapalı** (`pcov.enabled=0`; php-fpm ve normal koşuya maliyeti yok). `composer test:coverage`, `tests/php/pcov.ini`'yi `PHP_INI_SCAN_DIR` ile ekleyip açar; ana süreç ve paralel işçiler bu ayarı devralır.
- Çıktılar: `storage/coverage/html/index.html`, `storage/coverage/clover.xml`, `storage/coverage/summary.txt`.

**Taban çizgisi (Adım 03A, 2026-10-14, `app/`):**

| Ölçüt | Değer |
|---|---|
| Satır | **%58,32** (16.984 / 29.122) |
| Metot | %38,44 (991 / 2.578) |
| Sınıf | %14,25 (63 / 442) |

**Kapsam mandalı (Adım 03B):** CI `backend-tests` işi Clover çıktısını ölçer; satır kapsamı tabanın altındaysa iş kırılır (`node scripts/check-coverage.mjs backend backend/storage/coverage/clover.xml`). Taban **yalnız yukarı** ve **elle, ayrı commit'te** çekilir: hem aşağıdaki işaret hem `.github/workflows/ci.yml` → `BACKEND_COVERAGE_FLOOR` aynı değere güncellenir; `node scripts/check-coverage.mjs sync` ikisinin eşit olduğunu doğrular (CI'da da koşar). Ölçüm tabanı 0,5 puan aşarsa betik yükseltme notu basar.

<!-- coverage-floor:backend=58.32 -->
Backend satır kapsamı tabanı: **%58,32**.

En düşük modüller: Performance %7,1, Admin %11,1, Console %15,6, Assets %16,3, Training %20,3 (modül tablosu: `docs/cursor-gorevleri/raporlar/ADIM_03A_RAPOR.md`).

## 6. Statik analiz

- **Pint:** `vendor/bin/pint --test` exit 0 olmalı. Satır sonu LF'dir (`.gitattributes`); Windows'ta CRLF çalışma kopyası Pint'te `line_ending` olarak görünür, `vendor/bin/pint` düzeltir.
- **Larastan (PHPStan seviye 5):** `phpstan.neon` + `phpstan-baseline.neon` (Adım 03A: 891 hata → Adım 03B: **856 hata**). Baseline mevcut borcu görünür tutar; **büyümez** — yeni hatalar düzeltilir, baseline'a eklenmez. Baseline'dan hata düzeltildiğinde `vendor/bin/phpstan analyse --generate-baseline` ile küçültülür. CI bunu `node scripts/check-phpstan-baseline.mjs <ref>` ile denetler (girdilerin `count` toplamı; PR'da hedef dal, push'ta önceki commit ile karşılaştırılır).
- Larastan yalnız MySQL sözdizimli şema dump'ını okuyabiliyor. `scripts/larastan-schema.php` bağlı veritabanının şemasını salt okunur inceleyip `storage/framework/cache/phpstan-schema/schema.sql` üretir (git dışı); `composer analyse` önce bunu çalıştırır. Migration sonrası şema değiştiyse analiz bu görüntüyle güncel kalır.

## 7. Mimari testler ve izin listeleri

Her mimari test bugünkü bilinçli istisnaları **gerekçeli bir listede** tutar. Liste dışı yeni durum testi kırar; listede olup artık geçerli olmayan kayıt da ("bayat kayıt") testi kırar.

| Test | Kural | İzin listesi |
|---|---|---|
| `RouteSecurityInventoryTest` | Her `api/v1` route `auth:sanctum` ya da public listede; public route'lar throttle'lı; her sanctum route `permission:`/`can:`/`role:` middleware'i, `super_admin` veya `portal.access` kapısından geçer. | `PUBLIC_ROUTES`, `SELF_SERVICE_ROUTES` (anahtar `"METHOD uri"`, değer gerekçe). |
| `TwoFactorRouteGuardTest` | Her sanctum route `deny.2fa.challenge` içerir. | `ALLOWED_WITHOUT_GUARD` (yalnız `POST api/v1/auth/2fa/verify`). |
| `TenantModelGuardTest` | `company_id` kolonlu her tablonun modeli `BelongsToCompany` kullanır. | `MODEL_EXCEPTIONS` (tablo => gerekçe), `TABLES_WITHOUT_MODEL`. |
| `StorageDiskNameGuardTest` | Koddaki her `Storage::disk('x')` / `store*(…, 'x')` diski `config/filesystems.php`'de tanımlı. | — |
| `CodeRulesTest` | `app/` altında `env(` yok; controller'da `DB::statement`/`DB::unprepared` yok; `dd`/`dump`/`var_dump` yok. | — |
| `ModelFactoryCoverageTest` | Her modelin factory'si ve `HasFactory`'si var. | `EXCEPTIONS` (şu an boş). |
| `TestDatabaseSafetyTest` | Test DB ad kalıbı canlı DB'yi reddeder. | — |
| `BrandGuardTest`, `DatabaseDriverGuardTest` | Eski marka adı yok; yalnız PostgreSQL. | (kendi listeleri) |

`Feature/Factories/ModelFactoriesTest` her factory'yi `create()` eder ve doğrudan şirketli ebeveynlerin (users için `home_company_id`) kaydın şirketine ait olduğunu doğrular (`TENANT_CHECK_EXEMPT`: `CompanyUser`).

**Listeye ekleme kuralı:** önce kuralı sağlamayı deneyin (middleware ekleyin, trait kullanın). Olmuyorsa listeye **neden** sorusunu cevaplayan tek satırlık gerekçeyle ekleyin; gerekçesiz ya da "geçici" kayıt eklemeyin (geçiciyse `GEÇİCİ` yazıp `docs/DURUM.md` borç listesine ekleyin).

## 8. Ortak test yardımcıları (`tests/Concerns`)

| Trait | Yardımcılar |
|---|---|
| `CreatesUsersWithPermissions` | `createActiveCompany()`, `ensurePermissions()`, `userWithPermissions($company, [...])`, `companyAdminFor($company, $extra)`, `superAdminUser()` |
| `SetsCompanyContext` | `actingAsInCompany($user, $company)` — Sanctum + `X-Company-Id` |
| `CreatesPortalEmployees` | `portalEmployee($company, ...)` — personel kaydı bağlı portal kullanıcısı |
| `CreatesTwoFactorUsers` | `enableTwoFactor($user)`, `challengeTokenFor($user, $portal)` |
| `CreatesOtherCompanyFixture` | `otherCompany()`, `otherCompanyEmployee()` — tenant izolasyonu senaryoları |

Not: `companyAdminFor`/`userWithPermissions` yalnız var olan izinleri bağlar; test `PermissionSeeder` çalıştırmıyorsa gereken izinleri parametreyle verin. Modül erişimi gereken uçlarda modülü şirkete açın (`Module::firstOrCreate` + `$company->modules()->syncWithoutDetaching(...)`).

## 9. Yeni endpoint / modül için zorunlu test şablonu

Her yeni uç için en az: 401, 403, happy path, başka şirketin kaydı (404), validasyon (422). Örnek gerçek uygulama: `tests/Feature/OnboardingDuplicateAndSkipTest.php`.

```php
namespace Tests\Feature\Ornek;

use App\Models\Company;
use App\Models\OrnekKayit;
use Tests\Concerns\CreatesOtherCompanyFixture;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\Concerns\RefreshDatabase;
use Tests\Concerns\SetsCompanyContext;
use Tests\TestCase;

class OrnekKayitApiTest extends TestCase
{
    use CreatesOtherCompanyFixture;
    use CreatesUsersWithPermissions;
    use RefreshDatabase;
    use SetsCompanyContext;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = $this->createActiveCompany();
    }

    public function test_guest_gets_401(): void
    {
        $this->postJson('/api/v1/ornek-kayitlar', [])->assertUnauthorized();
    }

    public function test_user_without_permission_gets_403(): void
    {
        $this->actingAsInCompany($this->userWithPermissions($this->company, []), $this->company);
        $this->postJson('/api/v1/ornek-kayitlar', ['ad' => 'X'])->assertForbidden();
    }

    public function test_authorized_user_creates_record(): void
    {
        $this->actingAsInCompany($this->userWithPermissions($this->company, ['ornek.kayitlar.create']), $this->company);
        $this->postJson('/api/v1/ornek-kayitlar', ['ad' => 'X'])->assertCreated()->assertJsonPath('success', true);
    }

    public function test_invalid_payload_gets_422(): void
    {
        $this->actingAsInCompany($this->userWithPermissions($this->company, ['ornek.kayitlar.create']), $this->company);
        $this->postJson('/api/v1/ornek-kayitlar', [])->assertUnprocessable()->assertJsonValidationErrors(['ad']);
    }

    public function test_cannot_access_other_company_record(): void
    {
        $foreign = OrnekKayit::factory()->create(['company_id' => $this->otherCompany()->id]);
        $this->actingAsInCompany($this->userWithPermissions($this->company, ['ornek.kayitlar.view']), $this->company);
        $this->getJson("/api/v1/ornek-kayitlar/{$foreign->id}")->assertNotFound();
    }
}
```

Yeni model için: `database/factories/{Model}Factory.php` (tenant-doğru; şirketli ebeveynler `fn (array $attributes) => X::factory()->state(['company_id' => $attributes['company_id']])` deseniyle) + modelde `HasFactory`. `ModelFactoriesTest` yeni factory'yi otomatik dener.

## 10. Bilinen notlar

- `docker compose build app queue scheduler` + `up -d` sonrası nginx eski app IP'sini tutup 502 dönebilir → `docker compose restart nginx`.
- CI iş yapısı §16'dadır.

---

## 11. Frontend testleri (Vitest)

Araçlar: Vitest 5 (+ `@vitest/coverage-v8`), jsdom, React Testing Library (`react`, `user-event`, `jest-dom`), MSW 3. Kök `frontend/vitest.config.ts` dört projeyi (`packages/shared`, `apps/superadmin`, `apps/company`, `apps/portal`) birleştirir; her projenin kendi `vitest.config.ts`'i ve `test` betiği vardır.

| Komut (`frontend/` içinde) | Ne yapar |
|---|---|
| `pnpm test` | Tüm projeler (~55 sn). |
| `pnpm test:coverage` | Aynısı + kapsam (`frontend/coverage/`: `coverage-summary.json`, `lcov`, HTML; git dışı). |
| `pnpm --filter @dobedan/company test` | Tek proje. |
| `pnpm vitest run --project shared src/services/api.test.ts` | Tek dosya. |
| `pnpm typecheck` | 3 uygulamada `tsc -b`; her uygulamanın `tsconfig`'i `packages/shared/src`'yi de içerir. |

Sayılar (Adım 03B sonu): **17 dosya, 291 test** — shared 77, company 17 birim + 127 route duman + 3 menü, portal 43 duman + 4 menü, superadmin 18 duman + 2 menü.

Yardımcılar (`packages/shared/src/test/`, `@shared/test` olarak içe aktarılır):

| Dosya | İçerik |
|---|---|
| `setup.ts` | jest-dom, TR i18n, jsdom eksikleri (ResizeObserver, matchMedia, canvas…), MSW sunucusu `onUnhandledFrame: 'error'` (tanımsız istek testi kırar), her testten sonra temizlik. |
| `renderWithProviders.tsx` | Redux store (`auth`, `theme` + `extraReducers`), Router (`route`), QueryClient, i18n ile render; `createTestStore`. |
| `fixtures.ts` | `makeUser`, `companyAdminUser`, `userWithPermissions([...])`, `portalEmployeeUser`, `superAdminUser`, `authenticatedState`, `persistSession`, `setCompanyContext`, şirket/şube bağlamı yanıtları, Form Engine alan/tanım fixture'ları. |
| `msw.ts` | `ApiResponse` zarfı (`envelope`), `apiOk`, `apiPaginated`, `apiError`, `emptyApiFallbackHandlers`. |
| `consoleGuard.ts` | `captureConsoleErrors(ignore)` — konsol hatası olan testi kırmak için. |
| `routeManifest.ts` | `App.tsx` kaynağından route listesi, statik bağlantı ve `path:` çıkarıcıları. |

jsdom URL'si API ile aynı kökendir (`http://localhost:8000/`); CORS ön isteği oluşmaz.

**Kapsam mandalı:** CI `frontend-tests` işi `coverage-summary.json` satır kapsamını tabanla karşılaştırır (`node scripts/check-coverage.mjs frontend frontend/coverage/coverage-summary.json`). İlk ölçüm %39,14–39,19 (route duman testinin zamanlamasına bağlı ±0,05 oynama); taban bu oynamanın altında seçildi. Yükseltme kuralı backend ile aynıdır (`FRONTEND_COVERAGE_FLOOR` + aşağıdaki işaret, ayrı commit).

<!-- coverage-floor:frontend=39.00 -->
Frontend satır kapsamı tabanı: **%39,00**.

## 12. MSW kalıpları

- Her test kendi handler'ını `server.use(...)` ile ekler; `afterEach` sıfırlar. Eşleşmeyen istek hata verir — "sessizce boş dönen" test yoktur.
- Yanıtlar her zaman `ApiResponse` zarfıyla: `apiOk('get', '/employees/1', {...})`, `apiPaginated('/employees', [...], { total: 1 })`, `apiError('post', '/auth/login', 401, 'Hatalı giriş.')`.
- Yolu `apiUrl()` üretir (`api.defaults.baseURL` + yol); `http.get(apiUrl('/x'), ...)` ile özel handler yazılabilir.
- Gövde/başlık doğrulamak için handler içinde `request` yakalanır (örnek: `services/api.test.ts` — `Authorization`, `X-Company-Id`, `X-Branch-Id`).
- Sayfanın beklediği şekil liste değil nesneyse (rapor özeti, tek kayıt) gerçekçi boş nesne handler'ı yazılır; boş liste ile geçiştirilmez (örnek: `apps/company/src/test/smokeHandlers.ts`).

## 13. Route duman testi ve menü–route tutarlılığı

- `apps/*/src/routes.smoke.test.tsx`: route listesi `App.tsx?raw` kaynağından **kod okunarak** üretilir (`buildRouteManifest`); yeni route eklendiğinde otomatik kapsanır. Ayrıştırıcı bulduğu route sayısını kaynaktaki `path=` sayısıyla karşılaştırır, kaçırırsa test kırılır.
- Her somut route için: yetkili kullanıcıyla `emptyApiFallbackHandlers` (+ uygulamaya özel handler'lar) altında render; konsol hatası, hata sınırı ("Bir Hata Oluştu"), "Erişim Engeli" ve beklenmeyen giriş formu yoktur. Parametreli yollar `1` ile doldurulur.
- Yetkisiz senaryolar: oturumsuz → `/login`; izinsiz kullanıcı → "Erişim Engeli"; portalda `must_change_password` → `/force-password-change`; superadmin'e şirket yöneticisi → `/login`.
- Route sayıları (Adım 03B): company 124 (123 somut + `*`), portal 24 (23 + `*`), superadmin 11 (10 + `*`).
- `menuRoutes.test.ts` (her uygulama): sidebar/rail/alt menü tanımlarındaki ve koddaki statik bağlantıların (`to="/x"`, `navigate('/x')`, `path: '/x'`) her biri bir route'a eşleşir; route'suz menü bağlantısı testi kırar.

## 14. API sözleşme kontrolü

`scripts/check-api-contract.mjs` frontend kaynağındaki (`packages/shared/src`, `apps/*/src`; testler hariç) her `api.get/post/put/patch/delete(...)` çağrısını backend `route:list` çıktısıyla eşler. `${...}` parametre segmentidir, sorgu dizesi atılır, `{x?}` isteğe bağlı segment desteklenir.

```bash
docker compose exec -T app php artisan route:list --json --path=api/v1 > /tmp/api-routes.json
node scripts/check-api-contract.mjs /tmp/api-routes.json [--verbose]
```

Eşleşmeyen veya yolu çözülemeyen (dinamik) çağrı → exit 1. Adım 03B: 674 route, 587 çağrı, 0 eşleşmeyen, 0 dinamik. Route listesi veritabanı gerektirmez (CI `frontend-quality` işi PHP kurup üretir). Windows PowerShell'de `>` UTF-16 yazar; çıktıyı bash/cmd ile veya `Out-File -Encoding utf8` ile alın.

## 15. Uçtan uca testler (Playwright, `e2e/`)

**İzolasyon:** E2E canlı geliştirme veritabanına (`dobedan_hr`) asla dokunmaz.

- Veritabanı `<POSTGRES_DB>_e2e` (`dobedan_hr_e2e`); yeni volume'de `docker/postgres/init/01-test-db.sh` açar, `e2e/scripts/prepare.mjs` yoksa oluşturur.
- Backend `app-e2e` servisi (`docker compose --profile e2e`, port **8100**): aynı imaj, `APP_ENV=e2e`, dizi önbellek/oturum, senkron kuyruk, ayrı özel disk kökü (`storage/app/e2e/private`). Config/route/event önbelleği container içinde (`APP_*_CACHE=/tmp/...`) tutulur ve her hazırlıkta yeniden üretilir; önbelleksiz koşuda eşzamanlı istekler 15 sn'yi aşıp senaryoları rastgele düşürüyordu. Paylaşılan `bootstrap/cache`'e yazılmaz, geliştirme `app`i etkilenmez.
- Sıfırlama `php artisan e2e:reset` = `migrate:fresh --seed --seeder=E2eSeeder`. `E2eDatabaseGuard` bağlı DB adı `_e2e` ile bitmiyorsa (veya production'da) reddeder; aynı koruma `migrate:fresh/refresh` ve `db:seed` E2eSeeder'ı hedeflediğinde komut başlamadan çalışır (`E2eDatabaseGuardTest`).
- `E2eSeeder` deterministiktir: E2E Şirket A (yönetici, personel, 2FA'lı yönetici; personelin yöneticisi = yönetici; 14 gün yıllık izin), E2E Şirket B (yönetici, A'nın görmemesi gereken personel), süper admin; modüller açık, varsayılan izin onay akışı. Şifre ve TOTP anahtarı yalnız bu veritabanına özgüdür; sabitler `e2e/fixtures/data.ts` ile birebir aynı tutulur.

**Çalıştırma** (Docker yığını ayakta, en az `postgres`):

| Komut | Ne yapar |
|---|---|
| `pnpm e2e` (`frontend/` içinde) veya `pnpm --dir e2e e2e` | Hazırlık (DB + `app-e2e` + önbellek + `e2e:reset`) → 3 SPA'yı vite ile 4101/4102/4103'te açar (API :8100) → senaryolar. Tam koşu ~5 dk. |
| `pnpm e2e:ui` | Playwright UI modu. |
| `E2E_SKIP_PREPARE=1 pnpm --dir e2e exec playwright test tests/x.spec.ts` | Sıfırlamadan tek dosya (veri önceki koşudan kalır). |
| `pnpm --dir e2e typecheck` | Senaryoların tip kontrolü. |

İlk kurulum: `pnpm --dir e2e install` + `pnpm --dir e2e exec playwright install chromium`.

**Senaryolar (13 test):** `auth.spec.ts` — üç uygulamada giriş/çıkış ve yanlış şifre, 2FA başarılı ve yanlış kod; `employees.spec.ts` — personel oluştur → listede gör → düzenle; `leave-flow.spec.ts` — portal izin talebi → company onayı → bakiye düşer → portalda "Onaylandı"; `tenant-isolation.spec.ts` — A yöneticisi B personelinin detay/düzenleme URL'sini açamaz (API 403/404, ekranda veri yok); `superadmin-modules.spec.ts` — firma listesi, modül kapatınca company menüsünden düşer, açınca geri gelir; `documents.spec.ts` — evrak yükle, indir, içerik birebir aynı.

**Kurallar:** Senaryolar aynı veritabanını paylaşır → sıralı (`workers: 1`), tekrar deneme yok. Veri değiştiren senaryo benzersiz değer üretir (zaman damgası) ya da değiştirdiğini `finally` ile geri alır. Seçici önceliği: rol + erişilebilir ad, placeholder, `title`; CSS sınıfı son çare. Hata anında ekran görüntüsü, video ve trace `e2e/test-results/` altına (git dışı); `pnpm exec playwright show-trace <trace.zip>`.

`scripts/gorsel-kontrol` (görsel denetim düzeneği) E2E ile birleştirilmedi: canlı demo verisi ve demo şifresiyle çalışır, ayrı npm kilidi vardır ve çıktısını `docs/` altına yazar — izole veritabanı kuralıyla çelişir.

## 16. CI işleri (`.github/workflows/ci.yml`)

Tetikleyici: `main`, `faz*`, `altyapi` push; `main`'e PR; `workflow_dispatch`. Aynı ref'te yeni koşu eskisini iptal eder (`concurrency`). İşler paraleldir:

| İş | Adımlar |
|---|---|
| `backend-quality` | PG16 servis → migrate → `pint --test` → `composer analyse` → baseline büyüme kontrolü → `composer test:arch` → `composer audit` (yalnız rapor, kırmaz). |
| `backend-tests` | PG16 (`max_locks_per_transaction=256`) → migrate ×2 (idempotent) → paralel PHPUnit + pcov Clover (artefakt `backend-coverage-clover`) → backend kapsam mandalı. |
| `frontend-quality` | Mojibake, `check:brand`, kapsam tabanı eşitliği (`sync`), portal route smoke → `pnpm install --frozen-lockfile` → lint → `pnpm typecheck` → `route:list` JSON → API sözleşme kontrolü. |
| `frontend-tests` | `pnpm test:coverage` (artefakt `frontend-coverage`) → frontend kapsam mandalı. |
| `frontend-build` | `pnpm build` (3 SPA) → `scripts/bundle-size-summary.mjs` (iş özetine JS/CSS ham + gzip tablosu). |
| `e2e` | Yalnız `main`'e PR ve `workflow_dispatch`. Compose `postgres` + `app-e2e` imajı → frontend + e2e kurulumu → Chromium → `pnpm --dir frontend e2e`. Hata halinde `app-e2e` logları ve `e2e-results` artefaktı (trace/video/rapor). |

Önbellekler: `backend/vendor` (composer.lock), pnpm store (setup-node, lock dosyaları), `~/.cache/ms-playwright`. YAML `actionlint` ile denetlenir: `docker run --rm -v "$PWD:/repo" -w /repo rhysd/actionlint:latest`.
