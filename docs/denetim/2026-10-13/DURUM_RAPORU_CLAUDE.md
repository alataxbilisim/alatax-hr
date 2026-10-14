# Dobedan HR — Claude Bağımsız Durum Değerlendirmesi + Cursor Çapraz Doğrulaması

**Tarih:** 13 Ekim 2026 · **Branch:** `faz4-form-engine` @ `f006f93` · **Yöntem:** Salt okuma (kod + git + log + `docs/claude-denetim/cikti/` canlı DB/route çıktıları). Çapraz doğrulama sonucu §16'da.
**Amaç:** `docs/DURUM_RAPORU_CURSOR.md` ile çapraz doğrulama. Bölüm 1'deki tablo Cursor promptundaki tabloyla aynı sırada.

---

## 0. Özet

- Kod tabanı sağlam ve disiplinli: 666 API route, 565/580 frontend çağrısı backend'de karşılığını buluyor, kodda sıfır TODO/FIXME, `.gitignore` ve satır sonu (LF) hijyeni temiz.
- Son geliştirme **6 Ağustos 2026**'da bitmiş (Faz G + Faz 6 öncesi kapatma). Sıradaki iş ROADMAP'e göre **Faz 6 (W2 iade akışı + modül derinleştirme)**.
- ~~Veritabanı sıfırdan kurulmuş~~ **DÜZELTME:** Canlı DB eski veriyi taşıyor (110 migration kaydı, son batch 19, 60 personel, 62.903 Telescope kaydı). 12 Ekim'deki tablo-yok hataları geri yükleme sırasında oluşmuş.
- Genel sağlık notu (çapraz doğrulama sonrası): **6 / 10** (Cursor: 4/10; gerekçe §16). Mimari ve test güçlü; düşüren noktalar: `main` 175 commit geride, iki kırık frontend butonu, controller şişkinliği ve `.cursorrules`'tan sapma (inline validation), ortam yapılandırmasındaki MySQL kalıntıları.

**Güncel öncelik listesi §16.5'te.** İlk turdaki bulgular:
1. `backend/.env` → `DB_CONNECTION=mysql`, `127.0.0.1:3306`, `dobedan-hr`. Docker'da compose ezdiği için sorun yok; backend XAMPP/host üzerinden çalıştırılırsa yanlış (boş) MySQL'e bağlanır.
2. `main` branch'i 11 Temmuz'da kalmış, `faz4-form-engine` 175 commit önde. Tek hat üzerinde çalışılıyor; release/merge stratejisi yok.
3. Onboarding'de 2 canlı kırık çağrı: "şablonu çoğalt" ve "görevi atla" backend'de route'u olmayan uç noktalara gidiyor (404).
4. `storage/logs/laravel.log` 235 MB, rotasyon yok (`LOG_CHANNEL` stack/single).
5. Claude Projesindeki dokümanlar (ör. `GUNCEL_DURUM_RAPORU.md`, 14 Temmuz, "294 test, Form Engine yok") repodaki sürümün çok gerisinde; Claude tarafında yanlış bağlam riski.

---

## 1. Envanter (çapraz doğrulama tablosu)

