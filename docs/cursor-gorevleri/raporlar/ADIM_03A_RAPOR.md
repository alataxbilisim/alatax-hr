# ADIM 03A Raporu — Backend Test Altyapısı ve Bilinen Kritik Hatalar

**Dal:** `altyapi` · **Tarih:** 2026-10-14

**Özet:** Adım 03A tamamlandı; tüm kabul kriterleri ✅. Sekiz kritik hata (K1–K8) ayrı commit'lerle kapatıldı; her birinin regresyon testi var (K7 betik düzeltmesi, K8 container doğrulaması). Test veritabanı artık otomatik oluşuyor ve testler `_testing` ile bitmeyen bir veritabanında başlamıyor. Tam suite paralel koşuyor: 968 test, 4143 assertion, 0 hata, 0 deprecation; süre 21:44'ten 6:27'ye indi. Seri koşu da yeşil. Satır kapsamının taban çizgisi %58,32. 116 modelin hepsinin factory'si var. Kalıcı mimari testler eklendi: route envanteri, yetki kapısı, 2FA, tenant, disk adı, kod kuralları. `pint --test` temiz. Larastan seviye 5, 891 hatalık baseline ile exit 0 veriyor. Canlı veritabanında satır sayısı değişmedi; yalnız Telescope'un log tabloları büyüdü. K6 veri düzeltmesi satır eklemedi, yalnız değer güncelledi.

---

## 1. Yapılanlar ve dosyalar

### Bölüm A — Kritik hatalar

