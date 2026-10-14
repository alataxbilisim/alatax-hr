# ADIM 02 — Ürün Adı Değişikliği: Alatax HR → Dobedan HR

**Ön koşul:** `00_ORTAK_KURALLAR.md` okunmuş olmalı. `ADIM_01_RAPOR.md` tüm kabul kriterleri ✅ olmalı. Değilse DUR.
**Dal:** `altyapi`
**Amaç:** "alatax" ifadesi (her yazımıyla: `alatax`, `Alatax`, `ALATAX`, `alatax-hr`, `alatax_hr`, `@alatax/…`) depodan, Docker ortamından ve veritabanından tamamen kalkacak, yerine Dobedan gelecek. Veri kaybı olmayacak.

## Kesinleşmiş adlandırma tablosu

| Bağlam | Eski | Yeni |
|---|---|---|
| Ürün adı (UI, e-posta, başlık, `APP_NAME`) | ALATAX HR / Alatax HR | **Dobedan HR** |
| Slug / Docker compose proje adı / container önekleri | `alatax-hr`, `alatax-hr-app` … | `dobedan-hr`, `dobedan-hr-app` … |
| Veritabanı | `alatax_hr` | `dobedan_hr` |
| Test veritabanı | `alatax_hr_testing` | `dobedan_hr_testing` |
| PostgreSQL kullanıcısı | `alatax` | `dobedan` |
| Frontend paket kapsamı | `@alatax/shared`, `@alatax/company`, `@alatax/portal`, `@alatax/superadmin`, `alatax-hr-frontend` | `@dobedan/shared`, `@dobedan/company`, `@dobedan/portal`, `@dobedan/superadmin`, `dobedan-hr-frontend` |
| Tarayıcı depolama anahtarları | `alatax_token`, `alatax_company_id`, `alatax_branch_id` … | `dobedan_token`, `dobedan_company_id`, `dobedan_branch_id` … |
| Composer paket adı | `laravel/laravel` | `dobedan/hr-backend` |
| E-posta alan adı (seed, örnek, test) | `alataxbilisim.com`, `alatax.local` | `dobedan.com`, `dobedan.local` |
| Süper admin seed hesabı | `admin@alataxbilisim.com` | `admin@dobedan.com` |
| Logo dosyası | `alatax.png` | `brand-logo.png` (görsel içeriği şimdilik aynı kalabilir; yeni logo daha sonra gelecek) |
| Demo slug'ları | `alatax-demo-as` vb. | `dobedan-demo-as` vb. |

**Değişmeyecekler (istisna listesi):**
- GitHub deposu ve remote URL'si (`github.com/alataxbilisim/alatax-hr`) ve bu adrese giden bağlantılar.
- `.git` geçmişi, mevcut commit mesajları ve etiketler (`alatax-son` etiketi kalır).
- `docs/backups/` içindeki yedek dosyaları.
- `activity_logs` (denetim kaydı) tablosundaki geçmiş kayıtlar. Denetim izi değiştirilmez.
- `node_modules`, `vendor`, `dist`, derlenmiş cache'ler (yeniden üretilecek).

## Bölüm 0 — Ön hazırlık
1. `docs/cursor-gorevleri/` altındaki görev dosyalarını (`00_ORTAK_KURALLAR.md`, `ADIM_*.md`) git'e ekle ve ayrı bir commit at: `docs: Cursor görev dosyalarını depoya ekle`. Bundan sonraki görev dosyaları da her adımın başında commit edilecek.
2. `backend/.env.bak-adim01` dosyasını bu adımın **sonunda**, Bölüm G doğrulamaları geçtikten sonra sil (git dışı; artık kullanılmıyor).

## Bölüm A — Teşhis (değişiklikten önce)
1. Tüm depoda büyük/küçük harf duyarsız `alatax` ara (istisnalar hariç). Sonucu dosya bazında kategorilere ayırarak rapora koy: backend kodu, config/env, Docker, CI, frontend kodu, paket tanımları, testler, betikler, aktif dokümanlar, arşiv dokümanları, `.cursor/` ve `.cursorrules`.
2. Canlı veritabanında `alatax` geçen verileri bul: `information_schema.columns` üzerinden tüm `text`, `varchar` ve `jsonb` kolonlarını tara (`ILIKE '%alatax%'`, jsonb için `::text`). `telescope_*` ve `activity_logs` tablolarını tarama dışı bırak, ama satır sayılarını not et. Tablo → kolon → satır sayısı tablosunu rapora koy.
3. Redis anahtar öneklerinin, cache ve session prefix'lerinin nereden üretildiğini tespit et (`APP_NAME` slug'ı vb.).
4. Bulduklarında yukarıdaki adlandırma tablosuna uymayan bir bağlam varsa (örneğin dış servis kimliği, lisans anahtarı) DUR ve raporla.

