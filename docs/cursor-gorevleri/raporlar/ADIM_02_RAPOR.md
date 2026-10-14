# ADIM 02 Raporu — Ürün Adı Değişikliği: Alatax HR → Dobedan HR

**Tarih:** 2026-10-14 · **Dal:** `altyapi` · **Geri dönüş noktası:** tag `alatax-son` (dokunulmadı) · **Push:** yapılmadı

**Özet:** Adım 02 tamamlandı; tüm kabul kriterleri ✅. Ürün adı depo, Docker, veritabanı ve arayüzlerde Dobedan HR oldu. Veri kaybı yok: 155 tablonun hepsinde satır sayısı farkı sıfır (toplam 118.476 satır), 144 sequence doğru. `APP_KEY` değişmedi. Tam backend suite (785 test), frontend lint, build ve `check:brand` yeşil. Eski Docker volume'leri silinmedi; kaldırmaları kullanıcı onayına bırakıldı.

---

## 1. Yapılanlar

### Bölüm 0 — Ön hazırlık
- Görev dosyaları (`00_ORTAK_KURALLAR.md`, `ADIM_01…`, `ADIM_02…`) commit edildi (`88abeb4`).
- `backend/.env.bak-adim01` Bölüm G geçtikten sonra silindi (git dışı dosyaydı).

### Bölüm A — Teşhis (değişiklik öncesi, `88abeb4` üzerinde)

**A1. Depo taraması** (`git grep -i alatax`; istisnalar hariç: `docs/cursor-gorevleri/`, `docs/backups/`, `node_modules`, `vendor`, `dist`). Toplam **135 dosya / 394 satır**:

