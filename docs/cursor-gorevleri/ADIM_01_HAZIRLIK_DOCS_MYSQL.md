# ADIM 01 — Hazırlık, Doküman Arşivi ve MySQL/SQLite'ın Tamamen Kaldırılması

**Ön koşul:** `docs/cursor-gorevleri/00_ORTAK_KURALLAR.md` dosyasını oku ve uygula.
**Bu adımın amacı:** Altyapı çalışmasına temiz bir zeminden başlamak. (1) Geri dönüş noktası ve yedek, (2) dokümanların tek güncel kaynağa indirilmesi, (3) PostgreSQL dışındaki tüm veritabanı izlerinin kaldırılması.
**Bu adımda YAPILMAYACAKLAR:** Ürün adı değişikliği (Adım 02), test altyapısı (Adım 03), yeni özellik. `alatax` adını bu adımda değiştirme.

---

## Bölüm A — Geri dönüş noktası ve yedek

1. `git status` temiz değilse (izlenmeyen dosyalar hariç) DUR ve raporla.
2. Mevcut HEAD'e etiket koy: `git tag alatax-son` (yalnız local).
3. `faz4-form-engine` dalından yeni çalışma dalı aç: `altyapi`. Bundan sonraki tüm adımlar bu dalda yapılacak. `main`e dokunma.
4. Canlı PostgreSQL yedeği al (custom format):
   `docker compose exec -T postgres sh -c 'pg_dump -Fc -U "$POSTGRES_USER" "$POSTGRES_DB"' > docs/backups/oncesi_adim01_<tarih-saat>.dump`
   Dosya boyutunu ve `pg_restore -l` ile içindeki tablo sayısını rapora yaz (155 bekleniyor).
5. Docker MySQL container'ındaki veriyi tespit et (sadece okuma): veritabanı listesi ve her şemadaki tablo sayısı. İçinde veri varsa `mysqldump` ile `docs/backups/mysql_son_<tarih>.sql` olarak yedekle. Bu, MySQL'den kalan son kopya olacak.
6. Aktif `APP_KEY`'in hangi dosyadan geldiğini tespit et (container içinde `config('app.key')` ile kök `.env` ve `backend/.env` değerlerinin **SHA-256 özetlerini** karşılaştır, değeri yazma). Sonucu rapora yaz.

## Bölüm B — Dokümanların arşivlenmesi ve tek güncel kaynak

Hedef yapı:

```
docs/
  README.md            ← YENİ: doküman haritası (hangi dosya ne için, okuma sırası)
  DURUM.md             ← YENİ: tek güncel durum belgesi
  spec/                ← hâlâ geçerli HEDEF tasarımlar (henüz tamamlanmamış olsa da)
  operasyon/           ← kurulum/deploy belgeleri
  denetim/2026-10-13/  ← iki bağımsız denetim raporu (taban çizgisi)
  cursor-gorevleri/    ← görev promptları ve raporları (DOKUNMA)
  arsiv/2026-10-alatax-donemi/  ← diğer her şey
  arsiv/2026-08/ ve mevcut arsiv içeriği yerinde kalır
  backups/             ← git dışı, DOKUNMA
```