## Bölüm B — Yedek
`ADIM_01`deki yöntemle yeni bir `pg_dump -Fc` yedeği al: `docs/backups/oncesi_adim02_<tarih-saat>.dump`. `pg_restore -l` ile doğrula.

## Bölüm C — Kod, yapılandırma ve dokümanlar
1. **Backend:** `APP_NAME`, mail `from` adı, config varsayılanları, seeder'lar (`SuperAdminSeeder`, demo seeder'lar), factory'ler, testlerdeki sabitler, artisan komut adları veya önekleri, `composer.json` adı/açıklaması, `backend/README.md`. PHP namespace'i `App\` olarak kalır.
2. **Docker:** `docker-compose.yml` (`name:`, `container_name`, varsayılan DB/kullanıcı değerleri), kök `.env` ve `.env.docker.example`, `backend/Dockerfile` etiketleri, `docker/` altındaki config dosyaları.
3. **CI:** `.github/workflows/ci.yml` (test DB adı, kullanıcı, healthcheck).
4. **Frontend:** paket adları ve tüm `@alatax/*` import'ları, `pnpm-workspace` filtreleri, kök `package.json` script'leri, Vite/TS alias'ları, sayfa başlıkları (`index.html <title>`), i18n metinleri, localStorage anahtarları, logo dosya adı ve referansları. Ardından `pnpm install` ile lock dosyasını yeniden üret. Kullanıcılar bir kez yeniden giriş yapacak; eski anahtarlar için geçiş kodu yazma (test aşamasındayız).
5. **Betikler:** `scripts/`, `backend/scripts/`, `scripts/gorsel-kontrol/` (Ubuntu deploy yolları dahil). Ubuntu sunucusunda zaten kurulu bir ortam varsa oranın geçişi elle yapılacak: `docs/operasyon/DEPLOY_UBUNTU.md` içine "Alatax → Dobedan sunucu geçişi" başlıklı kısa bir adım listesi ekle.
6. **Dokümanlar:** aktif dokümanlar, `docs/spec/`, `docs/denetim/`, `docs/arsiv/`, `README.md`, `.cursorrules`, `.cursor/` altındaki kurallar. İstisna listesindeki GitHub bağlantılarına dokunma.
7. Dosya ve klasör adlarında `alatax` geçiyorsa (`git mv` ile) yeniden adlandır.

## Bölüm D — Veritabanı ve Docker geçişi (veri korunarak)
Compose proje adı değişince Docker yeni, **boş** volume'ler oluşturur (`dobedan-hr_postgres_data`). Bu yüzden sıra önemli:

1. Eski yığın çalışırken son yedeği al (Bölüm B'deki yedek yeterliyse onu kullan).
2. Satır sayısı taban çizgisini kaydet: tüm `public` tabloları için `count(*)` listesi (`docs/backups/satir_sayilari_oncesi_adim02.txt`).
3. Eski yığını durdur: `docker compose -p alatax-hr down` (**`-v` KULLANMA**, eski volume'ler kalsın).
4. Yeni adlarla yığını kaldır: `docker compose up -d --build`. Yeni Postgres `dobedan_hr` veritabanını ve `dobedan` kullanıcısını oluşturacak.
5. Yedeği yeni veritabanına yükle: `pg_restore --no-owner --role=dobedan -d dobedan_hr`. Hata çıktısını rapora koy (beklenen uyarılar dışında hata olmamalı).
6. Satır sayılarını yeniden al ve taban çizgisiyle karşılaştır. **Fark sıfır olmalı.**
7. Sequence'ların doğru değerde olduğunu kontrol et (her tablonun `max(id)` ≤ sequence `last_value`).
8. Redis için yeni yığının kendi volume'ü yeterli. Eski cache/session taşınmaz (yeniden giriş gerekir).

## Bölüm E — Veritabanındaki "alatax" verilerinin dönüştürülmesi
1. Tek seferlik, idempotent bir artisan komutu yaz: `php artisan dobedan:rebrand-data {--dry-run}`.
   - Bölüm A2'de bulunan tablo/kolonlarda adlandırma tablosuna göre dönüşüm yapar (ürün adı, slug, e-posta alan adı).
   - `activity_logs` ve `telescope_*` tablolarına dokunmaz.
   - E-posta değişikliğinde benzersizlik çakışması olursa o satırı atlar ve raporlar.
   - Tek transaction içinde çalışır. `--dry-run` hangi tabloda kaç satırın değişeceğini listeler.
2. Önce `--dry-run` çalıştır, çıktıyı rapora koy. Sonra gerçek çalıştır.
3. Sonrasında A2 taramasını tekrarla: `activity_logs` ve `telescope_*` dışında **sıfır** sonuç.
4. Komut için bir feature testi yaz (dry-run hiçbir şeyi değiştirmez, gerçek çalışma doğru dönüştürür, ikinci çalıştırma değişiklik yapmaz).

## Bölüm F — Kalıcı koruma
1. `backend/tests/Architecture/BrandGuardTest.php`: `app/`, `config/`, `database/`, `routes/`, `resources/`, `tests/` (bu test dosyası hariç) altında `alatax` (büyük/küçük harf duyarsız) geçmediğini doğrular.
2. `scripts/check-brand.mjs`: depo genelinde (istisna listesi hariç) `alatax` arar, bulursa exit 1. Kök `package.json`'a `check:brand` script'i olarak ekle ve CI'da frontend job'ına bir adım olarak koy.
3. `.cursorrules` içine: "Ürün adı Dobedan HR. `alatax` ifadesi kod, config ve dokümanlarda kullanılmaz" kuralını ekle.

## Bölüm G — Doğrulama
1. `docker compose ps`: 6 servis `dobedan-hr-*` adlarıyla çalışıyor.
2. `artisan about`: Application Name = Dobedan HR, Database = pgsql, DB adı `dobedan_hr`.
3. `/up` 200. Üç arayüz açılıyor (3001/3002/3003): giriş sayfasında ve sekme başlığında "Dobedan HR" görünüyor. Mevcut bir kullanıcıyla company panelinde giriş yapılıyor ve personel listesi önceki sayıyla geliyor.
4. Süper admin girişi `admin@dobedan.com` ile çalışıyor (şifreyi rapora yazma).
5. 2FA'sı açık bir kullanıcı varsa girişini test et (APP_KEY değişmediyse çalışmalı).
6. `pnpm lint`, `pnpm build` (3 uygulama), `pnpm check:brand` exit 0.
7. Backend testleri: `BrandGuardTest`, `DatabaseDriverGuardTest` ve rebrand komut testi geçiyor. Tam suite Adım 03'te; ama test DB'si Adım 01'de kurulduysa tam suite'i koş ve sonucu rapora yaz.
8. Son tarama: istisnalar dışında `alatax` için sıfır sonuç (kanıt olarak komut çıktısı).

## Kabul kriterleri
- [ ] Bölüm A teşhis tabloları raporda.
- [ ] Yedek alındı ve doğrulandı.
- [ ] Depoda (istisnalar hariç) `alatax` sıfır.
- [ ] Docker yığını `dobedan-hr`, DB `dobedan_hr`, kullanıcı `dobedan`; satır sayısı farkı sıfır; sequence'lar doğru.
- [ ] DB verisinde (`activity_logs`, `telescope_*` hariç) `alatax` sıfır.
- [ ] `BrandGuardTest` + `check:brand` + CI adımı var ve geçiyor.
- [ ] Arayüzlerde Dobedan HR görünüyor, giriş çalışıyor, APP_KEY değişmedi.
- [ ] Rapor: `docs/cursor-gorevleri/raporlar/ADIM_02_RAPOR.md`.

## Kullanıcıya bırakılacaklar (raporda listele, kendin yapma)
- Eski Docker volume'lerinin silinmesi (`alatax-hr_postgres_data`, `alatax-hr_redis_data`, varsa `alatax-hr_mysql_data`): yeni yığın birkaç gün sorunsuz çalıştıktan sonra, onayla.
- Ubuntu sunucusundaki ortamın geçişi.
- `push`.