| Metrik | Claude değeri | Nasıl sayıldı |
|---|---|---|
| Git: aktif branch | `faz4-form-engine` | `git status -sb` |
| Git: son commit | `f006f93` · 2026-08-06 14:54 · fix(export): DataScope birligi, chunk/stream ve export registry | `git log -1` |
| Git: toplam commit | 233 | `git rev-list --count HEAD` |
| Git: commit'lenmemiş değişiklik | 0 değişmiş, 25 izlenmeyen (untracked) | `git status --short` |
| Git: stash | 0 | `git stash list` |
| Git: remote farkı | 0 / 0 (senkron) | `git status -sb` |
| Git: main ile fark | faz4 175 önde, 0 geride | `git rev-list --count main..faz4-form-engine` |
| Backend | Laravel ^12, Sanctum 4, Spatie Permission 6, Telescope 5, Google2FA | `composer.json` |
| PHP | 8.2 (Docker image `php:8.2-fpm-bookworm`) | `backend/Dockerfile` |
| Frontend | React 19.2, RRD 7, Vite, TS 5.9.3, pnpm monorepo (3 app + shared) | `package.json` |
| PostgreSQL | 16-alpine (compose) — canlı sürüm bekleniyor | `docker-compose.yml` |
| Docker servisleri | 7: app, nginx, mysql, postgres, redis, queue, scheduler | `docker-compose.yml` |
| Migration dosyası | 3 + squash şema (`database/schema/pgsql-schema.sql`, 13.901 satır) | `ls database/migrations` |
| Şemadaki tablo (kod) | 155 | `grep -c '^CREATE TABLE'` |
| Şemadaki FK / index | 431 / 189 | grep |
| View / function / trigger | 0 / 0 / 0 | grep |
| Model | 116 | `find app/Models` |
| Controller | 114 | `find app/Http/Controllers` |
| Service sınıfı | 122 | `find app/Services` |
| Policy / FormRequest / Resource | 9 / 16 / 1 | find |
| Enum | 81 | `ls app/Enums` |
| API route | 666 (654 tanım satırı + 4 resource genişlemesi) | `routes/api.php` ayrıştırıcı (`route:list` ile teyit bekleniyor) |
| Web route | ~1 dosya, 7 satır | `routes/web.php` |
| Frontend route (company / portal / superadmin) | 124 / 24 / 11 | `App.tsx` içindeki `path=` |
| Frontend sayfa dosyası | 105 / 20 / 9 | `src/**/pages/*.tsx` |
| Backend test dosyası | 117 (111 Feature + 6 Unit) | find |
| Test metodu | ~657 `test_` + attribute'lar | grep |
| Son tam suite | 725 passed / 3180 assertion (5 Ağu, 5 rastgele sıra koşusu) | `docs/uzun-kosu-suite-summary.txt` |
| TODO/FIXME/HACK | 0 (backend ve frontend) | grep |
| Modül (ModuleSeeder) | 14 | `ModuleSeeder.php` |

> Not: `GUNCEL_DURUM_RAPORU.md` "758 passed" diyor, son suite log'u 725. Cursor bugün koşarsa gerçek sayı netleşir.

---

## 2. Git ve depo

