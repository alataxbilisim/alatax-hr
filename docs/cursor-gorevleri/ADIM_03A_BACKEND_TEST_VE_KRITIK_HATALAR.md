# ADIM 03A — Backend Test Altyapısı ve Bilinen Kritik Hatalar

**Ön koşul:** `00_ORTAK_KURALLAR.md` okunmuş olmalı. `ADIM_02_RAPOR.md` tüm kabul kriterleri ✅ olmalı.
**Dal:** `altyapi`. Görev dosyasını adımın başında commit et.
**Amaç:** Backend test altyapısını, 40+ modüllü ve on binlerce personelli bir ürünün yükünü taşıyacak hale getirmek ve denetimlerde bulunan kritik hataları regresyon testleriyle kapatmak. Adım 03B (frontend + E2E + CI) bu adımın üzerine kurulacak.
**Bu adımda YAPILMAYACAKLAR:** Bağımlılık sürüm yükseltmeleri (Adım 04 güvenlik iş paketi), yeni modül özelliği, UI değişikliği (onboarding'deki 2 uç hariç).

---

## Bölüm A — Bilinen kritik hatalar (önce bunlar, her biri ayrı commit + regresyon testi)

| # | Hata | Beklenen çözüm | Test |
|---|---|---|---|
| K1 | **2FA atlatma:** portal (`routes/api.php` ~1434) ve süper admin (~1394) grupları `deny.2fa.challenge` içermiyor | Middleware'i bu gruplara ekle. Daha kalıcısı: `auth:sanctum` kullanan **her** route grubunda zorunlu olsun; tek istisna `auth/2fa/verify`. | (1) Portal ve admin uçlarına challenge token ile istek → 401/403. (2) Mimari test (Bölüm C3): `auth:sanctum` olan ve `deny.2fa.challenge` olmayan route listesi yalnız izinli istisnaları içerir. |
| K2 | **`private` disk tanımsız** → belge/bordro yükleme-indirme 500 | `config/filesystems.php` içine `private` diski: `local` sürücü, kök `storage_path('app/private')`, `visibility=private`, `serve=false`, `throw=true`. Sürücü env ile değiştirilebilsin (`PRIVATE_DISK_DRIVER`, ileride S3/MinIO için). Önce teşhis: DB'de `employee_documents`, `payslips`, `documents` vb. kayıtların gösterdiği dosyalar diskte var mı? Rapora yaz. | `Storage::fake` **kullanmadan** gerçek config ile yükle → indir → sil akışı. Ayrıca mimari test: koddaki her `Storage::disk('x')` çağrısındaki disk adı `config('filesystems.disks')` içinde tanımlı. |
| K3 | **Kolon adı enjeksiyonu / hassas alan sızıntısı:** `EmployeeDashboardController.php` ~203-209, istemcinin gönderdiği `filters` anahtarlarını doğrudan `where($field)`e koyuyor. Maaş yetkisi olmayan kullanıcı `salary`, `national_id` vb. ile filtreleyip değer tahmin edebilir. | İzinli alan beyaz listesi (tercihen rapor motorunun dataset alan tanımından), hassas alanlar için alan izni kontrolü. Beyaz liste dışı alan → 422. Aynı deseni projedeki **diğer** istemci kaynaklı kolon/sıralama/gruplama girdilerinde de ara (`orderBy($request->...)`, `groupBy`, `select`) ve raporla/düzelt. | Bilinmeyen alan → 422; `salary` ile filtre maaş yetkisi olmadan → 403/422; izinli alanla filtre çalışır. |
| K4 | KPI hesabı olmayan `age` / `tenure` kolonları üzerinde ortalama alıyor (DURUM.md borç 25) | PostgreSQL ifadesiyle hesapla (`date_part('year', age(birth_date))` vb.). | Her KPI tipi 200 ve sayısal sonuç. |
| K5 | Onboarding'de 2 kırık uç: `POST onboarding/templates/{id}/duplicate`, `POST onboarding/processes/{id}/tasks/{task}/skip` | Backend uçlarını mevcut desene göre ekle (FormRequest, Policy/permission, tenant izolasyonu, audit). `skip` yalnız `can_skip`/zorunlu olmayan görevde. Frontend'deki 13 ölü `api.ts` metodunu sil. | Her uç için 401 / 403 / happy path / başka şirketin kaydına erişim 404. |
| K6 | İşten çıkmış bazı personeller listede "Unknown" adıyla görünüyor (Adım 02 raporu) | Kök nedeni bul (`full_name` boş mu, ilişki mi kopuk?). Veri düzeltmesi gerekiyorsa idempotent komut + rapor. | Regresyon testi. |
| K7 | İki betikte sabit veritabanı şifresi (Adım 02 raporu) | Env'den okut. Git geçmişinde bu şifre gerçek bir ortamın şifresiyse rapora "değiştirilmeli" notu düş. | — |
| K8 | `public/storage` symlink'i yok | `docker/php/entrypoint.sh` içinde idempotent `storage:link`. | Container yeniden başlatıldıktan sonra link var. |

## Bölüm B — Test veritabanı ve çalıştırma altyapısı

1. **Test DB'si otomatik:** `docker/postgres/init/01-test-db.sql` (veya `.sh`) ile yeni bir Postgres volume'ünde `dobedan_hr_testing` otomatik oluşsun. Mevcut volume için idempotent bir yol: `composer test:db` script'i veya artisan komutu (`CREATE DATABASE` yalnız yoksa). Test DB'si canlı DB ile **asla** aynı olamaz: `tests/bootstrap.php` veya `TestCase` içinde DB adı `_testing` ile bitmiyorsa testler başlamadan dursun.
2. **Paralel çalıştırma:** `brianium/paratest` ekle, `php artisan test --parallel` PostgreSQL'de çalışsın (süreç başına `dobedan_hr_testing_test_N`). Hedef: tam suite süresini mevcut ~17 dk'dan belirgin şekilde düşürmek. Önce/sonra süreleri rapora yaz. Paralelde kırılan (sıraya bağımlı) testleri düzelt, atlama.
3. **Test türleri ve dizinler:** `Unit`, `Feature`, `Architecture` mevcut. Gerekirse `Security` (yetki/izolasyon/2FA) ayrı suite. `phpunit.xml` ve `composer.json` script'leri: `test`, `test:parallel`, `test:arch`, `test:security`, `test:coverage`.
4. **Deprecation temizliği:** 59 PHPUnit deprecation uyarısını sıfırla (doc-comment metadata → attribute vb.). Uyarı sayısı 0 olmalı.
5. **Kapsam ölçümü:** Docker dev imajına `pcov` ekle. `composer test:coverage` HTML + Clover üretsin (`backend/storage/coverage/`, git dışı). Modül bazında kapsam tablosunu rapora koy. Bu adımda eşik zorunlu değil; ama ölçülen değeri `docs/operasyon/TEST.md` içine **taban çizgisi** olarak yaz.
6. **Factory'ler:** Bugün 116 modele karşı 9 factory var. Bu, ölçeklenebilir test yazmayı engelliyor. Her Eloquent model için factory yaz (tenant ilişkileri doğru kurulmuş, `company_id` tutarlı, gerçekçi Türkçe veri, `faker` `tr_TR`). Pivot veya sistem tabloları gibi factory'si anlamsız modeller açık bir istisna listesinde olsun.
7. **Ortak test yardımcıları:** `tests/Concerns/` altında rol/izin ile kullanıcı oluşturma, şirket bağlamı kurma, portal personeli oluşturma, 2FA'lı kullanıcı, "başka şirket" fixture'ı gibi tekrar eden kurulumlar için trait'ler. Yeni testler bunları kullanır; eski testleri bu adımda toplu değiştirmek zorunlu değil.

## Bölüm C — Mimari ve güvenlik testleri (kalıcı korumalar)

`tests/Architecture/` altına. Her biri, bugünkü durumda bilinçli istisnalar varsa onları **açık bir izin listesinde** tutsun. Liste büyürse test kırılsın.
1. **Route güvenlik envanteri:** Her `api/v1` route'u ya `auth:sanctum` ya da açık public listesinde (`public/*`, `auth/login` vb.). Public olanların hepsinde throttle var.
2. **Yetki zorunluluğu:** `auth:sanctum` olan her route'ta `permission:*`, policy kontrolü veya açık izin listesinde gerekçe var (`auth/me` gibi).
3. **2FA:** K1'deki kural.
4. **Tenant izolasyonu:** `company_id` kolonu olan her tablonun modeli `BelongsToCompany` (veya eşdeğer global scope) kullanıyor; istisnalar listede.
5. **Disk adları:** K2'deki kural.
6. **Kod kuralları:** `app/` altında `env(` çağrısı yok (yalnız `config/`); controller'larda `DB::statement`/`DB::unprepared` yok; `dd(`, `dump(`, `var_dump(` yok.
7. Mevcut `BrandGuardTest` ve `DatabaseDriverGuardTest` korunur.

## Bölüm D — Statik analiz ve kod stili

1. **Pint:** ~170 dosyadaki stil sapmalarını tek ve yalnız biçim içeren bir commit'le düzelt (`style: pint`). Sonra `pint --test` exit 0.
2. **Larastan (PHPStan):** Kur. Hedef seviye en az 5; mevcut hatalar için `phpstan-baseline.neon` üret. Böylece yeni kod temiz olmak zorunda kalır, eski borç görünür olur. Baseline'daki hata sayısını rapora ve `DURUM.md` borç listesine yaz.
3. `composer audit` çıktısını rapora koy (düzeltme Adım 04'te).

## Bölüm E — Dokümantasyon ve kurallar

1. `docs/operasyon/TEST.md`: test DB kurulumu, tüm komutlar, paralel çalıştırma, kapsam, mimari testlerin mantığı ve izin listelerinin nasıl güncelleneceği, yeni modül için zorunlu test şablonu.
2. `.cursorrules` → "Tamamlanma tanımı (DoD)" bölümü: her yeni endpoint için 401 / 403 / happy path / tenant izolasyonu / validasyon testleri; her yeni model için factory; mimari testler yeşil; PHPStan baseline büyümez.
3. `DURUM.md`: kapanan kritik hatalar (K1–K8), yeni test sayıları, kapsam taban çizgisi, PHPStan baseline sayısı.

## Doğrulama
1. `composer test:parallel` (veya eşdeğeri) → 0 hata, 0 deprecation. Seri çalıştırma da yeşil.
2. `composer test:arch` yeşil.
3. `pint --test` ve `phpstan analyse` exit 0 (baseline ile).
4. Elle kontrol: portal ve süper admin uçlarına 2FA challenge token ile erişim reddediliyor. Company panelinde bir personele belge yüklenip indirilebiliyor. Portalda bordro indirilebiliyor (veri varsa).
5. Canlı `dobedan_hr` veritabanında satır sayısı değişmedi (K6 veri düzeltmesi hariç; o da raporda).

## Kabul kriterleri
- [ ] K1–K8 kapandı, her biri için regresyon testi var.
- [ ] Test DB otomatik oluşuyor; canlı DB'ye karşı test çalıştırma imkânsız.
- [ ] Paralel suite yeşil, süre kazanımı raporda.
- [ ] Deprecation 0.
- [ ] Her model için factory (istisna listesi hariç).
- [ ] Bölüm C mimari testleri var ve yeşil.
- [ ] Pint temiz, Larastan baseline ile yeşil.
- [ ] Kapsam taban çizgisi ölçüldü ve belgelendi.
- [ ] `TEST.md`, `.cursorrules` DoD ve `DURUM.md` güncel.
- [ ] Rapor: `docs/cursor-gorevleri/raporlar/ADIM_03A_RAPOR.md`.
