# Backend testleri — kurulum, komutlar, kurallar

Dobedan HR backend test altyapısının tek başvuru belgesi. Tamamlanma tanımı (DoD) `.cursorrules` içindedir; bu belge nasıl yapılacağını anlatır.

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

Sayılar (Adım 03A sonu): **968 test, 4143 assertion**, deprecation 0.

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
| `composer test:parallel` | Paralel tam suite (8 süreç, ~6,5 dk). Günlük kullanım budur. |
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

Bu adımda eşik zorunlu değil. Kapsam düşüren değişiklik gerekçesiz kabul edilmez; eşik Adım 03B/CI ile gelecek. En düşük modüller: Performance %7,1, Admin %11,1, Console %15,6, Assets %16,3, Training %20,3 (modül tablosu: `docs/cursor-gorevleri/raporlar/ADIM_03A_RAPOR.md`).

## 6. Statik analiz

- **Pint:** `vendor/bin/pint --test` exit 0 olmalı. Satır sonu LF'dir (`.gitattributes`); Windows'ta CRLF çalışma kopyası Pint'te `line_ending` olarak görünür, `vendor/bin/pint` düzeltir.
- **Larastan (PHPStan seviye 5):** `phpstan.neon` + `phpstan-baseline.neon` (Adım 03A: **891 hata**). Baseline mevcut borcu görünür tutar; **büyümez** — yeni hatalar düzeltilir, baseline'a eklenmez. Baseline'dan hata düzeltildiğinde `vendor/bin/phpstan analyse --generate-baseline` ile küçültülür.
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
- CI (`.github/workflows/ci.yml`) şu an `pint --test` + seri `php artisan test` koşar; paralel, kapsam ve `composer analyse` CI'a Adım 03B'de eklenecek.