1. Önce `docs/` altındaki her dosya için tek satırlık sınıflandırma tablosu çıkar (dosya → hedef klasör → gerekçe) ve rapora koy. Varsayılan sınıflandırma:
   - **spec/**: `MODUL_SPEC.md`, `AKIS_SPEC.md`, `AKIS_SEMA_EDITOR.html`, `SISTEM_ISLEYIS.md`, `TASARIM_REHBERI.md`, `I18N.md`, `ENTEGRASYON_SPEC.md`, `AYAR_MOTORU.md`, `EXPORT_KAPSAM_BIRLIGI.md`, `EXPORT_GUVENLIK_VE_ENTEGRASYON_EKLERI.md`
   - **operasyon/**: `DEPLOY_UBUNTU.md`
   - **denetim/2026-10-13/**: `DURUM_RAPORU_CLAUDE.md`, `DURUM_RAPORU_CURSOR.md`, `claude-denetim/` klasörü
   - **arsiv/2026-10-alatax-donemi/**: tüm `FAZ*_RAPOR.md`, `*_RAPOR.md`, `*_TESHIS.md`, `GUNCEL_DURUM_RAPORU.md`, `BURADAN_BASLA.md`, `ROADMAP.md`, `DOCS_ANALIZ.md`, `YENIDEN_CERCEVELEME.md`, `DENETIM_TURU.md`, `TEST_TURU.md`, `TEMEL_SAGLAMLASTIRMA.md`, `FAZ6_ONCESI_KAPATMA.md`, `FAZ_G_KAPANIS_OZET.md`, `CURSOR_GORSEL_KONTROL_PROMPT.md`, `UZUN_KOSU_RAPORU.md`, tüm `uzun-kosu-*` log/txt ve `docs/*.json` ölçüm çıktıları, `qa/`, `gorsel-kontrol/`
   - Bir dosyanın içeriği hâlâ geçerli hedef tasarımsa ama listede arşive düşüyorsa, gerekçeyle spec/'e al ve raporda belirt.
2. Taşımaları `git mv` ile yap (izlenmeyen dosyalar için `mv` + `git add`).
3. **`docs/DURUM.md`** yaz. Kaynaklar: arşivlenen `GUNCEL_DURUM_RAPORU.md`, `ROADMAP.md` ve iki denetim raporu. İçerik (kısa ve güncel, tarihçe değil):
   - Ürün çerçevesi (00_ORTAK_KURALLAR vizyonuyla uyumlu)
   - Modül listesi ve her modülün **gerçek** durumu (Backend / Frontend / Test: Tam / Kısmi / İskelet / Yok). Kullanıcının tespiti: hiçbir modül bitmiş değil, sayfalar da tamamlanmış değil. Abartma.
   - Kurulmuş platform motorları (Lookup, Form Engine, Workflow, Bildirim, Rapor, Ayar, registry'ler) ve olgunluk seviyeleri
   - **Kilitli mimari kararlar** listesi (ROADMAP ve eski raporlardan; MySQL ile ilgili maddeyi "MySQL tamamen kaldırıldı" olarak güncelle)
   - **Bilinen kritik hatalar** (denetimlerden): 2FA portal/admin açığı, `private` disk tanımsız, MySQL'e özgü SQL, test DB yok, bağımlılık açıkları, onboarding 2 kırık uç. Durum: Adım 03'te ele alınacak.
   - Açık teknik borç listesi (tek liste, sahibi/adım numarasıyla)
4. **`docs/README.md`** yaz: klasör yapısı, her aktif dosyanın tek cümlelik amacı, yeni gelen biri (insan veya AI) için okuma sırası: `00_ORTAK_KURALLAR` → `DURUM.md` → `.cursorrules` → ilgili `spec/` dosyası.
5. Kodda ve `.cursorrules` içinde eski doküman yollarına verilen referansları bul (`docs/ROADMAP.md`, `docs/GUNCEL_DURUM_RAPORU.md`, `ROADMAP.md` vb.) ve yeni yollara güncelle. Arşivdeki bir dosyaya kod içinden referans kalmasın.
6. Kökteki geçici dosyaları temizle: `frontend/_e_lint_*.txt`, `frontend/_e_tsc_*.txt` (git'ten sil). `scripts/tmp-*` dosyalarını sil. Kökteki `backend-*.log` dosyalarını `docs/arsiv/2026-10-alatax-donemi/suite-loglari/` altına taşı (git dışında kalabilirler).
7. İzlenmeyen betikler (`backend/scripts/denetim/`, `backend/scripts/uzun-kosu/`, `backend/scripts/*.php` tek seferlik düzeltme betikleri, `scripts/ubuntu-fix-migrate.sh`): her biri için "commit et / arşive taşı / sil" önerisini gerekçesiyle rapora yaz. Tek seferlik veri düzeltme betiklerini (`fix-*`, `strip-*`, `find-*`) `backend/scripts/arsiv/` altına taşıyıp commit et; ölçüm betikleri (`uzun-kosu/`, `denetim/`) kalıcı araç olarak commit edilsin.

## Bölüm C — MySQL, MariaDB ve SQLite'ın tamamen kaldırılması

Teşhis listesi (tamamını kendin de tara: `mysql|mariadb|sqlite|pdo_mysql|3306|3307` — `node_modules`, `vendor`, `.git`, `docs/arsiv`, `docs/backups` hariç). Bilinen noktalar:

| Yer | Yapılacak |
|---|---|
| `docker-compose.yml` | `mysql` servisini, `mysql_data` volume tanımını ve `app` servisinin `mysql` bağımlılığını kaldır. Bayat yorumları ("Faz 1'de PostgreSQL ile değiştirilecek", "app default hâlâ mysql") düzelt. |
| `backend/Dockerfile` | `pdo_mysql` eklentisini kaldır. İmajı yeniden derle. |
| `backend/config/database.php` | `mysql`, `mariadb`, `sqlite` bağlantılarını kaldır. Varsayılan `env('DB_CONNECTION', 'pgsql')`. Redis bölümüne dokunma. |
| `backend/config/queue.php`, `telescope.php` | MySQL/SQLite'a özgü varsayılan varsa pgsql'e çek. |
| `backend/app/Support/PortableEnum.php` | sqlite/mysql dallarını kaldır, yalnız PostgreSQL. Sınıf adı ve kullanım yerleri korunabilir; dokümantasyon yorumunu güncelle. |
| `backend/database/database.sqlite` | Sil. `backend/database/.gitignore` içindeki sqlite satırlarını temizle. |
| `backend/database/migrations_backup_pre_faz1a/` | `docs/arsiv/2026-10-alatax-donemi/` altına taşı (kod klasöründe kalmasın). |
| `.env`, `.env.docker.example`, `backend/.env.example`, `backend/.env.ci-test` | Tüm `MYSQL_*` anahtarlarını ve `DB_CONNECTION=mysql/sqlite` satırlarını kaldır. |
| `backend/.env` (git dışı) | **Tek doğruluk kaynağı kök `.env` olacak.** Önce Bölüm A6'daki APP_KEY tespitine bak. Aktif APP_KEY kök `.env`'deyse `backend/.env`'i `backend/.env.bak-adim01` olarak yeniden adlandır (silme). Ardından container'ın `artisan about` çıktısının değişmediğini doğrula. Aktif anahtar `backend/.env`'deyse DUR ve raporla. |
| `.cursorrules` | "MySQL'e geri dönüş yolu açık kalsın" türü kuralları kaldır. "Yalnız PostgreSQL 16; MySQL/MariaDB/SQLite yasak" kuralını ekle. |
| `README.md`, `backend/README.md`, `docs/operasyon/DEPLOY_UBUNTU.md`, `docs/spec/*` | MySQL anlatımlarını kaldır veya PostgreSQL'e çevir. |
| `.github/workflows/ci.yml` | MySQL izleri varsa kaldır. |
| `composer.json` | MySQL'e özgü paket veya `ext-pdo_mysql` gereksinimi varsa kaldır. |

**Kalıcı koruma:** `backend/tests/Architecture/DatabaseDriverGuardTest.php` adında bir test ekle (`phpunit.xml` test suite'lerine dahil olduğundan emin ol). Bu test:
- `config('database.connections')` anahtarlarının yalnızca `pgsql` (ve gerekiyorsa test bağlantısı) olduğunu,
- `app/`, `config/`, `database/`, `routes/` altındaki PHP dosyalarında `mysql`, `mariadb`, `sqlite`, `TIMESTAMPDIFF`, `CURDATE(`, `DATE_FORMAT(` ifadelerinin geçmediğini doğrulasın.
- Not: MySQL'e özgü SQL kullanan mevcut 5 controller (`EmployeeController`, `EmployeeReportController`, `EmployeeDashboardController`, `Documents/ReportController`, `Recruitment/ReportController`) bu testi kıracak. **Bu adımda o SQL'leri PostgreSQL'e çevir** (`EXTRACT(YEAR FROM ...)`, `to_char(...)`, `date_part('year', age(...))`, `CURRENT_DATE`, string sabitleri tek tırnak). Her düzeltilen uç için PostgreSQL üzerinde çalışan bir feature testi ekle (yalnız 200 ve beklenen JSON şekli yeterli).

## Bölüm D — Doğrulama

1. `docker compose down` → `docker compose up -d --build` (volume silme yok). `docker compose ps` çıktısında `mysql` olmamalı, diğer 6 servis çalışmalı.
2. `docker compose exec app php artisan about`: Database = pgsql. `php -m` çıktısında `pdo_mysql` olmamalı.
3. API sağlık: `/up` 200, login çalışıyor (mevcut bir kullanıcıyla, şifreyi rapora yazmadan).
4. Canlı DB satır sayıları (personel, kullanıcı, izin talepleri) yedek öncesiyle aynı.
5. Test paketi: test DB'si henüz yoksa (Adım 03 konusu) yalnız yeni eklenen testleri geçici bir test DB'siyle çalıştırmayı dene. Mümkün değilse nedeni rapora yaz. Tam suite Adım 03'te yeşile dönecek.
6. Frontend: `pnpm lint` ve `pnpm build` (3 uygulama) exit 0.
7. Son tarama: `mysql|mariadb|sqlite` araması, arşiv ve yedek klasörleri dışında **sıfır** sonuç vermeli. İstisna varsa gerekçesiyle listele.

## Kabul kriterleri
- [ ] `alatax-son` etiketi ve `altyapi` dalı var; çalışma `altyapi` dalında.
- [ ] PostgreSQL yedeği alındı ve doğrulandı; MySQL verisi varsa yedeklendi.
- [ ] `docs/` hedef yapıda; `README.md` ve `DURUM.md` yazıldı; koddan arşive referans yok.
- [ ] MySQL/MariaDB/SQLite aramasında kod, config, Docker, CI ve aktif dokümanlarda sıfır sonuç.
- [ ] `DatabaseDriverGuardTest` var ve geçiyor; 5 controller'daki MySQL SQL'i PostgreSQL'e çevrildi ve testlendi.
- [ ] Docker 6 servisle ayakta, veri kaybı yok, APP_KEY değişmedi.
- [ ] Frontend lint + build yeşil.
- [ ] Rapor: `docs/cursor-gorevleri/raporlar/ADIM_01_RAPOR.md`.

## Kullanıcıya bırakılacaklar (raporda listele, kendin yapma)
- Docker'daki eski `mysql_data` volume'ünün silinmesi (`docker volume rm ...`): yedek doğrulandıktan sonra kullanıcı onayıyla.
- Windows'ta XAMPP'ın MySQL ve Apache servislerinin durdurulması (port 3306 ve 80 hâlâ dinleniyor).
- `push`.