| Kategori | Dosya | Satır |
|---|---:|---:|
| Backend kodu (`app/`, `database/`) | 5 | 10 |
| Config / env (`config/`, `.env*.example`, `.gitignore`, `.gitattributes`) | 4 | 10 |
| Docker (`docker-compose.yml`, `Dockerfile`, `.env.docker.example`) | 3 | 30 |
| CI (`.github/workflows/ci.yml`) | 1 | 11 |
| Frontend kodu (`index.html`, layout, store, i18n, CSS, PWA dosyaları) | 26 | 34 |
| Paket tanımları (`package.json`, `composer.json/lock`, `pnpm-lock.yaml`, `package-lock.json`) | 8 | 20 |
| Testler (`tests/`, `phpunit.xml`) | 10 | 19 |
| Betikler (`scripts/`, `backend/scripts/`, `gorsel-kontrol`) | 22 | 72 |
| Aktif dokümanlar (`docs/` arşiv hariç, README'ler) | 21 | 70 |
| Arşiv dokümanları (`docs/arsiv/`) | 34 | 116 |
| `.cursor/` ve `.cursorrules` (`.cursor/` depoda izlenmiyor) | 1 | 2 |

Ek bulgular:
- **Dosya ve klasör adları:** 164 izlenen dosya `docs/arsiv/2026-10-alatax-donemi/` altındaydı. Ayrıca adında marka geçen bir dosya vardı: `qa/e2e/20-company-dashboard-alatax.png`.
- **`alatax.png` logo dosyası depoda yoktu.** Logo bir ikon bileşeni olduğu için `brand-logo.png` yeniden adlandırması uygulanacak bir şey bulmadı.
- **Git dışı env dosyaları:** kök `.env`, `backend/.env.ci-test` ve `backend/.env.testing` dosyalarında da `APP_NAME`, `POSTGRES_DB/USER` ve test DB adı geçiyordu. Değerleri okunmadan yalnız marka kalıpları değiştirildi.

**A2. Canlı veritabanı taraması.** Tüm `text`, `varchar`, `bpchar`, `json` ve `jsonb` kolonları tarandı (686 kolon, `ILIKE '%alatax%'`, json için `::text`):

| Tablo | Kolon | Satır |
|---|---|---:|
| `users` | `email` | 1 (`admin@alataxbilisim.com`) |

Tarama dışı bırakılanlar (yalnız sayıldı; satır sayıları `satir_sayilari_oncesi_adim02.txt` dosyasından):

| Tablo | Toplam satır | `alatax` içeren satır |
|---|---:|---:|
| `activity_logs` | 491 | 1 (tarihsel kayıt, değiştirilmedi) |
| `telescope_entries` | 82.895 | ~22.000 (debug kayıtları) |
| `telescope_entries_tags` | 32.375 | 0 |
| `telescope_monitoring` | 0 | 0 |

**A3. Redis, cache ve session önekleri.** Hepsi `Str::slug(env('APP_NAME'))` değerinden üretiliyor:
- `config/cache.php`: `{slug}-cache-`
- `config/database.php` (redis `options.prefix`): `{slug}-database-`
- `config/session.php` (cookie): `{slug}-session`

Eski değerler `alatax-hr-cache-`, `alatax-hr-database-` ve `alatax-hr-session` idi. `APP_NAME=Dobedan HR` ile otomatik olarak `dobedan-hr-*` oldular. Config varsayılanları da `'Dobedan HR'` yapıldı.

**A4. Adlandırma tablosuna uymayan bağlam:** yok. Dış servis kimliği ya da lisans anahtarı içinde marka geçmiyor. Değerlendirilen sınır durumlar şunlar (ayrıntı 6. bölümde):
- Capacitor `appId`
- KVKK anonimleştirme tuzu (salt)
- 2FA issuer adı

### Bölüm B — Yedek
- `docs/backups/oncesi_adim02_20261014-1129.dump`: 5.289.432 bayt, `pg_dump -Fc`. `pg_restore -l` çıktısı 155 TABLE, 155 TABLE DATA, 144 SEQUENCE SET.
- `docs/backups/oncesi_adim02_son_20261014-1142.dump`: 5.298.555 bayt. Bu, D1'deki son yedektir ve restore bundan yapıldı. `queue`, `scheduler` ve `nginx` durdurulduktan sonra alındı; yedek sırasında sayılar sabitti. `pg_restore -l` çıktısı 155 tablo.
- Yedekler git dışında (`docs/backups/` ignore'da).

### Bölüm C — Kod, yapılandırma ve dokümanlar
Adlandırma tablosu birebir uygulandı:
- Ürün adı `Dobedan HR`; Docker `name: dobedan-hr` ve `container_name: dobedan-hr-*`.
- DB `dobedan_hr`, test DB `dobedan_hr_testing`, PG kullanıcısı `dobedan`.
- Paketler `@dobedan/*` ve `dobedan-hr-frontend`; tarayıcı depolama anahtarları `dobedan_*`.
- Composer `dobedan/hr-backend`; alan adları `dobedan.com` ve `dobedan.local`; süper admin `admin@dobedan.com`.

İstisnalara dokunulmadı:
- GitHub adresi `github.com/alataxbilisim/alatax-hr` ve ona verilen bağlantılar
- tag `alatax-son`
- `.git` geçmişi
- `docs/backups/`
- `activity_logs`

Uygulama ayrıntıları:
- **Backend:**
  - `config/app.php`, `mail.php`, `cache.php`, `database.php`, `session.php` içindeki varsayılan adlar.
  - `SuperAdminSeeder` e-postası.
  - `TwoFactorService` issuer varsayılanı ve KVKK `AnonymizationHelper` tuzu.
  - Komutlar: `GorselFixtureCommand`, `SeedCompanyDefaultsCommand`.
  - Test sabitleri, `phpunit.xml` (`dobedan_hr_testing`).
  - `composer.json`: ad, açıklama ve anahtar kelimeler. `composer.lock` content-hash yenilendi.
  - `backend/README.md`: Laravel şablonu yerine kısa bir Dobedan HR README.
  - PHP namespace `App\` olarak kaldı.
- **Docker:** `docker-compose.yml` (`name`, `container_name`, varsayılan DB ve kullanıcı), `.env.docker.example`, `backend/Dockerfile` etiketleri, kök `.env`. `APP_KEY` ve `POSTGRES_PASSWORD` değişmedi.
- **CI:** `ci.yml` içinde test DB adı, kullanıcı ve healthcheck.
- **Frontend:**
  - Paket adları ve `@dobedan/*` filtreleri.
  - `index.html` `<title>` etiketleri.
  - i18n `common.appName` ve `auth.brandName`.
  - Layout marka metinleri.
  - localStorage anahtarları `dobedan_company_id` ve `dobedan_branch_id`.
  - PWA dosyaları (`manifest.json`, `sw.js` cache adı, `offline.html`) ve IndexedDB adı `dobedan-offline-db`.
  - Capacitor `com.dobedan.portal`.
  - `pnpm install` çalıştırıldı ve `pnpm-lock.yaml` yeniden üretildi.
  - Eski anahtarlar için geçiş kodu yazılmadı (görev gereği).
- **Betikler:** `scripts/` (Ubuntu activate/start/stop/deploy yolları `~/dobedan-hr`), `backend/scripts/`, `scripts/gorsel-kontrol/`.
- **Dokümanlar:**
  - Aktif dokümanlar, `docs/spec/`, `docs/denetim/`, `docs/arsiv/`, `README.md` ve `.cursorrules` güncellendi.
  - `docs/operasyon/DEPLOY_UBUNTU.md` dosyasına **"Alatax → Dobedan sunucu geçişi"** bölümü eklendi. Bölümün 10 adımı: yedek, `down` (`-v` olmadan), klasör adı değişikliği, `.env` düzenlemesi, postgres ve redis'i başlatma, `pg_restore`, satır sayısı karşılaştırması, `dobedan:rebrand-data`, frontend, test DB oluşturma, volume temizliği (onayla).
- **Dosya ve klasör adları (`git mv`):**
  - `docs/arsiv/2026-10-alatax-donemi/` → `docs/arsiv/2026-10-eski-ad-donemi/`, 164 dosya; izlenen dosya sayısı öncesi ve sonrası 164.
  - `20-company-dashboard-alatax.png` → `20-company-dashboard-dobedan.png`.
- **Ek düzeltme:**
  - Superadmin ve portal giriş ekranlarındaki sabit başlık toplu değişimden sonra yalnız "Dobedan" kalmıştı. Firma panelindeki desenle aynı şekilde `t('auth:brandName')` ("Dobedan HR") kullanılıyor (`5bd5ed1`).
  - `frontend/package.json` içinde ara bir düzenlememin birleştirdiği `lint` ve `gorsel-kontrol` satırları ayrıldı (`af64113`).

### Bölüm D — Veritabanı ve Docker geçişi
1. **Son yedek:** `oncesi_adim02_son_20261014-1142.dump` (Bölüm B).
2. **Satır sayısı taban çizgisi:** `docs/backups/satir_sayilari_oncesi_adim02.txt`, 155 satır, `tablo|sayı` biçiminde.
3. **Eski yığın:** `docker compose -p alatax-hr down` çalıştırıldı, **`-v` kullanılmadı**. Eski volume'ler `alatax-hr_postgres_data` ve `alatax-hr_redis_data` duruyor.
4. **Yeni yığın:** önce yalnız `postgres` ve `redis` başlatıldı (`up -d --build`). Queue ve scheduler'ın (Telescope) sayıları değiştirmemesi için restore ve sayım yalnız bu iki servis açıkken yapıldı.
5. **Restore:** `pg_restore --no-owner --role=dobedan -U dobedan -d dobedan_hr`. Çıkış kodu 0, hata ya da uyarı çıktısı **boş** (çıktı 3. bölümde). Tablo sayısı 0'dan 155'e çıktı; tablo sahibi `dobedan`.
6. **Satır sayıları:** `satir_sayilari_sonrasi_adim02.txt` ile `Compare-Object` sonucu **0 farklı satır**. 155/155 tablo; toplam 118.476 = 118.476.
7. **Sequence'lar:** 144 serial/identity kolonun hepsinde `max(id) ≤ last_value`; ihlal **0**.
8. **Redis:** yeni volume `dobedan-hr_redis_data` kullanılıyor. 18 anahtarın hepsi `dobedan-hr-database-` önekli; `alatax` içeren anahtar 0.

Ardından tüm yığın `docker compose up -d --build` ile açıldı (6 servis). `dobedan_hr_testing` veritabanı `createdb` ile oluşturuldu; bu, Adım 01'de onaylanan test DB adımının yeni adla tekrarıdır.

### Bölüm E — Veritabanındaki `alatax` verilerinin dönüştürülmesi
- **Servis:** `app/Services/Rebrand/BrandDataRebrandService.php`.
  - İşlemin tamamı tek `DB::transaction` içinde.
  - Kolonlar `Schema::getTables()` ve `getColumns()` ile keşfediliyor; arama `whereLike` ile (ham SQL yok).
  - Güncelleme birincil anahtar üzerinden yapılıyor.
  - Atlanan tablolar: `activity_logs`, `telescope_*` ve altyapı tabloları (`migrations`, `cache*`, `sessions`, `jobs`, `job_batches`, `failed_jobs`).
  - Atlanıp raporlanan değerler:
    - şifreli veya hash'li değerler (`eyJpdiI6…`, `$2y$…`) ve serialize PHP değerleri
    - tek kolonlu unique indekste çakışacak değerler (`unique_conflict`; örneğin e-posta tekilliği)
  - GitHub adresi korunuyor.
  - Dönüşüm sırası: `alataxbilisim.com` → `dobedan.com`, `ALATAX HR` → `Dobedan HR`, `ALATAX` → `DOBEDAN`, `Alatax` → `Dobedan`, `alatax` → `dobedan`.
- **Komut:** `php artisan dobedan:rebrand-data {--dry-run}`. İdempotent; tablo, kolon ve satır sayısını, atlananları ve nedenlerini listeliyor.
- **Test (`tests/Feature/RebrandDataCommandTest.php`, 3 test):**
  - Dry-run hiçbir şeyi değiştirmiyor.
  - Gerçek çalıştırma dönüştürüyor, e-posta çakışmasını atlayıp raporluyor, `activity_logs` kaydına ve GitHub adresine dokunmuyor.
  - İkinci çalıştırma "Değişecek satır yok." diyor.
- **Canlı çalıştırma:** önce dry-run, sonra gerçek çalıştırma, sonra tekrar dry-run (çıktılar 3. bölümde). 1 satır değişti (`users.email` → `admin@dobedan.com`); çakışma yok.
- **Yeniden tarama:** hariç tablolar dışında **0 eşleşme**.

### Bölüm F — Kalıcı koruma
- **`tests/Architecture/BrandGuardTest.php`:**
  - `app/`, `config/`, `database/`, `routes/`, `resources/`, `tests/` altında içerikte ve dosya adlarında büyük/küçük harf duyarsız eski marka adını arıyor.
  - `brand-guard:allow-start` ve `brand-guard:allow-end` işaretleri arasını yok sayıyor; kapatılmamış işaret hata sayılıyor.
  - Kendi dosyasını taramıyor.
- **`scripts/check-brand.mjs` ve `pnpm check:brand`:**
  - Repo genelinde izlenen ve izlenmeyen (ignore'da olmayan) dosyaları `git ls-files` ile tarıyor.
  - İzinli ifadeler: GitHub adresi ve `alatax-son`. Hariç yollar: `docs/cursor-gorevleri/`, `docs/backups/`, `node_modules`, `vendor`, `dist`.
  - Aynı allow işaretlerini tanıyor. Negatif test: geçici dosyada exit 1, dosya silindikten sonra exit 0.
- **CI:** frontend job'una `Brand scan (Dobedan HR)` adımı (`pnpm check:brand`) eklendi.
- **`.cursorrules`:** ürün adı kuralı ve istisnaları eklendi (allow işaretleri içinde).

### Bölüm G — Doğrulama
Sonuçlar 3. bölümde, kriter eşlemesi 4. bölümde.

### Değişen ve eklenen dosyalar (özet)
- `79f626b` — 90 dosya:
  - kök: `.env.docker.example`, `.gitattributes`, `.gitignore`, `README.md`, `docker-compose.yml`
  - `.github/workflows/ci.yml`
  - `backend/`: `.env.example`, `.env.testing.example`, `Dockerfile`, `README.md`, `composer.json/lock`, `phpunit.xml`
  - `backend/config/`: `app`, `cache`, `database`, `mail`, `session`
  - `backend/app/`: 4 dosya (2 komut, `TwoFactorService`, `AnonymizationHelper`)
  - `SuperAdminSeeder`, 9 test dosyası, 11 backend betiği
  - frontend: 33 dosya (3 `index.html`, 4 `package.json`, layout'lar, store'lar, i18n, CSS, PWA, Capacitor, `api.ts`), `pnpm-lock.yaml`
  - `scripts/`: 13 dosya
- `bb99aa4` — 194 dosya: dokümanlar ve arşiv klasörünün `git mv` ile yeniden adlandırılması (164 yeniden adlandırma), `DEPLOY_UBUNTU.md` geçiş bölümü, `DURUM.md`, `docs/README.md`.
- `7c06fc0` — `BrandDataRebrandService.php`, `RebrandDataCommand.php`, `RebrandDataCommandTest.php` (yeni).
- `af64113` — `BrandGuardTest.php` ve `scripts/check-brand.mjs` (yeni); `.cursorrules`, `ci.yml`, `frontend/package.json`.
- `5bd5ed1` — `frontend/apps/{superadmin,portal}/src/layouts/AuthLayout.tsx`.
- Bu rapor.
- Git dışı: kök `.env`, `backend/.env.ci-test`, `backend/.env.testing` (marka kalıpları), `docs/backups/*` (yedekler ve sayım dosyaları). `backend/.env.bak-adim01` silindi.

---

## 2. `git log --oneline` (Adım 02)

```
(bu commit) docs: Adım 02 raporu
5bd5ed1 fix(ui): superadmin ve portal giris basliginda urun adini t() ile goster
af64113 test: eski marka korumasi (BrandGuardTest, check-brand, CI, .cursorrules)
7c06fc0 feat(rebrand): dobedan:rebrand-data komutu ve testi
bb99aa4 docs: dokümanları Dobedan adlandırmasına çevir, arşiv klasörünü yeniden adlandır
79f626b refactor: ürün adını Dobedan HR olarak değiştir (kod, config, Docker, CI, frontend)
88abeb4 docs: Cursor görev dosyalarını depoya ekle
a404b1c docs: Adım 01 raporu ve DURUM.md güncellemesi   ← Adım 01 sonu
```

---

## 3. Doğrulama komutları ve çıktıları

**Restore (D5).** Betik postgres container'ında çalıştı:

```
user=dobedan db=dobedan_hr
tables_before=0
pg_restore --no-owner --role="$POSTGRES_USER" -U "$POSTGRES_USER" -d "$POSTGRES_DB" /tmp/restore.dump
pg_restore_exit=0
--- restore log (errors/warnings) ---
--- end log ---
tables_after=155
owner_check=dobedan
```

**Satır sayısı ve sequence (D6, D7):**

```
before_tables=155 after_tables=155
diff_lines=0
total_rows_before=118476 after=118476
sequences_checked=144
bad_sequences=0
```

**`docker compose ps` (G1):**

```
dobedan-hr-app         running
dobedan-hr-nginx       running (healthy)
dobedan-hr-postgres    running (healthy)
dobedan-hr-queue       running
dobedan-hr-redis       running (healthy)
dobedan-hr-scheduler   running
```

Volume'ler: `alatax-hr_postgres_data`, `alatax-hr_redis_data` (korunuyor), `dobedan-hr_postgres_data`, `dobedan-hr_redis_data`.

**Rebrand dry-run (E):** `docker compose exec app php artisan dobedan:rebrand-data --dry-run`

```
DRY-RUN — hiçbir satır değiştirilmedi.
+-------+-------+-------+
| Tablo | Kolon | Satır |
+-------+-------+-------+
| users | email | 1     |
+-------+-------+-------+
Toplam: 1 satır (değişecek), 0 atlandı.
```

**Gerçek çalıştırma:** `php artisan dobedan:rebrand-data`

```
Dönüşüm uygulandı.
| users | email | 1     |
Toplam: 1 satır (değişti), 0 atlandı.
```

**İkinci çalıştırma (idempotentlik):**

```
DRY-RUN — hiçbir satır değiştirilmedi.
Değişecek satır yok.
Toplam: 0 satır (değişecek), 0 atlandı.
```

**DB yeniden tarama (E):**

```
DB=dobedan_hr columns_scanned=686
(HIT satırı yok)
SKIP|activity_logs|rows=491|alatax_matches=1
SKIP|telescope_entries|rows=86124|alatax_matches=22196
SKIP|telescope_entries_tags|rows=32378|alatax_matches=0
SKIP|telescope_monitoring|rows=0|alatax_matches=0
```

**`artisan about` ve config (G2):**

```
Application Name ........ Dobedan HR
Laravel Version ......... 12.43.1
PHP Version ............. 8.2.34
Database ................ pgsql   Cache/Queue/Session ... redis
db_name=dobedan_hr  driver=pgsql
cache_prefix=dobedan-hr-cache-  redis_prefix=dobedan-hr-database-  session_cookie=dobedan-hr-session
mail_from_name=Dobedan HR
app_key_sha16=d1ca474b219cbf37   (Adım 01 ile aynı özet; anahtarın kendisi yazılmadı)
```

**`/up` (G3):** `GET http://localhost:8000/up` → **200**.

**Arayüzler (G3).** Cursor tarayıcısıyla kontrol edildi:

| Uygulama | Sekme başlığı | Giriş sayfası başlığı |
|---|---|---|
| Superadmin :3001 | Dobedan HR - SuperAdmin Panel | Dobedan HR |
| Firma :3002 | Dobedan HR - Firma Paneli | Dobedan HR |
| Portal :3003 | Dobedan HR - Personel Portalı | Dobedan HR |

**Giriş ve personel sayısı (G3, G4):**

```
super_admin_login (admin@dobedan.com) success=True type=super_admin token_present=True requires_2fa=False
company_admin_login (admin@demo.test) success=True type=company_admin
employees total: Demo Firma 40 + Otel B 10 + Otel C 10 = 60   (taşıma öncesi employees=60)
```

- Firma paneli arayüzünden giriş yapıldı; Personel Listesi "Toplam 40 kayıt" gösterdi.
- Superadmin arayüzünden `admin@dobedan.com` ile giriş yapıldı; dashboard 3 firma gösterdi.
- Şifreler yazılmadı.

**2FA (G5):** `two_factor_enabled_users=0`. 2FA'sı açık kullanıcı olmadığı için bu adım uygulanamadı. `APP_KEY` özeti aynı olduğundan şifreli alanların çözülmesi etkilenmez.

**Frontend (G6):**

```
pnpm lint         → exit 0
pnpm build        → exit 0 (superadmin 5.5s, portal 15.1s, company 29.2s)
pnpm check:brand  → "OK: no old brand references found", exit 0
```

**Backend testleri (G7):**

```
phpunit tests/Architecture tests/Feature/RebrandDataCommandTest.php
  ✔ BrandGuard: Backend sources do not contain old brand
  ✔ DatabaseDriverGuard (3 test)
  ✔ RebrandDataCommand: dry run / real run / idempotent (3 test)
  OK (7 tests, 22 assertions)

phpunit (tam suite) → OK, but there were issues!
  Tests: 785, Assertions: 3503, PHPUnit Deprecations: 59   (exit 0)
```

Adım 01'de 781 test vardı; `BrandGuardTest` ve 3 rebrand testi eklendi. 59 deprecation uyarısı Adım 01'de de vardı.

**Son tarama (G8).** `git grep -i alatax -- . ':!docs/cursor-gorevleri'` → 52 satır, hepsi istisna:
- GitHub adresi bağlantıları (arşiv ve denetim raporları) ile `DURUM.md` ve `.cursorrules` içindeki `alatax-son`: 24 satır.
- Allow işaretleri içindeki koruma ve geçiş araçları: 28 satır.
  - `BrandDataRebrandService` (6), `RebrandDataCommandTest` (9), `BrandGuardTest` (1), `check-brand.mjs` (1).
  - `DEPLOY_UBUNTU.md` geçiş bölümü (11).
- Dosya adlarında `alatax`: 0.

---

## 4. Kabul kriterleri

| Kriter | Durum | Kanıt |
|---|:-:|---|
| Bölüm A teşhis tabloları raporda | ✅ | §1 Bölüm A: kategori tablosu, DB tablosu, hariç tablo sayıları, prefix kaynağı |
| Yedek alındı ve doğrulandı | ✅ | 2 dump; `pg_restore -l` 155 tablo; restore exit 0 |
| Depoda (istisnalar hariç) `alatax` sıfır | ✅ | §3 G8; `pnpm check:brand` exit 0; `BrandGuardTest` OK |
| Yığın `dobedan-hr`, DB `dobedan_hr`, kullanıcı `dobedan`; satır farkı sıfır; sequence'lar doğru | ✅ | `ps` 6 × `dobedan-hr-*`; `about` ve `db_name`; `owner_check=dobedan`; `diff_lines=0`; `bad_sequences=0` |
| DB verisinde (`activity_logs`, `telescope_*` hariç) `alatax` sıfır | ✅ | Yeniden tarama: HIT yok |
| `BrandGuardTest` + `check:brand` + CI adımı var ve geçiyor | ✅ | `af64113`; testler OK; check:brand exit 0; `ci.yml` "Brand scan (Dobedan HR)" |
| Arayüzlerde Dobedan HR görünüyor, giriş çalışıyor, APP_KEY değişmedi | ✅ | 3 UI başlık tablosu; superadmin ve firma girişleri; personel 60; `app_key_sha16` aynı |
| Rapor `docs/cursor-gorevleri/raporlar/ADIM_02_RAPOR.md` | ✅ | Bu dosya |

---

## 5. Kullanıcının elle yapacakları

1. **Eski volume'leri kaldırmak** (birkaç gün sorunsuz çalıştıktan sonra, onayınızla): `docker volume rm alatax-hr_postgres_data alatax-hr_redis_data`. Geri alınamaz. Yedek `docs/backups/oncesi_adim02_son_20261014-1142.dump` dosyasında. `alatax-hr_mysql_data` zaten yoktu.
2. **Ubuntu sunucusunun geçişi:** `docs/operasyon/DEPLOY_UBUNTU.md` → "Alatax → Dobedan sunucu geçişi" (10 adım). Sunucuda `.env` içinde `APP_NAME`, `POSTGRES_DB` ve `POSTGRES_USER` güncellenmeli; şifre ve `APP_KEY` değişmemeli.
3. **Push:** `altyapi` dalı yerelde; push yapılmadı.
4. **Tarayıcı oturumları:**
   - Token anahtarı (`token`) değişmedi ve `personal_access_tokens` tablosu taşındı; mevcut oturumlar açık kalıyor.
   - Firma ve şube seçimi yeni `dobedan_company_id` / `dobedan_branch_id` anahtarlarına bir kez yeniden yazılıyor.
   - Eski `alatax_*` anahtarları tarayıcıda zararsız artık olarak kalıyor (görev gereği geçiş kodu yok).
5. **GitHub deposunun adı** (`alataxbilisim/alatax-hr`) istisna olarak bırakıldı. Ad değişecekse GitHub tarafında yapılmalı; ardından `check-brand.mjs`, `.cursorrules` ve arşiv bağlantıları güncellenmeli.

---

## 6. Kapsam dışı bulgular ve açık sorular

**Bu adımda verilen kararlar (gerekçeli):**
- **Arşiv klasörü `2026-10-eski-ad-donemi` oldu,** `dobedan-donemi` olmadı: içerik yeniden adlandırma öncesi döneme ait.
- **`docs/cursor-gorevleri/` değiştirilmedi** ve `check-brand` kapsamı dışında. Görev metinleri ve raporlar tarihsel kayıt. Sonuç olarak `ADIM_01_RAPOR.md` içindeki arşiv yolları eski adı gösteriyor.
- **Capacitor `appId` → `com.dobedan.portal`:** depoda android ve ios native projesi yok, yayınlanmış uygulama kimliği etkilenmiyor.
- **KVKK anonimleştirme tuzu `dobedan-kvkk-` oldu.** Yalnız bundan sonra üretilecek takma ad etiketlerini etkiler; geçmiş etiketler arama için yeniden hesaplanmıyor.
- **2FA issuer varsayılanı Dobedan HR oldu.** Yalnız authenticator uygulamasındaki etiket değişir; kodların geçerliliği etkilenmez. Şu an 2FA'lı kullanıcı yok.
- **Portal PWA:**
  - `sw.js` cache adı ve IndexedDB adı (`dobedan-offline-db`) değişti.
  - Eski IndexedDB'de bekleyen çevrimdışı işlem kuyruğu varsa yeni ada taşınmaz. Test aşamasında risk düşük.
  - Eski service worker cache'i tarayıcıda kalabilir.

**Kapsam dışı bulgular (dokunulmadı):**
1. **Pint:** yaklaşık 170 backend dosyası `pint --test` kontrolünden geçmiyor (çoğu CRLF çalışma kopyası `line_ending`; bir kısmı `unary_operator_spaces` ve `single_quote`). Adım 02 öncesinden var. Yeni eklenen 4 PHP dosyası Pint'ten geçiyor.
2. **Sabit şifre:** `backend/scripts/uzun-kosu/seed-scale.php` ve `backend/scripts/schema-dump-prune.sh` içinde sabit bir `PGPASSWORD` değeri var (değer yazılmadı). Ortam değişkenine taşınmalı.
3. **Vite uyarısı:** superadmin ve company dev sunucuları açılışta "Failed to resolve dependency: @tanstack/react-query, present in client 'optimizeDeps.include'" uyarısı veriyor. `vite.config.ts` içindeki bu `include` girdisi Adım 02 öncesinden var; build ve çalışma etkilenmiyor.
4. **"Unknown" personel adları:** firma panelindeki personel listesinde "İşten Çıkmış" durumundaki bazı personeller ad soyad yerine "Unknown" ve boş e-posta gösteriyor (örn. DEM-035…040). Veri ya da gösterim fallback'i kaynaklı, rebrand ile ilgisi yok (rebrand yalnız `users.email` 1 satırını değiştirdi). İncelenmesi önerilir; anonimleştirilmiş kayıtlarsa beklenen davranış olabilir.
5. **Telescope:** `telescope_entries` 86 bin satır ve DB'nin büyük kısmı. Eski marka içeren yaklaşık 22 bin debug kaydı duruyor (görev gereği dönüştürülmedi). `telescope:prune` ile temizlenmesi önerilir.
6. **Superadmin ilk giriş:** ilk "Giriş Yap" tıklaması sayfayı ilerletmedi; form yeniden gönderilince login isteği 200 döndü ve dashboard açıldı. O sırada tam test paketi aynı container'da çalışıyordu ve API yavaştı. Tekrarlanırsa ayrıca bakılmalı.

**Açık sorular:**
- Eski Docker volume'leri ne zaman kaldırılsın? Öneri: bir hafta sorunsuz çalışmadan sonra.
- GitHub deposu adı da değişecek mi? Değişecekse istisna listesi güncellenmeli.
