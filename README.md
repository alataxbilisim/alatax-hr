# Dobedan HR

Türkiye odaklı multi-tenant B2B HR SaaS.

| Katman | Teknoloji |
|--------|-----------|
| Backend | Laravel 12, PHP 8.2, Sanctum, Spatie Permission |
| Frontend | pnpm monorepo — SuperAdmin `:3001`, Company `:3002`, Portal `:3003` |
| API | `http://localhost:8000` |

Doküman haritası: [`docs/README.md`](docs/README.md) · Güncel durum: [`docs/DURUM.md`](docs/DURUM.md) · i18n: [`docs/spec/I18N.md`](docs/spec/I18N.md)

---

## Docker ile çalıştırma (backend + PostgreSQL + Redis)

Frontend **Docker dışında** kalır (dev’de host’ta `pnpm`). Sadece API ve altyapı container’dadır.  
Backend’i çalıştırmanın desteklenen tek yolu Docker’dır; `php artisan` komutları her zaman `docker compose exec app ...` ile çalıştırılır.

### Gereksinimler

- Docker Desktop (Compose v2)
- Host’ta frontend için: Node 22 LTS + pnpm 9.15.9 (kök `.nvmrc`, `frontend/package.json` → `packageManager`)

> **Veritabanı:** yalnız PostgreSQL 16 (`DB_HOST=postgres`, host port `5432`). MySQL/MariaDB/SQLite desteklenmez. Host portları `5432`, `6379` ve `8000` başka bir süreç tarafından kullanılmamalıdır.

### 1) Ortam dosyası

```bash
cp .env.docker.example .env
```

Kök `.env` container’lar için tek ortam kaynağıdır (`env_file`). İçinde `DB_CONNECTION=pgsql` ve `DB_PORT=5432` olmalı (örnek dosyada varsayılan). `.env` değişikliğinden sonra container’ları yeniden oluştur: `docker compose up -d`.

`APP_KEY` boşsa, container ayağa kalktıktan sonra üret:

```bash
docker compose exec app php artisan key:generate --show
# Çıktıyı .env içindeki APP_KEY= satırına yapıştır, sonra:
docker compose up -d app queue scheduler
```

Mevcut bir kurulumda `APP_KEY` **asla değiştirilmez**; değişirse şifreli alanlar ve oturumlar okunamaz hâle gelir.

### 2) Servisleri başlat

```bash
docker compose up -d --build
docker compose ps
```

Servisler (6): `app` (php-fpm), `nginx` (:8000), **`postgres` (:5432)**, `redis` (:6379), `queue`, `scheduler`.

### 3) Migrate + seed

```bash
docker compose exec app php artisan migrate:fresh --seed
```

### 4) Sağlık kontrolü

```bash
curl http://localhost:8000/up
```

Redis:

```bash
docker compose exec app php artisan tinker --execute="Illuminate\Support\Facades\Redis::ping();"
# veya
docker compose exec redis redis-cli ping
```

### 5) Frontend (host)

```bash
cd frontend
pnpm install
pnpm --filter @dobedan/superadmin dev   # :3001
pnpm --filter @dobedan/company dev      # :3002
pnpm --filter @dobedan/portal dev       # :3003
```

SPA’ların API base URL’i `http://localhost:8000` olmalı.

### Durdurma

```bash
docker compose down          # volume’lar kalır
docker compose down -v       # Postgres/Redis verisini de siler — geri alınamaz, önce pg_dump yedeği al
```

### Veritabanı notu

Veritabanı yalnız **PostgreSQL 16**’dır. MySQL servisi, `pdo_mysql` ve MySQL/MariaDB/SQLite bağlantıları Adım 01’de kaldırıldı; `tests/Architecture/DatabaseDriverGuardTest` geri gelmelerini engeller.

---

## XAMPP / host PHP ile çalıştırma

Desteklenmez. Backend yalnız Docker üzerinden çalıştırılır (yukarıdaki bölüm).