| # | Sorun ve kök neden | Çözüm | Regresyon testi | Commit |
|---|---|---|---|---|
| K1 | Portal ve süper admin route gruplarında `deny.2fa.challenge` yoktu; 2FA challenge token'ı bu uçlara 200 ile erişebiliyordu | `routes/api.php` içinde iki gruba da middleware eklendi. Kalıcı kural: `auth:sanctum` kullanan her route'ta bu middleware zorunlu; tek istisna `auth/2fa/verify` | `tests/Security/TwoFactorChallengeIsolationTest` (portal, admin ve panel uçları challenge token ile 403; gerçek token çalışıyor; verify ucu açık) + `tests/Architecture/TwoFactorRouteGuardTest` | `8c7fbea` |
| K2 | `private` disk tanımsızdı; belge ve bordro yükleme/indirme 500 dönüyordu | `config/filesystems.php`: `private` diski (local, `storage/app/private`, `visibility=private`, `serve=false`, `throw=true`; sürücü `PRIVATE_DISK_DRIVER` ile değiştirilebilir). `.env` örneklerine açıklama eklendi | `tests/Feature/PrivateDiskFileFlowTest`: `Storage::fake` olmadan gerçek diskte yükle, indir, sil. `tests/Architecture/StorageDiskNameGuardTest`: koddaki her sabit disk adı config'de tanımlı olmalı | `60a3437` |
| K3 | Pano widget filtresi istemcinin gönderdiği kolon adını doğrudan `where()`'e koyuyordu; yetkisiz kullanıcı maaş veya TCKN ile filtreleyip değer tahmin edebiliyordu | `app/Services/Reports/EmployeeDashboardFilterResolver.php`: filtreler yalnız `EmployeesDataset` alanlarına eşleniyor. Bilinmeyen alan veya geçersiz değer 422; yetkisiz hassas alan 403. Diğer istemci kaynaklı sıralamalar da tarandı: `app/Traits/AppliesListSorting.php` ile `employees`, `users`, `admin/users`, `admin/companies`, `recruitment/positions` listelerinde `sort_by`/`sort_dir` beyaz listeye bağlandı (listede olmayan değer 422). `DocumentController` zaten beyaz liste kullanıyordu | `tests/Security/QueryColumnWhitelistTest` | `67e389a` |
| K4 | KPI hesabı `employees` tablosunda olmayan `age`/`tenure` kolonlarının ortalamasını alıyordu (500) | Tek metrik ifade haritası: `date_part('year', age(CURRENT_DATE, birth_date))`, `tenure` için aynısı `hire_date` ile. KPI ve grafik yolu aynı ifadeyi kullanıyor; sonuç sayısal | `tests/Feature/EmployeeDashboardKpiTest`: her KPI ve grafik metriği 200 ve sayısal | `2e0e5a3` |
| K5 | Frontend iki onboarding ucunu çağırıyordu ama backend'de route yoktu | `POST onboarding/templates/{template}/duplicate` (`permission:onboarding.templates.create`, FormRequest, kopya `is_default=false`, ActivityLog) ve `POST onboarding/processes/{process}/tasks/{task}/skip` (`permission:onboarding.processes.edit`, yalnız zorunlu olmayan ve tamamlanmamış görev, ActivityLog). Tenant izolasyonu route model binding + `BelongsToCompany` ile (404). Aynı controller'larda yanlış argüman sırasıyla çağrılan 3 `error()` düzeltildi. `frontend/packages/shared/src/services/api.ts` içinden backend karşılığı olmayan 13 ölü metot silindi | `tests/Feature/OnboardingDuplicateAndSkipTest`: 401, 403, happy path, 422, başka şirket 404 | `5f3b74c` |
| K6 | Kullanıcı hesabı olmayan işten çıkmış demo personel "Unknown" görünüyordu. Kök neden: `DemoDataSeeder` `employees.full_name` yazmıyordu; hesabı olanlar `users.name`'e düşüyordu, olmayanlar sabit "Unknown"a | Seeder bütün demo personeline `full_name` yazıyor. `Employee::display_name` sırası: `full_name`, sonra `users.name`, sonra personel kodu (sabit İngilizce metin kaldırıldı). Personel export'unun "Ad Soyad" sütunu `display_name` kullanıyor. `EmployeeFactory` `full_name` üretiyor. **Canlı veri:** yedekten sonra mevcut idempotent `employees:backfill-full-name` komutu boş `full_name`'li **54 satırı** `users.name`'den doldurdu (yalnız değer değişti, satır sayısı aynı). Kullanıcısı olmayan 6 satır (DEM-035…040) boş kaldı ve personel koduyla görünüyor (bkz. 6. bölüm) | `tests/Feature/EmployeeDisplayNameFallbackTest`, `tests/Feature/DemoDataSeederGuardTest` | `444e386` |
| K7 | İki betikte sabit veritabanı şifresi | `scripts/schema-dump-prune.sh`: sabit `PGPASSWORD` kaldırıldı; `pg_dump` postgres container'ının kendi ortamıyla `docker compose exec` üzerinden çalışıyor. `scripts/uzun-kosu/seed-scale.php`: sentetik kullanıcı şifresi `SCALE_SEED_PASSWORD`'den okunuyor; tanımlı değilse betik veritabanına dokunmadan duruyor | Test yok (görev dosyasında da "—"). Kaldırılan değer, geliştirme ortamının varsayılan şifresiyle aynı; bkz. 5. bölüm | `452fa87` |
| K8 | `public/storage` symlink'i yoktu | `docker/php/entrypoint.sh`: app (php-fpm) servisinde link yoksa `storage:link` çalışıyor. Queue ve scheduler aynı volume'u paylaştığı için, yarış olmasın diye orada çalışmıyor | Elle: imaj yeniden derlenip restart edilince link oluşuyor (`public/storage -> /var/www/html/storage/app/public`); ikinci restart'ta komut tekrar çalışmıyor; `/storage/...` isteği HTTP 200 | `31f8337` |

