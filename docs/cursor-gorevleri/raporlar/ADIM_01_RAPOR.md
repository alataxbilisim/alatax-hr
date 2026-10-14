# ADIM 01 RAPORU — Hazırlık, Doküman Arşivi, MySQL/MariaDB/SQLite'ın Kaldırılması

**Tarih:** 2026-10-13 / 2026-10-14 · **Dal:** `altyapi` (kaynak: `faz4-form-engine`) · **Geri dönüş etiketi:** `alatax-son` → `f006f93`

**Özet:** Adım 01 eksiksiz tamamlandı. Tüm kabul kriterleri ✅. Veritabanı artık yalnız PostgreSQL 16; Docker 6 servisle ayakta; canlı veri ve `APP_KEY` değişmedi; tam backend suite (781 test), frontend lint ve build yeşil.

---

## 1. Yapılanlar

### Bölüm A — Geri dönüş noktası ve yedek

| # | İş | Sonuç |
|---|---|---|
| A1 | `git status` kontrolü | İzlenen dosyalarda değişiklik yoktu (yalnız izlenmeyen dosyalar vardı) → devam edildi. |
| A2 | Etiket | `git tag alatax-son` (local) → `f006f93` (`fix(export): DataScope birligi, chunk/stream ve export registry`). |
| A3 | Çalışma dalı | `faz4-form-engine` → `altyapi`. `main`e dokunulmadı. |
| A4 | PostgreSQL yedeği | `docs/backups/oncesi_adim01_20261013-1644.dump` — **4.411.220 bayt**, `pg_restore -l` ile **155 TABLE** girdisi (beklenen 155 ✅). SHA-256 ön eki `c523ca495dc8f11e`. `docs/backups/` git dışı (`.gitignore:103`). |
| A5 | Docker MySQL tespiti (salt okuma) | Container'da yalnız MySQL sistem şemaları vardı; **uygulama veritabanı/verisi yok** → `mysqldump` gerekmedi. |
| A6 | Aktif `APP_KEY` kaynağı | Container `config('app.key')` SHA-256[0:16] = `d1ca474b219cbf37`; kök `.env` = `d1ca474b219cbf37` ✅; `backend/.env` = `b53603ef75cb839f` (farklı, kullanılmıyordu). **Aktif anahtar kök `.env`'den geliyor.** Değer hiçbir yere yazılmadı. |

### Bölüm B — Dokümanlar

**B1 — Sınıflandırma tablosu** (`git mv`; izlenmeyenler `mv` + `git add`)