- Branch'ler: `main` (11 Tem), `faz1-postgresql`, `faz2-rbac-audit` (main'e dahil, ölü), `faz3-tasarim` (13 commit main'de yok ama faz4 içinde), `faz4-form-engine` (aktif). Remote ile hepsi senkron.
- Untracked 25 dosya: `backend/scripts/denetim`, `backend/scripts/uzun-kosu`, tek seferlik düzeltme betikleri (`fix-user-where-company-id.php`, `strip-employee-position-attr.php`…), `docs/*.json` ölçüm çıktıları, `scripts/tmp-*`. Ya commit'lenmeli ya `.gitignore`'a alınmalı.
- Git'te izlenen bayat dosyalar: `frontend/_e_lint_*.txt`, `frontend/_e_tsc_*.txt` (10 Temmuz).
- `.gitignore` doğru çalışıyor: `.env`, kök `*.log`, `composer.phar`, `docs/backups` izlenmiyor.
- `.gitattributes` `eol=lf`; tüm `.sh` betikleri LF (CRLF yok).

## 3. Ortam ve altyapı

- Compose yorumları bayat: "app default hâlâ mysql", "Faz 1'de PostgreSQL ile değiştirilecek". `app` servisi hâlâ `mysql` healthy olmasını bekliyor → gereksiz bir container ve açılış bağımlılığı.
- Kök `.env` = `.env.docker.example` kopyası; `MYSQL_*` anahtarları var, `CORS_ALLOWED_ORIGINS` yok (config varsayılanına düşüyor).
- `backend/.env` MySQL'e işaret ediyor (bkz. Özet #1).
- Taşıma izleri: eski yol `C:\xampp\htdocs\dobedan-hr` yalnızca derlenmiş view cache'inde (`storage/framework/views`), zararsız; `php artisan view:clear` temizler. `bootstrap/cache` içinde config/route cache yok (iyi).
- Frontend `node_modules` pnpm ile kurulmuş (junction'lar). Kök `frontend/` içinde hem `pnpm-lock.yaml` hem `package-lock.json` var → karışık paket yöneticisi riski.
- `backend/package.json` + `backend/node_modules` + boş `backend/frontend/apps` → Laravel iskeletinden kalma, kullanılmıyor görünüyor.

## 4. Backend mimarisi

- Katmanlı yapı: Controller → Service (122) → Model; DataScope, CompanyContext, Settings Registry, Dataset Registry, Approval Registry.
- Route güvenliği: korumalı grup `auth:sanctum, deny.2fa.challenge, company.context, company.active, branch.context` + route başına `permission:*` ve `module.access:*`. Auth uçları `throttle:auth`.
- **Kural sapması:** `.cursorrules` "validasyon her zaman FormRequest" diyor; 16 FormRequest'e karşılık controller'larda **242 inline `validate()`** var. API Resource yalnızca 1.
- Şişkin dosyalar: `EmployeeController` 1298, `UserController` 1183, `AuthController` 863, `ReportPivotService` 833 satır.
- Ham SQL: 94 kullanım. `ReportQueryBuilder` filtreleri alan/operatör whitelist'inden geçiyor (güvenli). `PortableEnum` / `PostgresAdvisoryLock` iç sabitlerle çalışıyor.
- `$guarded = []` kullanan model yok.
- Telescope prod paketinde (`require`) ve `TELESCOPE_ENABLED` varsayılanı `true`; gate yalnızca local dışı ortamda devreye giriyor. Prod'da `TELESCOPE_ENABLED=false` şart.

## 5. Veritabanı (canlı DB — `topla.ps1` çıktısı)

- `config('database.default')` = **pgsql / postgres / dobedan_hr** (doğrulandı). Laravel 12.43.1, PHP 8.2.34.
- Canlı 155 tablo = kod 155 tablo. Farklar yalnızca squash sonrası 3 migration'ın beklenen etkileri (`users.company_id→home_company_id`, `employees.position` kaldırıldı, soft-delete partial unique index'ler). **Şema sapması yok.**
- 3 migration `Ran` (batch 19). Satır: 60 personel, 59 kullanıcı, 444 izin, 7 rol, 14 modül, 4 organizasyon, 300 puantaj kaydı; 93 tablo boş.
- `public/storage` **NOT LINKED** (avatar/logo URL'leri 404).
- Host'ta XAMPP'ın `mysqld` (3306) ve `httpd` (80) servisleri hâlâ çalışıyor; Docker MySQL 3307'de.

### 5a. İlk ara rapordaki not (geçersiz)

Kod tarafı: squash şema 155 tablo, 431 FK, 189 index.
Log kanıtı: `2026-10-12 14:43` iki kez `relation "telescope_entries" does not exist` → DB o anda boştu; `14:50`'de `EmployeeRequest` 1–3 için onay akışı başlıyor → **migrate + demo seed yeni bilgisayarda yapılmış.** 5 Ağustos dump'ı (20 MB) yüklendiyse satır sayıları bunu gösterecek.

Yapılacak karşılaştırmalar (`topla.ps1` sonrası): canlı tablo sayısı vs 155, `migrate:status` (3 migration işlenmiş mi), satır sayıları, `config('database.default')`.

## 6. Frontend

- 3 SPA: company (:3002, 124 route), portal (:3003, 24), superadmin (:3001, 11) + `@dobedan/shared`.
- `tsc` / lint bu incelemede koşturulamadı (Windows junction'ları sanal makinede okunamıyor) → `topla.ps1` 11–12. adımlar.

## 7. Backend ↔ Frontend sözleşmesi

580 benzersiz (method, path) çağrısının **565'i** backend route'u ile eşleşti, method uyumsuzluğu 0.
Karşılığı olmayan 15 çağrı (`packages/shared/src/services/api.ts`):

| Çağrı | Kullanılıyor mu | Etki |
|---|---|---|
| `POST /onboarding/templates/{id}/duplicate` | **Evet** — `OnboardingPage.tsx:168` | Buton 404 |
| `POST /onboarding/processes/{id}/tasks/{id}/skip` | **Evet** — `ProcessDetailPage.tsx:110` | Buton 404 |
| `POST /onboarding/processes/{id}/tasks`, `GET /onboarding/dashboard` | Hayır | Ölü kod |
| `GET /leaves/requests/my`, `/pending`, `/leaves/calendar/today`, `/upcoming` | Hayır | Ölü kod |
| `GET/POST/PUT/DELETE /documents/dashboards…` (6) | Hayır | Ölü kod |
| `GET /employees/export-report` | Hayır | Ölü kod |

## 8. Test ve kalite

- Son kayıtlı sonuç: 725 passed, 5 rastgele sıralı koşuda kararlı (~17–18 dk/koşu).
- Yeni bilgisayarda suite henüz koşulmadı → ilk yapılacak doğrulama.

## 9. Güvenlik (ilk tur)

- Pozitif: CORS env'den, throttle, 2FA challenge ayrımı, permission middleware yoğun, rapor filtre whitelist'i, gizli bilgi git'te değil.
- Dikkat: Telescope prod varsayılanı, `APP_DEBUG=true` (dev için normal), `backend/.env` ile kök `.env` arasında iki ayrı gerçek.

## 10. Dokümantasyon

- Repo `docs/` güncel (6 Ağustos) ve tutarlı: `BURADAN_BASLA` → `GUNCEL_DURUM_RAPORU` → `ROADMAP`.
- Claude Projesindeki kopyalar 14 Temmuz'da kalmış; `FAZ5_RAPOR`, `FAZ6_ONCESI_KAPATMA`, `GRUP_MIMARI_TESHIS`, `KVKK_RAPOR`, `FAZ_G_KAPANIS_OZET` projede yok.

## 11. Açık borçlar (repo dokümanlarına göre, kodla tutarlı)

İzin onayı → puantaj wire (Faz 6 B3) · İade/`returned` akışı (W2) · Mailtrap E2E · Bundle < 1 MB · i18n EN · Form Engine 4A tam geçiş · Saklama politikası job'ları · Cascading picklist · Şirkete özel rol · 23 personelde belirsiz `position_id` (5 Ağu verisi).

## 13. Öneriler

**Hemen (yeni PC stabilizasyonu)**
1. `topla.ps1` çıktısıyla DB'yi doğrula; demo veri yerine eski veriler gerekiyorsa 5 Ağu dump'ını geri yükle (S).
2. Docker'da tam test suite'i koş, sonucu 725/758 ile karşılaştır (S).
3. `backend/.env`'i pgsql'e çek veya XAMPP kullanımını bırak; tek doğruluk kaynağı (S).
4. Onboarding duplicate/skip route'larını ekle ya da butonları kaldır (S).
5. Log rotasyonu: `LOG_STACK=daily`, eski `laravel.log`'u arşivle (S).

**Kısa vade**
6. Compose'dan `mysql` bağımlılığını ve bayat yorumları temizle (S).
7. Untracked betik/çıktıları ayıkla, `_e_*.txt` dosyalarını sil (S).
8. `faz4-form-engine` → `main` merge ve tag (`v0.x-faz-g`) (M).
9. Ölü 13 API fonksiyonunu kaldır (S).
10. Claude Projesindeki dokümanları repodaki güncel sürümle değiştir (S).

**Orta vade**
11. Faz 6'ya başlarken yeni kodda FormRequest/Resource kuralına uy; büyük controller'ları dokunuldukça böl (L).
12. Prod için `TELESCOPE_ENABLED=false` ve on-prem checklist'ine ekle (S).

## 14. Açık sorular

- Yeni PC'de demo veri ile mi devam ediyoruz, eski verileri mi yüklüyoruz?
- Backend'i yalnızca Docker'da mı çalıştırıyorsun, XAMPP hâlâ kullanılıyor mu?
- `main`'e merge ne zaman? (Kural: "yalnız faz4-form-engine")
- Faz 6'da ilk iş W2 (iade) mi, yoksa senin öncelikli istediğin bir modül mü?


---

## 16. Çapraz Doğrulama (Claude ↔ Cursor)

### 16.1 İki tarafın aynı bulduğu (yüksek güven)
| Konu | Claude | Cursor |
|---|---|---|
| Branch / HEAD / 233 commit / remote senkron / main 175 geride | ✔ | ✔ |
| 116 model, 114 controller, 122 servis, 9 policy, 16 FormRequest, 1 Resource, 81 enum | ✔ | ✔ |
| 666 API route (Claude ayrıştırıcı = `route:list` birebir) | ✔ | ✔ |
| 155 tablo, 431 FK, şema = squash + 3 migration | ✔ | ✔ |
| Frontend 124/24/11 route, 105/20/9 sayfa | ✔ | ✔ |
| 15 eşleşmeyen FE çağrısı, 2'si canlı (onboarding duplicate/skip) | ✔ | ✔ (aynı tablo) |
| 242 inline validate, şişkin controller'lar | ✔ | ✔ |
| `backend/.env` bayat MySQL, Telescope varsayılan açık, `_e_*.txt` bayat | ✔ | ✔ |

### 16.2 Cursor'un bulup Claude'un ilk turda kaçırdığı — Claude tarafından BAĞIMSIZ DOĞRULANDI
| # | Bulgu | Claude kanıtı |
|---|---|---|
| 1 | **2FA atlatma:** portal (77) ve admin (31) route grupları `deny.2fa.challenge` içermiyor; challenge token bu uçlara erişebilir | `routes/api.php:1394` ve `:1434` grup tanımları; `route:list` middleware analizi: panel 541 ✔, portal 77 ✘, admin 31 ✘ |
| 2 | **`private` disk tanımsız** → belge/bordro yükleme-indirme 500 | `config/filesystems.php` yalnız local/public/s3; 8 dosyada 15 kullanım. Disk **hiçbir commit'te tanımlanmamış**; tek test `Storage::fake('private')` ile maskelemiş (`CompanyGapsC5Test.php:144`) |
| 3 | **MySQL'e özgü SQL** (`YEAR`, `MONTH`, `TIMESTAMPDIFF`, `CURDATE`, çift tırnaklı string) | `EmployeeController.php:1225-1249`, `EmployeeDashboardController.php:350-364`, `Documents/ReportController.php:220-244` |
| 4 | Test DB `dobedan_hr_testing` yok → suite koşmuyor | `phpunit.xml:31` bu DB'yi zorunlu kılıyor (DB varlığı Cursor'un psql kanıtına dayanıyor) |
| 5 | Bağımlılık açıkları (composer 46, pnpm 137; jspdf critical) | Claude doğrulayamadı (audit aracı yok) — Cursor kanıtına dayanıyor |

### 16.3 Claude'un bulup Cursor'un raporlamadığı
- `storage/logs/laravel.log` **235 MB**, rotasyon yok (`LOG_STACK=single`).
- Host'ta XAMPP `mysqld`/`httpd` hâlâ çalışıyor (port 3306/80) — kullanılmıyorsa kapatılmalı.
- Claude Projesindeki dokümanlar 14 Temmuz sürümü (repo 6 Ağustos).
- Eski klasör yolu (`dobedan-hr`) yalnız derlenmiş view cache'inde.

### 16.4 Ayrışmalar
| Konu | Claude | Cursor | Sonuç |
|---|---|---|---|
| Sağlık notu | 6/10 | 4/10 | Kritik 3 hata gerçek ama her biri küçük (S) düzeltme; mimari/test altyapısı güçlü. 6/10 öneriyorum. |
| DB yeni mi kuruldu? | İlk turda "sıfırdan" dedi → **yanlıştı**, düzeltildi | Belirtmedi | Eski veri geri yüklenmiş. |
| İzlenmeyen dosya | 25 (dizin bazlı) | 52 (`-uall`) | Aynı küme, farklı sayım yöntemi. |
| CRLF | `.sh` dosyalarında 0 | 330 dosya çalışma kopyasında CRLF (`core.autocrlf=true`) | Index temiz; Windows checkout etkisi. Ubuntu deploy'da sorun yok. |

### 16.5 Ortak öncelik sırası (önerilen ilk sprint)
1. 2FA: `deny.2fa.challenge` portal + admin gruplarına + regresyon testi (S)
2. `private` disk tanımı + gerçek config testi (S)
3. MySQL SQL → PostgreSQL uyumlu (`EXTRACT`, `age()`, tek tırnak) + uç testleri (M)
4. `dobedan_hr_testing` oluştur, suite'i koş, sayıyı dokümanlara yaz (S)
5. Onboarding duplicate/skip uçları (S)
6. `storage:link`, log rotasyonu, Telescope/Sanctum expiry (S)
7. Bağımlılık güncellemeleri (M) → ardından Faz 6 (W2 iade akışı)