**K2 teşhisi** (DB'deki dosya yolları diskte var mı; canlı veritabanı, bugün):

| Tablo.kolon | Beklenen disk | Yolu dolu satır | Beklenen diskte | Diğer diskte | Eksik |
|---|---|---:|---:|---:|---:|
| `employee_documents.file_path` | private | 0 | 0 | 0 | 0 |
| `payslips.file_path` | private | 0 (6 bordro var, hiçbirinde dosya yok) | 0 | 0 | 0 |
| `documents.file_path` | public | 3 | 0 | 0 | **3** (demo seed'in yer tutucu kayıtları; dosya hiç üretilmemiş) |
| `document_versions`, `job_applications.cv_path`, `leave_requests.document_path`, `expense_items.receipt_path`, `users.avatar`, `companies.logo` | public | 0 | 0 | 0 | 0 |

Kullanıcı verisi kaybı yok: `private` disk tanımsız olduğu için bugüne kadar hiçbir belge veya bordro dosyası yüklenememişti.

### Bölüm B — Test altyapısı

| # | Yapılan | Dosyalar | Commit |
|---|---|---|---|
| B1 | Boş bir Postgres volume'ü ilk açılışta `dobedan_hr_testing`'i kendisi oluşturuyor. Mevcut volume'ler için idempotent `php artisan db:create-testing` (`composer test:db`) var; yalnız `_testing` ile biten adları kabul ediyor. `TestCase` koruması: veritabanı adı `/_testing(_test_\d+)?$/` kalıbına uymuyorsa testler başlamadan duruyor | `docker/postgres/init/01-test-db.sh`, `docker-compose.yml` (init mount), `app/Console/Commands/CreateTestingDatabaseCommand.php`, `tests/TestCase.php`, `tests/Architecture/TestDatabaseSafetyTest.php` | `62c54a7` |
| B2 | `brianium/paratest ^7.8`. `php artisan test --parallel` her süreç için ayrı `dobedan_hr_testing_test_N` açıyor. Paralelde kırılan 5 test sıraya bağlı değildi; veritabanı adını birebir bekliyorlardı. Bunlar paralel son ekini kabul edecek şekilde düzeltildi (hiçbiri atlanmadı) | `composer.json/.lock`, 5 test dosyası | `6511661` |
| B3 | Suite'ler: `Unit`, `Feature`, `Architecture`, `Security` (yeni). Composer script'leri: `test`, `test:parallel`, `test:arch`, `test:security`, `test:coverage`, `test:db`, `analyse` (hepsi zaman aşımsız) | `phpunit.xml`, `composer.json` | `8c7fbea`, `b4446a5` |
| B4 | PHPUnit deprecation'ları **59 → 0**: doc-comment metadata (`@test`, `@dataProvider`) attribute'a çevrildi | 9 test dosyası | `42f0f4d` |
| B5 | `pcov` dev imajında, varsayılan olarak kapalı (`pcov.enabled=0`, normal koşular yavaşlamasın diye). `composer test:coverage`, `tests/php/pcov.ini`'yi `PHP_INI_SCAN_DIR` ile açıyor ve HTML + Clover + metin özetini `storage/coverage/` altına yazıyor (git dışı). Postgres'e `max_locks_per_transaction=256` verildi: 8 paralel sürecin `migrate:fresh`'i varsayılan kilit tablosunu taşırıyordu ("out of shared memory") | `backend/Dockerfile`, `tests/php/pcov.ini`, `backend/.gitignore`, `composer.json`, `docker-compose.yml` | `b4446a5` |
| B6 | Factory sayısı 9'dan **116/116**'ya çıktı; istisna listesi şu an boş. 107 factory şemadan üretildi (NOT NULL, FK, CHECK, unique, enum cast) ve sonra elle düzeltildi. `company_id` önce çözülüyor. Şirketli ebeveynler aynı şirketten geliyor; `users` ebeveyni `home_company_id` ile aynı şirkete bağlanıyor. Faker `tr_TR`. `HasFactory` eksik olan 36 modele eklendi | `database/factories/*` (107 yeni), `app/Models/*` (36), `tests/Architecture/ModelFactoryCoverageTest.php`, `tests/Feature/Factories/ModelFactoriesTest.php` (her factory `create()` edilir; ebeveynlerin şirket tutarlılığı kontrol edilir) | `b01f555` |
| B7 | Ortak test yardımcıları: rol ve izinli kullanıcı, şirket bağlamı (`X-Company-Id`), portal personeli, 2FA'lı kullanıcı + challenge token, "başka şirket" fixture'ı | `tests/Concerns/*.php` (5 trait) | `12c20f0` |

### Bölüm C — Mimari testler (`tests/Architecture/`)

| # | Test | Kural ve izin listesi |
|---|---|---|
| C1 | `RouteSecurityInventoryTest` | Her `api/v1` route'u ya `auth:sanctum` kullanıyor ya da `PUBLIC_ROUTES` listesinde gerekçesiyle yer alıyor (14 route). Public route'ların hepsi throttle'lı |
| C2 | `RouteSecurityInventoryTest` | Her sanctum route bir kapıdan geçiyor (`permission:`, `can:`, `role:`, `role_or_permission:`, `super_admin`, `portal.access`) ya da `SELF_SERVICE_ROUTES` listesinde gerekçesiyle yer alıyor (29 route: `auth/me`, profil, bildirimler, Policy ile korunan onay aksiyonları vb.). Listede bayat kayıt kalırsa da test kırılıyor |
| C3 | `TwoFactorRouteGuardTest` | `auth:sanctum` kullanan her route'ta `deny.2fa.challenge` var; tek istisna `auth/2fa/verify` |
| C4 | `TenantModelGuardTest` | `company_id` kolonu olan her tablonun modeli `BelongsToCompany` kullanıyor. Gerekçeli istisnalar: 5 model (`company_ledger`, `company_user`, `holidays`, `lookups`, `setting_values`) ve modeli olmayan 16 tablo. Bayat kayıt testi var. Bulgu: `Timesheet` modeli tenant scope'suzdu; `BelongsToCompany`'ye alındı (`4b74e7c`) |
| C5 | `StorageDiskNameGuardTest` | Koddaki her `Storage::disk('x')` adı `config('filesystems.disks')` içinde tanımlı |
| C6 | `CodeRulesTest` | `app/` içinde `env(` yok; controller'larda `DB::statement` / `DB::unprepared` yok; `app/`, `routes/` ve `database/` içinde `dd(`, `dump(`, `var_dump(` yok |
| C7 | `BrandGuardTest`, `DatabaseDriverGuardTest` | Korundu, yeşil |
| + | `TestDatabaseSafetyTest`, `ModelFactoryCoverageTest` | B1 ve B6 korumaları |

### Bölüm D — Statik analiz ve stil

- **Pint:** Yalnız biçim içeren tek bir `style: pint` commit'i (`072f0af`). Pint 165 dosyada sapma bildirdi. Bunların ~149'u yalnız satır sonu (CRLF) farkıydı ve git satır sonlarını normalleştirdiği için diff'e girmedi. İçerik değişikliği 16 dosyada oldu: kullanılmayan import'lar, tırnaklar, girinti. `pint --test` exit 0 (860 dosya).
- **Larastan:** `larastan/larastan ^3`, `phpstan.neon` (seviye 5, `app/`). Baseline `phpstan-baseline.neon`: **891 hata** (617 kayıt, 180 dosya). `composer analyse` exit 0. Larastan şema okumak için yalnız MySQL sözdizimli dump'ı ayrıştırabiliyor. Bu yüzden `pgsql-schema.sql` okunmuyordu ve 3.169 ham hatanın ~2.640'ı sahte "bilinmeyen kolon" hatasıydı. `scripts/larastan-schema.php` bağlı veritabanının şemasından Larastan'a özel bir anlık görüntü üretiyor (salt okuma, git dışı); `composer analyse` önce bunu çalıştırıyor (`c33c14e`).
- **`composer audit`** (düzeltme Adım 04'te): 15 pakette 46 advisory, **14 high**.

| Paket | Toplam | High | Medium | Low | Önemli başlık |
|---|---:|---:|---:|---:|---|
| `league/commonmark` | 14 | 9 | 5 | 0 | — |
| `guzzlehttp/guzzle` | 9 | 1 | 8 | 0 | — |
| `laravel/framework` | 4 | 1 | 1 | 1 | Varsayılan `email` kuralında CRLF enjeksiyonu (<12.60.0); imzalı URL yol karışıklığı; debug sayfasında XSS; 1 kaydın şiddeti belirtilmemiş |
| `symfony/mime` | 2 | 1 | 1 | 0 | — |
| `symfony/http-kernel` | 1 | 1 | 0 | 0 | — |
| `phpunit/phpunit` | 1 | 1 | 0 | 0 | PHPT kapsam işleminde güvensiz deserialization (dev) |
| `guzzlehttp/psr7` | 4 | 0 | 4 | 0 | — |
| `symfony/yaml` | 3 | 0 | 0 | 3 | — |
| `symfony/routing` | 2 | 0 | 2 | 0 | — |
| `symfony/process`, `symfony/http-foundation`, `psy/psysh`, `symfony/mailer` | 1'er | 0 | 1'er | 0 | — |
| `symfony/polyfill-intl-idn`, `league/flysystem` | 1'er | 0 | 0 | 1'er | — |

### Bölüm E — Dokümantasyon

- `docs/operasyon/TEST.md` (yeni): suite'ler, test veritabanı kurulumu, bütün komutlar, paralel koşu, kapsam taban çizgisi, statik analiz, mimari testlerin mantığı ve izin listelerinin güncellenmesi, `tests/Concerns` yardımcıları, yeni modül için zorunlu test şablonu (`OrnekKayitApiTest`).
- `.cursorrules`: "Tamamlanma tanımı (DoD)" bölümü eklendi. Kapsadıkları: her endpoint için 401, 403, happy path, başka şirket 404 ve 422 testleri; her yeni model için tenant-doğru factory; route kapısı ve `BelongsToCompany`; `test:parallel` ve `test:arch` yeşil; `pint --test` temiz; `composer analyse` exit 0 ve baseline büyümüyor.
- `docs/DURUM.md`: başlık Adım 03A. Modül tablosunda A1, A2, A7, B2, B5, B7 ve B13 notları güncellendi. §5 kritik hatalar K1–K8 kapandı olarak işaretlendi. Yeni §5a test ve kalite tablosu (968 test, kapsam %58,32, PHPStan baseline 891). §6 borç listesinde #2, #3, #25 ve #26 kapandı; #10, #14, #15 ve #20 güncellendi; #27–#34 yeni.
- `docs/README.md`: doküman haritasına `operasyon/TEST.md` eklendi.

---

## 2. `git log --oneline` (Adım 03A)

```
(bu rapor) docs: Adim 03A raporu
8df0e69 docs: TEST.md, DoD ve DURUM (E)
c33c14e chore: Larastan seviye 5 + baseline (D2)
072f0af style: pint
b01f555 test: her Eloquent model icin tenant-dogru factory (B6)
b4446a5 test: composer test script'leri, pcov ve paralel kosu PG kilit ayari (B3/B5)
4b74e7c test: mimari testler - route envanteri, yetki kapisi, tenant, kod kurallari (C)
42f0f4d test: PHPUnit doc-comment metadata yerine attribute (B4)
6511661 test: paratest ile paralel PostgreSQL test kosusu (B2)
62c54a7 test: test veritabanini otomatik hazirla ve canli DB'yi kilitle (B1)
31f8337 fix(docker): entrypoint'te idempotent storage:link (K8)
444e386 fix(employees): "Unknown" personel adi kok nedeni (K6)
452fa87 fix(scripts): betiklerdeki sabit sifreleri ortama tasi (K7)
5f3b74c feat(onboarding): sablon kopyalama ve gorev atlama uclari (K5)
2e0e5a3 fix(dashboard): age/tenure KPI'larini tarihten hesapla (K4)
67e389a fix(security): istemci kolon adlarini beyaz listeye bagla (K3)
60a3437 fix(storage): private disk tanimi + gercek disk akis testi (K2)
8c7fbea fix(security): portal ve super admin route'larinda 2FA challenge token'ini reddet (K1)
12c20f0 test: ortak test yardimcilari (tests/Concerns)
aa7111d docs: Adim 03A ve 03B gorev dosyalarini ekle
```

Push yapılmadı; `main`'e dokunulmadı.

---

## 3. Doğrulama

| Kontrol | Komut | Sonuç |
|---|---|---|
| Paralel suite | `composer test:parallel` (8 süreç) | **OK (968 test, 4143 assertion)**, 0 deprecation, 0 risky, 06:27 |
| Seri suite | `composer test` | **968 passed (4143 assertion)**, 0 deprecation, 23:33 |
| Mimari testler | `composer test:arch` | **22 passed (160 assertion)** |
| Kapsam | `composer test:coverage` | Yeşil; testler 7:47, rapor üretimiyle birlikte ~15 dk (HTML üretimi 5:55) |
| Stil | `vendor/bin/pint --test` | exit 0 (860 dosya) |
| Statik analiz | `composer analyse` | `[OK] No errors` (baseline ile), exit 0 |
| Frontend | `pnpm -r lint`, `pnpm -r build` | lint 3/3 app temiz; build 3/3 app başarılı |

**Süreler (B2):**

| Koşu | Test | Süre |
|---|---:|---|
| Önce, seri (adım başı) | 837 | 21:44 |
| Paralel, ilk ölçüm | 841 | 5:36 |
| Paralel, son (factory testleri dahil) | 968 | **6:27** (~3,4 kat hızlı) |
| Seri, son | 968 | 23:33 |

Görev dosyasında "~17 dk" yazıyordu; bu makinede seri ölçüm 21:44 çıktı.

**Kapsam taban çizgisi (B5, `app/`, 527 dosya):** satır **%58,32** (16.984/29.122), metot %38,44 (991/2.578), sınıf %14,25 (63/442).

| Modül / dizin | Satır % | | Modül / dizin | Satır % |
|---|---:|---|---|---:|
| Auth | 100 | | Payroll | 63,7 |
| Pdf | 100 | | Core | 59,7 |
| Export | 97,4 | | Workflow | 54,8 |
| Observers | 95,5 | | Public | 54,4 |
| Traits | 94,2 | | Leaves | 53,0 |
| Rebrand | 93,2 | | Portal | 52,4 |
| Http (middleware vb.) | 93,1 | | Surveys | 50,3 |
| Settings | 90,4 | | Models | 46,4 |
| Notification (servis) | 83,2 | | Recruitment | 39,2 |
| Providers | 82,8 | | Documents | 35,3 |
| Approval | 81,1 | | Jobs | 35,1 |
| Reports | 79,0 | | Notifications (sınıflar) | 33,3 |
| Demo | 77,5 | | Mail | 21,6 |
| Support | 76,1 | | Training | 20,3 |
| Enums | 75,0 | | Assets | 16,3 |
| Salary | 74,2 | | Console | 15,6 |
| Timesheet | 73,6 | | Admin | 11,1 |
| Policies | 71,6 | | Performance | **7,1** |
| Onboarding | 71,3 | | | |
| Kvkk | 71,0 | | | |
| Expenses | 68,1 | | | |
| Rules | 66,7 | | | |
| Announcements | 64,1 | | **Toplam** | **58,3** |

Modül = `app/Http/Controllers/Api/V1/{Modül}` + `app/Services/{Modül}`. Diğer dizinler ayrı satırda.

**Elle kontroller (canlı API, nginx üzerinden):** Script geçici token'lar oluşturdu ve bitince sildi; token değerleri hiçbir yere yazılmadı.

| Kontrol | Sonuç |
|---|---|
| Süper admin `GET /admin/companies`, challenge token ile | **403** (normal token 200) |
| Portal `GET /portal/dashboard`, challenge token ile | **403** (normal portal token 200) |
| Firma paneli: personele belge yükleme `POST /employees/{id}/documents` | **201** |
| Aynı belgeyi indirme `GET .../documents/{id}/download` | **200**; içerik birebir aynı; dosya `private` diskte |
| Portal bordro listesi `GET /portal/payslips` | 200 |
| Portal bordro indirme | Canlıda 6 yayınlanmış bordro var ama hiçbirinin dosyası yok. Bu yüzden `GET /portal/payslips/{id}/download` **404 "Bordro dosyası bulunamadı"** döndü; eskiden disk tanımsız olduğu için 500 dönüyordu. Dosyalı indirme akışını `PrivateDiskFileFlowTest` gerçek diskte doğruluyor |

Elle kontrolün oluşturduğu belge, dosya, token ve activity log satırları silindi. Kontrol öncesi ve sonrası `employee_documents=0`, `personal_access_tokens=9`, `activity_logs=503`.

**Canlı satır sayıları:** `docs/backups/satir_sayilari_oncesi_adim03a.txt` ve `..._sonrasi_adim03a.txt` dosyaları karşılaştırıldı (155/155 tablo, `Compare-Object`). Fark yalnız iki log tablosunda: `telescope_entries` 91.716 → 109.398 ve `telescope_entries_tags` 36.467 → 49.420. Bunlar geliştirme ortamında Telescope'un istek kayıtları (elle kontroller ve kullanım). Diğer 153 tabloda fark sıfır. K6 düzeltmesi 54 satırın `full_name` değerini güncelledi; satır sayısını değiştirmedi.

**Yedekler** (`docs/backups/`, git dışı): `oncesi_adim03a_20261014-1238.dump` (adım başı), `dobedan_hr_oncesi_k6_20261014_132520.dump` (K6 veri düzeltmesi öncesi), `dobedan_hr_oncesi_pg_locks_20261014_143207.dump` (postgres container'ı `max_locks` için yeniden oluşturulmadan önce).

---

## 4. Kabul kriterleri

| Kriter | Durum | Kanıt |
|---|---|---|
| K1–K8 kapandı, her biri için regresyon testi var | ✅ | 1. bölümdeki tablo; K1–K6 testleri suite'te yeşil. K7 için görev dosyasında test istenmiyor. K8 container restart ile doğrulandı |
| Test DB otomatik oluşuyor; canlı DB'ye karşı test çalıştırma imkânsız | ✅ | `01-test-db.sh` + `db:create-testing`; `TestCase` koruması; `TestDatabaseSafetyTest` |
| Paralel suite yeşil, süre kazanımı raporda | ✅ | 968 test 6:27 (seri 21:44) |
| Deprecation 0 | ✅ | Paralel ve seri koşu çıktısında deprecation yok (önce 59) |
| Her model için factory (istisna listesi hariç) | ✅ | 116/116; `ModelFactoryCoverageTest` + `ModelFactoriesTest` (116 factory `create()`) |
| Bölüm C mimari testleri var ve yeşil | ✅ | `composer test:arch`: 22 test, 160 assertion yeşil |
| Pint temiz, Larastan baseline ile yeşil | ✅ | `pint --test` exit 0; `composer analyse` exit 0 (baseline 891) |
| Kapsam taban çizgisi ölçüldü ve belgelendi | ✅ | %58,32 satır; `TEST.md` §5, `DURUM.md` §5a, bu raporun 3. bölümü |
| `TEST.md`, `.cursorrules` DoD ve `DURUM.md` güncel | ✅ | Commit `8df0e69` |
| Rapor | ✅ | Bu dosya |

---

## 5. Kullanıcının elle yapacakları

1. **Başka makinelerde imajı yeniden derleyin:** `docker compose build app queue scheduler` ve ardından `docker compose up -d`. Dockerfile'a `pcov` eklendi; entrypoint'te `storage:link` var; postgres'e `max_locks_per_transaction=256` verildi.
2. **App container yeniden oluşturulursa `docker compose restart nginx` çalıştırın.** Nginx eski upstream IP'sini tutuyor ve 502 dönüyor (DURUM #31).
3. **Mevcut volume'lerde test veritabanını bir kez oluşturun:** `docker compose exec app composer test:db`. Yeni volume'lerde kendiliğinden oluşuyor.
4. **K7 şifresi:** Betikten kaldırılan `PGPASSWORD` değeri geliştirme ortamının varsayılan veritabanı şifresiyle aynı ve git geçmişinde duruyor. Bu değer herhangi bir gerçek ortamda (sunucu, staging) kullanılıyorsa o ortamın şifresi **değiştirilmeli**.
5. **`seed-scale.php`** artık `SCALE_SEED_PASSWORD` ortam değişkeni olmadan çalışmıyor; ölçek testinden önce bu değişkeni tanımlayın.
6. **CI:** Paralel koşu, kapsam ve `composer analyse` henüz CI'da yok; Adım 03B'de eklenecek. PHPStan'in CI'da şema anlık görüntüsü üretebilmesi için bir Postgres servisi gerekir.
7. **Eski yedekler** `docs/backups/` altında birikiyor (7 dump, ~55 MB). İhtiyaç dışı olanları siz silebilirsiniz; ben silmedim.

---

## 6. Kapsam dışı bulgular ve açık sorular

**Bulgular** (hepsi `DURUM.md` §6'ya işlendi):

1. **Hassas yüklemeler `public` diskte (DURUM #10, öncelikli).** CV, doküman, izin belgesi, masraf fişi ve talep ekleri `public` diske yazılıyor. K8 ile `public/storage` linki kurulduğu için bu dosyalar artık yolu bilen herkesin web'den erişebileceği durumda. Bugün canlıda böyle bir dosya yok; tablo yolları boş, `public` diskte 2 dosya var. Yine de ilk gerçek yüklemeden önce bu dosyaların `private` diske taşınması gerekiyor. **KVKK riski.**
2. **Kullanıcıya 400/403/404 yerine 500 dönen 33 hata çağrısı (DURUM #28).** Bu yerlerde `$this->error('...', null, NNN)` yazılmış; `error(string, int, $errors)` imzası nedeniyle TypeError oluşuyor. K5 kapsamındaki 3 tanesi düzeltildi. Kalanlar Portal (Duyuru, İzin, Performans, Profil, Eğitim, Pano), `LeaveRequestController`, `CompanyLedgerController` ve `LicensePackageController` içinde. PHPStan baseline'ında da görünüyorlar.
3. **Vekalet tenant kontrolü yok (DURUM #29).** `POST approvals/delegations` isteğinde `delegate_id` yalnız `exists:users,id` ile doğrulanıyor; vekilin aynı şirkette olduğu kontrol edilmiyor.
4. **`EmployeeDashboardController` yanıtları `response()->json` ile dönüyor, `ApiResponse` ile değil (DURUM #30).** Ayrıca pano toplam sayıları (company-level aggregate) şirketteki her kullanıcıya açık; `SELF_SERVICE_ROUTES` listesinde gerekçesiyle duruyor. Yetki modeli ürün kararı istiyor.
5. **Testler `storage/app/private` altında KVKK export ve yanıt dosyası bırakıyor (DURUM #32).** Şu an 361 dosya birikmiş, sayı her koşuda artıyor. Testlerin kendi dosyalarını temizlemesi gerekiyor.
6. **Kişisel veri export'u `local` diske yazılıyor; ayrıca bir `storage/{path}` serve route'u var.** İkisi de K2'deki disk politikasıyla birlikte, #10 kapsamında gözden geçirilmeli.
7. **`telescope_entries` büyük** (109 bin satır). Geliştirme ortamı; budama (`telescope:prune`) zamanlanmalı.
8. **`Timesheet` modeli kullanılmıyor ve `company_id`'li 16 tablonun modeli yok** (DURUM #14). `TenantModelGuardTest` izin listesinde gerekçesiyle duruyorlar.
9. **Factory tenant kontrolü yalnız doğrudan ebeveynlere bakıyor.** Dolaylı ilişkilerde (torun) şirket tutarlılığı test edilmiyor.
10. **Composer audit: 14 high** (laravel/framework email kuralı CRLF, commonmark, guzzle). Adım 04'e bırakıldı.

**Açık sorular:**

1. **K6:** Kullanıcı hesabı olmayan 6 eski demo personelin (DEM-035…040) `full_name`'i boş; listede personel koduyla görünüyorlar. `DemoDataSeeder` düzeltildi. Canlı demo verisinde bu 6 kaydın adını doldurmak için seeder'ı yeniden çalıştırmak (ya da tek seferlik bir komut) ister misiniz? Bu, canlı veriye yazma demek; onayınız olmadan yapmadım.
2. **Kapsam eşiği:** Bu adımda eşik zorunlu değildi. Adım 03B'de CI'a bir kapsam alt sınırı (örneğin taban çizgisinin altına düşmeme) eklensin mi?
3. **DURUM #10 (public disk):** Hassas yüklemelerin `private`'a taşınması Adım 04 güvenlik paketine mi alınsın, yoksa ayrı bir adım mı olsun?
