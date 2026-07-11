# ALATAX HR

Türkiye odaklı multi-tenant B2B HR SaaS.

| Katman | Teknoloji |
|--------|-----------|
| Backend | Laravel 12, PHP 8.2, Sanctum, Spatie Permission |
| Frontend | pnpm monorepo — SuperAdmin `:3001`, Company `:3002`, Portal `:3003` |
| API | `http://localhost:8000` |

Yol haritası: [`docs/ROADMAP.md`](docs/ROADMAP.md) · Başlangıç: [`docs/BURADAN_BASLA.md`](docs/BURADAN_BASLA.md) · i18n: [`docs/I18N.md`](docs/I18N.md)

---

## Docker ile çalıştırma (backend + PostgreSQL + Redis)

Frontend **Docker dışında** kalır (dev’de host’ta `pnpm`). Sadece API ve altyapı container’dadır.  
Mevcut XAMPP kurulumu bozulmaz; Docker ayrı bir seçenektir (`backend/.env` değiştirilmez).

### Gereksinimler

- Docker Desktop (Compose v2)
- Host’ta frontend için: Node 20+, pnpm

> **XAMPP ile birlikte:** Host’ta XAMPP MySQL `:3306` kullanıyorsa legacy MySQL container için `.env` içinde `MYSQL_HOST_PORT=3307` kullan. **App default DB: PostgreSQL** (`DB_HOST=postgres`, host port `5432`). Redis `:6379` ve API `:8000` boşsa değiştirme.

### 1) Ortam dosyası

```bash
cp .env.docker.example .env
```

`.env` içinde `DB_CONNECTION=pgsql` ve `DB_PORT=5432` olmalı (örnek dosyada varsayılan). Legacy MySQL satırları yorumlu kalır.

`APP_KEY` boşsa, container ayağa kalktıktan sonra üret:

```bash
docker compose exec app php artisan key:generate --show
# Çıktıyı .env içindeki APP_KEY= satırına yapıştır, sonra:
docker compose up -d app queue scheduler
```

Alternatif: host’taki mevcut `backend/.env` zaten `APP_KEY` içeriyorsa Docker onu kullanır; compose `DB_HOST=postgres`, `REDIS_HOST=redis` vb. override eder.

### 2) Servisleri başlat

```bash
docker compose up -d --build
docker compose ps
```

Servisler: `app` (php-fpm), `nginx` (:8000), **`postgres` (:5432)**, `mysql` (legacy, :3307), `redis` (:6379), `queue`, `scheduler`.

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
pnpm --filter @alatax/superadmin dev   # :3001
pnpm --filter @alatax/company dev      # :3002
pnpm --filter @alatax/portal dev       # :3003
```

SPA’ların API base URL’i `http://localhost:8000` olmalı.

### Durdurma

```bash
docker compose down          # volume’lar kalır
docker compose down -v       # Postgres/MySQL/Redis verisini de siler
```

### Faz 1 notu

Default DB **PostgreSQL**. `mysql:` servisi **legacy** olarak compose’da kalır (silinmedi); Faz 1 sonu / Faz 2’de kaldırılacak. Migration squash Faz 2’ye ertelendi.

---

## XAMPP ile çalıştırma (mevcut)

Docker kullanmadan önceki akış: Apache/PHP + `backend/.env` (**önerilen: pgsql**; sqlite/mysql legacy), `php artisan serve` veya vhost, frontend host’ta pnpm.