| Dosya / klasör | Hedef | Gerekçe |
|---|---|---|
| `MODUL_SPEC.md` | `spec/` | Hedef modül tasarımı |
| `AKIS_SPEC.md` | `spec/` | Hedef iş akışı tasarımı |
| `AKIS_SEMA_EDITOR.html` | `spec/` | Akış şeması editörü (AKIS_SPEC eki) |
| `SISTEM_ISLEYIS.md` | `spec/` | Yapılandırılabilirlik sınırı ve sistem işleyişi |
| `TASARIM_REHBERI.md` | `spec/` | UI/tasarım hedefi |
| `I18N.md` | `spec/` | i18n kuralları |
| `ENTEGRASYON_SPEC.md` | `spec/` | Entegrasyon hedefi |
| `AYAR_MOTORU.md` | `spec/` | Settings Registry hedefi |
| `EXPORT_KAPSAM_BIRLIGI.md` | `spec/` | Export kapsam kuralları |
| `EXPORT_GUVENLIK_VE_ENTEGRASYON_EKLERI.md` | `spec/` | Export güvenlik ekleri |
| `DEPLOY_UBUNTU.md` | `operasyon/` | Kurulum/deploy belgesi |
| `DURUM_RAPORU_CLAUDE.md` | `denetim/2026-10-13/` | Bağımsız denetim (taban çizgisi) |
| `DURUM_RAPORU_CURSOR.md` | `denetim/2026-10-13/` | Bağımsız denetim (taban çizgisi) |
| `claude-denetim/` (13 dosya) | `denetim/2026-10-13/` | Denetim ham çıktıları; gizli bilgi taraması temiz |
| `BURADAN_BASLA.md` | `arsiv/2026-10-alatax-donemi/` | Eski başlangıç rehberi; yerini `docs/README.md` aldı |
| `ROADMAP.md` | arşiv | Eski yol haritası; güncel karşılığı `DURUM.md` + `cursor-gorevleri/` |
| `GUNCEL_DURUM_RAPORU.md` | arşiv | Yerini `DURUM.md` aldı |
| `FAZ1/2/3/4/4A/4C/5_RAPOR.md`, `FAZ_A/B_RAPOR.md` | arşiv | Tarihçe raporları |
| `FAZ6_ONCESI_KAPATMA.md`, `FAZ_G_KAPANIS_OZET.md` | arşiv | Tarihçe |
| `KVKK/OFFBOARDING/PDKS/PORTAL/QA/UCRET_RAPOR.md` | arşiv | Modül tur raporları (tarihçe) |
| `GRUP_MIMARI_TESHIS.md` | arşiv | Teşhis raporu (tarihçe) |
| `DENETIM_TURU.md`, `TEST_TURU.md`, `TEMEL_SAGLAMLASTIRMA.md` | arşiv | Tur raporları |
| `YENIDEN_CERCEVELEME.md`, `DOCS_ANALIZ.md` | arşiv | Tarihçe/analiz |
| `CURSOR_GORSEL_KONTROL_PROMPT.md` | arşiv | Eski prompt |
| `UZUN_KOSU_RAPORU.md` + `uzun-kosu-*` log/txt/json | arşiv | Ölçüm çıktıları (log'lar git dışı) |
| `docs/*.json` ölçüm çıktıları (clean-install, explain, jobs/memory/pdks/query-health/scale summary, pdks-uids, denetim-raw) | arşiv | Ölçüm çıktıları |
| `qa/` (42 izlenen + ~197 izlenmeyen PNG) | arşiv | QA ekran görüntüleri |
| `gorsel-kontrol/` (80 izlenen + izlenmeyen `.secrets.local`) | arşiv | Görsel kontrol turu |
| Kökteki 8 `backend-*.log` | `arsiv/…/suite-loglari/` | Suite log'ları (git dışı) |
| `backend/database/migrations_backup_pre_faz1a/` (64 dosya) | `arsiv/…/migrations_backup_pre_faz1a/` | Kod klasöründen çıkarıldı; `.gitignore`'a `docs/arsiv/**/migrations_backup_*/` eklendi |

Listede arşive düşüp spec'e alınan dosya olmadı. `docs/arsiv/2026-08/` ve mevcut arşiv içeriği yerinde bırakıldı. `docs/cursor-gorevleri/` ve `docs/backups/` dokunulmadı.

**B2 —** `docs/DURUM.md` (ürün çerçevesi; A1–A7 ve B1–B14 modülleri Backend/Frontend/Test gerçek durumu — hiçbiri "Tam" değil; motorlar ve olgunluk; 12 kilitli karar, "MySQL tamamen kaldırıldı" dahil; kritik hatalar K1–K6; 26 maddelik tek borç listesi) ve `docs/README.md` (klasör yapısı, aktif dosya amaçları, okuma sırası `00_ORTAK_KURALLAR → DURUM.md → .cursorrules → spec/`) yazıldı.

**B3 — Referanslar ve temizlik**
- Eski yollar güncellendi: `README.md`, `.cursorrules` (ROADMAP → `DURUM.md`/`README.md`/`cursor-gorevleri`; I18N/SISTEM_ISLEYIS/ENTEGRASYON_SPEC → `docs/spec/`), `spec/SISTEM_ISLEYIS.md`, `spec/TASARIM_REHBERI.md`, `spec/MODUL_SPEC.md`, `operasyon/DEPLOY_UBUNTU.md`, `scripts/check-mojibake.mjs`, `GorselFixtureCommand.php` (yardım metni). Koddan arşive referans kalmadı.
- `frontend/_e_lint_*.txt`, `frontend/_e_tsc_*.txt` → `git rm`. `scripts/tmp-*` silindi.

**B3 — Betik önerileri ve uygulanan karar**

| Betik | Karar | Gerekçe |
|---|---|---|
| `backend/scripts/find-user-company-id.php` | Arşiv (`backend/scripts/arsiv/`) | Tek seferlik veri düzeltme |
| `backend/scripts/fix-user-where-company-id.php` | Arşiv | Tek seferlik veri düzeltme |
| `backend/scripts/strip-employee-position-attr.php` | Arşiv | Tek seferlik veri düzeltme |
| `scripts/ubuntu-fix-migrate.sh` | Arşiv (`backend/scripts/arsiv/`) | Tek seferlik deploy düzeltmesi |
| `backend/scripts/denetim/run-denetim.php` | Commit (kalıcı araç) | Denetim ölçümü |
| `backend/scripts/uzun-kosu/*.php`, `*.sh` | Commit (kalıcı araç) | Ölçek/performans ölçümleri |
| `backend/scripts/uzun-kosu/*.json`, `*.txt` çıktıları | Sil | Birebir kopyaları arşivde |
| `backend/scripts/show-db-config.php` | **Sil** | DB şifresini ekrana basıyordu (gizli bilgi riski) |

### Bölüm C — MySQL/MariaDB/SQLite kaldırma

| Dosya | Değişiklik |
|---|---|
| `docker-compose.yml` | `mysql` servisi, `mysql_data` volume tanımı, `app → mysql` bağımlılığı kaldırıldı; yorum "yalnız PostgreSQL 16". |
| `backend/Dockerfile` | `pdo_mysql` kaldırıldı; imaj yeniden derlendi. |
| `backend/config/database.php` | `connections` yalnız `pgsql`; varsayılan `env('DB_CONNECTION', 'pgsql')`; Redis bölümüne dokunulmadı. |
| `backend/config/queue.php`, `telescope.php` | Varsayılan bağlantılar `pgsql`. |
| `backend/app/Providers/AppServiceProvider.php` | Laravel 11+ çerçeve varsayılan bağlantıları (sqlite/mysql/mariadb/sqlsrv) config'e geri birleştirdiği için `register()` başında yalnız `pgsql` bırakılıyor. |
| `backend/app/Support/PortableEnum.php` | sqlite/mysql dalları kaldırıldı; yalnız PG CHECK constraint. |
| `backend/database/database.sqlite` | Silindi (izlenmeyen dosyaydı). |
| `backend/database/.gitignore` | Yalnız sqlite satırı içerdiği için `git rm`. |
| `.gitignore`, `.dockerignore`, `.gitattributes` | sqlite satırları kaldırıldı. |
| `.env.docker.example` | MySQL bloğu kaldırıldı; kök `.env`'in tek kaynak olduğu belirtildi; eksik non-DB anahtarlar eklendi. |
| `backend/.env.example` | Legacy MySQL/SQLite yorum blokları kaldırıldı. |
| `backend/composer.json` | `post-create-project-cmd` içindeki sqlite `touch` satırı kaldırıldı (`ext-pdo_mysql` gereksinimi yoktu). |
| `.github/workflows/ci.yml` | MySQL izi yoktu (son taramada sıfır). |
| 5 controller | MySQL SQL'i PostgreSQL'e çevrildi (aşağıda). |
| `backend/tests/Architecture/DatabaseDriverGuardTest.php` | Yeni kalıcı koruma (3 test). |
| `backend/phpunit.xml` | `Architecture` test suite'i eklendi. |
| `backend/tests/Feature/PostgresReportSqlTest.php` | Düzeltilen uçlar için 7 feature testi. |
| `.cursorrules` | "MySQL legacy korunur, ASLA silinmez / geri dönüş yolu açık kalsın" kuralları **bilinçli olarak tersine çevrildi** → "Yalnız PostgreSQL 16; MySQL/MariaDB/SQLite yasak"; yasaklar listesine "MySQL/MariaDB/SQLite sürücüsü, servisi veya bağımlılığı eklemek". |
| `README.md` | MySQL/XAMPP akışı kaldırıldı; 6 servis; kök `.env` tek kaynak; XAMPP/host PHP "desteklenmez". |
| `docs/operasyon/DEPLOY_UBUNTU.md`, `docs/spec/MODUL_SPEC.md` | MySQL ifadeleri PostgreSQL'e çevrildi. `backend/README.md`'de MySQL izi yoktu. |

**Git dışı ortam dosyaları (commit edilmedi):**
- Kök `.env`: 5 `MYSQL_*` satırı kaldırıldı; `backend/.env`'de olup kökte olmayan 20 non-DB anahtar (log, session, broadcast, filesystem, mail scheme, boş AWS, `VITE_APP_NAME` vb.) eklendi — böylece `artisan about` çıktısı birebir korundu.
- `backend/.env` → `backend/.env.bak-adim01` (silinmedi; `.env.*` ile git dışı). Aktif anahtar kökte olduğu için A6 koşulu sağlandı.
- `backend/.env.ci-test`: `DB_CONNECTION` sqlite → pgsql (`alatax_hr_testing`).

**5 controller — SQL dönüşümü**

| Controller | Eski (MySQL) | Yeni (PostgreSQL) |
|---|---|---|
| `EmployeeController::getStats` | `TIMESTAMPDIFF(YEAR, …, CURDATE())`, çift tırnaklı string | `date_part('year', age(CURRENT_DATE, …))`, tek tırnak, `as "group"`/`"range"` |
| `EmployeeReportController::aggregateData` | `YEAR()`, `DATE_FORMAT(…,'%Y-%m')`, `TIMESTAMPDIFF` | `CAST(EXTRACT(YEAR FROM …) AS integer)`, `to_char(…, 'YYYY-MM')`, `date_part/age` |
| `EmployeeDashboardController::getAggregatedData` | `YEAR(hire_date/birth_date)`, `TIMESTAMPDIFF` | `CAST(EXTRACT(YEAR FROM …) AS integer)`, `date_part/age` |
| `Documents/ReportController::buildQueryByDimension` | `YEAR()`, `MONTH()` | `CAST(EXTRACT(YEAR/MONTH FROM created_at) AS integer)` |
| `Recruitment/ReportController::trends` | `DATE_FORMAT` (`%Y-%m-%d`, `%x-%v`, `%Y-%m`) | `to_char` (`YYYY-MM-DD`, `IYYY-IW`, `YYYY-MM`) |

## 2. Commit listesi

```
$ git log --oneline alatax-son..HEAD
a8fc10c docs: kuralları ve dokümanları yalnız PostgreSQL 16'ya göre güncelle
e5432eb fix(reports): MySQL'e özgü rapor SQL'ini PostgreSQL'e çevir
ac99f24 chore(db): MySQL/MariaDB/SQLite altyapısını kaldır, yalnız PostgreSQL 16
f09ea8c docs: tek durum belgesi DURUM.md ve doküman haritası README.md
0631dab chore: doküman referanslarını güncelle, geçici dosya ve betikleri düzenle
9e4b665 docs: dokümanları spec/operasyon/denetim/arsiv yapısına taşı
```

Bu rapor ve `DURUM.md` güncellemesi (K4 notu, borç 25–26) ayrı bir `docs:` commit'iyle eklendi. `push` yapılmadı.

## 3. Doğrulama komutları ve çıktıları

**D1 — Docker** (`docker compose down` → `docker compose up -d --build`, volume silinmedi)
```
SERVICE     STATE     STATUS
app         running   Up
nginx       running   Up
postgres    running   Up (healthy)
queue       running   Up
redis       running   Up (healthy)
scheduler   running   Up
```
`mysql` yok, 6 servis çalışıyor.

**D2 — `artisan about` ve PHP modülleri**
```
Database .......... pgsql      Cache/Queue/Session ... redis
Broadcasting ...... log        Mail .................. log
Laravel 12.43.1 · PHP 8.2.34 · Environment local
```
Container içi kontrol: `default=pgsql`, `connections=pgsql`, `database=alatax_hr`, `server=16.15`, `app_key_sha16=d1ca474b219cbf37` (Adım öncesiyle aynı).

`php -m` (filtreli): `PDO`, `pdo_pgsql`, `pdo_sqlite`, `sqlite3`, `mysqlnd` — **`pdo_mysql` yok** ✅. Kalan üçü için istisna gerekçesi bölüm 6'da.

**D3 — API sağlık**
```
GET  /up                → 200
POST /api/v1/auth/login → success=True, token alındı (mevcut süper admin hesabı; şifre yazılmadı)
```

**D4 — Canlı DB satır sayıları** (yedek öncesi → sonrası)

| Tablo | Önce | Sonra |
|---|---|---|
| employees | 60 | 60 |
| users | 59 | 59 |
| leave_requests | 32 | 32 |
| companies | 3 | 3 |
| attendance_records | 300 | 300 |
| job_applications | 20 | 20 |
| saved_reports | 50 | 50 |

**D5 — Testler**
- Test DB'si yoktu. Kullanıcı onayıyla postgres container'ında boş `alatax_hr_testing` oluşturuldu (yalnız `CREATE DATABASE`; canlı DB'ye dokunulmadı, silme yok).
- Yeni testler: `phpunit tests/Architecture tests/Feature/PostgresReportSqlTest.php` → **OK (10 test, 170 assertion)**.
- Tam suite: `docker compose exec -T app php vendor/bin/phpunit` → **OK, 781 test, 3486 assertion**, 0 hata/başarısız. 59 PHPUnit deprecation uyarısı var (mevcut test kodundan; kırık değil).

`PostgresReportSqlTest` kapsamı:

| Test | Uç |
|---|---|
| `test_employee_stats_age_seniority_and_fallback_labels` | `GET /api/v1/employees/stats` (yaş/kıdem grupları, `Departman Atanmamış`, `unspecified`) |
| `test_employee_report_data_date_dimensions_and_age_measures` | `POST /api/v1/employees/reports/data` (`hire_year`, `hire_month`, `age_group`, `seniority_group`, `avg_age`, `avg_seniority`) |
| `test_employee_report_export_excel_with_date_dimension` | `POST /api/v1/employees/reports/export/excel` |
| `test_employee_dashboard_widget_year_dimensions_and_age_measures` | `POST /api/v1/employees/dashboards/widget-data` (`hire_year`, `birth_year`, `age`, `tenure`) |
| `test_document_widget_month_and_year_dimensions` | `POST /api/v1/documents/reports/widget-data` (month, year) |
| `test_document_kpi_this_month` | `POST /api/v1/documents/reports/kpi-data` |
| `test_recruitment_trends_day_week_month_intervals` | `GET /api/v1/recruitment/reports/trends` (day, week, month) |

**D6 — Frontend** (`frontend/`)
```
pnpm lint  → apps/superadmin, apps/company, apps/portal: Done   (exit 0)
pnpm build → superadmin ✓ 9.8s, portal ✓ 20.2s, company ✓ 34.2s (exit 0)
```

**D7 — Son tarama**
```
git grep -n -i -E "mysql|mariadb|sqlite|3306|3307" -- . \
  ":(exclude)docs/arsiv" ":(exclude)docs/denetim" ":(exclude)docs/backups" \
  ":(exclude)docs/cursor-gorevleri" ":(exclude)*.lock" ":(exclude)*pnpm-lock.yaml"
```
Kalan eşleşmelerin hepsi **yasak/koruma ifadesi**, kullanım değil:
- `.cursorrules:7, 35, 77` — "MySQL/MariaDB/SQLite yasak" kuralları.
- `README.md:25, 95` — "desteklenmez / kaldırıldı" notları.
- `backend/tests/Architecture/DatabaseDriverGuardTest.php` — yasaklı kelime listesi.
- `docs/DURUM.md:86, 106` — "MySQL tamamen kaldırıldı" kararı ve K3 "düzeltildi".
- `docs/spec/MODUL_SPEC.md:320` — "MySQL/MariaDB/SQLite yasak" kararı.

Kod, config, Docker, CI'da sıfır kullanım. `DatabaseDriverGuardTest` `app/ config/ database/ routes/` altında yasaklı belirteç bulursa kırılır.

## 4. Kabul kriterleri

| Kriter | Durum | Kanıt |
|---|---|---|
| `alatax-son` etiketi ve `altyapi` dalı var; çalışma `altyapi`'de | ✅ | `git tag -l alatax-son` → var (`f006f93`); `git branch --show-current` → `altyapi` |
| PG yedeği alındı ve doğrulandı; MySQL verisi varsa yedeklendi | ✅ | 4.411.220 bayt, 155 TABLE; MySQL'de uygulama verisi yoktu |
| `docs/` hedef yapıda; `README.md` ve `DURUM.md` yazıldı; koddan arşive referans yok | ✅ | Bölüm 1-B; `docs/` kökünde yalnız `DURUM.md`, `README.md` ve hedef klasörler |
| MySQL/MariaDB/SQLite aramasında kod, config, Docker, CI, aktif dokümanda sıfır sonuç | ✅ | D7 — kalanlar yalnız yasak ifadeleri |
| `DatabaseDriverGuardTest` var ve geçiyor; 5 controller çevrildi ve testlendi | ✅ | D5 — 10 test OK; tam suite 781 OK |
| Docker 6 servis, veri kaybı yok, APP_KEY değişmedi | ✅ | D1, D4, `app_key_sha16` aynı |
| Frontend lint + build yeşil | ✅ | D6 |
| Rapor yazıldı | ✅ | Bu dosya |

## 5. Kullanıcının elle yapması gerekenler

1. **Eski MySQL volume'ünü silmek** — `alatax-hr_mysql_data` hâlâ duruyor (içinde uygulama verisi yok, PG yedeği doğrulandı). Onaylarsan: `docker volume rm alatax-hr_mysql_data`. Geri alınamaz.
2. **Windows'ta XAMPP MySQL ve Apache servislerini durdurmak** (port 3306 ve 80 hâlâ dinleniyor). XAMPP Control Panel → MySQL/Apache "Stop" ve "Service" onay kutusunu kaldır.
3. **`push`** — yapılmadı. İstersen: `git push -u origin altyapi` (etiket için ayrıca `git push origin alatax-son`).
4. İsteğe bağlı: `backend/.env.bak-adim01` artık kullanılmıyor; bir süre sonra silinebilir (içinde eski, farklı bir `APP_KEY` var — yedek olarak tutmak daha güvenli).

## 6. Kapsam dışı bulgular ve açık sorular

**Kabul edilen istisnalar**
- `php -m` içinde `pdo_sqlite`, `sqlite3` ve `mysqlnd`: resmi `php:8.2-fpm-bookworm` imajına derlenmiş olarak gelir; `docker-php-ext-install` ile eklenmezler, kaldırmak için PHP'yi özel derlemek gerekir. Uygulama hiçbirini kullanmıyor (config'de yalnız `pgsql`, guard test koruyor). `mysqlnd` tek başına bir sürücü değil, `pdo_mysql` olmadan MySQL'e bağlanılamaz.

**Bulgular (DURUM.md borç listesine eklendi)**
- Borç 25: `EmployeeDashboardController::getKPIData`, measure `age`/`tenure` olduğunda olmayan kolonlarda `AVG/MIN/MAX/SUM` çalıştırıyor → KPI widget'ında 500 riski.
- Borç 26: `EmployeeDashboardController::getWidgetData`, `config.filters` anahtarlarını kolon adı olarak whitelist'siz `where/whereIn`'e veriyor (istemci keyfi kolona göre filtreleyebilir, ör. hassas alan çıkarımı).

**Diğer notlar**
- `backend/database/database.sqlite` izlenmeyen bir dosyaydı ve yedeği alınmadan silindi (uygulama sqlite kullanmıyordu; içerik değeri beklenmiyor).
- `backend/scripts/uzun-kosu/seed-scale.php` ölçek seed kullanıcıları için sabit bir sentetik test şifresi içeriyor (gerçek hesap değil). Adım 03'te env'den okunacak şekilde değiştirilmesi önerilir.
- Guard test config ve kaynak kodu denetler; PHP eklenti listesini denetlemez (yukarıdaki imaj istisnası nedeniyle).
- Test DB'si `alatax_hr_testing` bu makinede elle oluşturuldu; otomasyonu ve belgelenmesi Adım 03 konusu (K4 güncellendi).

**Güvenlik bildirimi**
- Önceki oturumda (Bölüm A6 sırasında) `APP_KEY` değerleri bir kez araç çıktısında göründü. Hiçbir dosyaya, rapora, log'a veya commit'e yazılmadı. Anahtarı değiştirmek **önerilmez** (şifreli veriler okunamaz olur); yalnızca bilgin olsun diye bildiriliyor.

**Açık sorular**
1. `docs/cursor-gorevleri/` içindeki görev dosyaları (`00_ORTAK_KURALLAR.md`, `ADIM_0x_*.md`) git'te izlenmiyor; yalnız bu rapor commit edildi. Görev dosyaları da commit edilsin mi?
2. `alatax-hr_mysql_data` volume'ü ve `backend/.env.bak-adim01` ne zaman silinsin?
