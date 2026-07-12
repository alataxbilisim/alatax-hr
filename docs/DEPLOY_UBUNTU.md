# Ubuntu’da Docker ile ALATAX HR (temiz makine)

Bu belge **yalnızca dokümantasyondur**. `docker-compose.yml` ve compose servisleri değiştirilmez.

Hedef: temiz bir Ubuntu sunucu/VM’de API + PostgreSQL + Redis’i Docker ile ayağa kaldırmak; frontend’i host’ta pnpm ile (veya isteğe bağlı build+serve ile) çalıştırmak.

---

## Gereksinimler

- Ubuntu 22.04+ (önerilir)
- Docker Engine + Compose v2 (`docker compose`)
- Git
- Frontend için: Node.js 20+ ve pnpm (`corepack enable && corepack prepare pnpm@latest --activate`)

Portların boş olduğundan emin olun:

| Servis | Host port |
|--------|-----------|
| API (nginx) | `8000` |
| PostgreSQL | `5432` |
| Redis | `6379` |
| SuperAdmin SPA | `3001` |
| Company SPA | `3002` |
| Portal SPA | `3003` |

---

## 1) Klon

```bash
git clone <repo-url> alatax-hr
cd alatax-hr
git checkout faz4-form-engine   # veya çalışılacak branch
```

---

## 2) Ortam dosyası (secret’sız şablon)

```bash
cp .env.docker.example .env
```

Şablonda secrets yok / örnek değerler vardır. En azından kontrol edin:

- `DB_CONNECTION=pgsql`
- `DB_HOST` compose ile container ağı üzerinden gelir (`postgres`)
- `APP_KEY` boşsa sonraki adımda üretin

**Uyarı:** Gerçek production secret’larını (mail, SMS, OAuth) repoya yazmayın; sunucuda yerel `.env` ile doldurun.

---

## 3) Docker Compose up

```bash
docker compose up -d --build
docker compose ps
```

Beklenen servisler: `app`, `nginx`, `postgres`, `redis`, `queue`, `scheduler` (ve varsa legacy `mysql`).

`APP_KEY` üretimi:

```bash
docker compose exec app php artisan key:generate --show
# Çıktıyı host `.env` içindeki APP_KEY= satırına yapıştırın
docker compose up -d app queue scheduler
```

---

## 4) Migrate + seed

```bash
docker compose exec app php artisan migrate --seed
# Temiz kurulumda gerekirse:
# docker compose exec app php artisan migrate:fresh --seed
```

Lookup Engine değerleri `LookupSeeder` ile gelir (`DatabaseSeeder` zincirinde olmalı; yoksa):

```bash
docker compose exec app php artisan db:seed --class=LookupSeeder
```

Sağlık:

```bash
curl -s http://localhost:8000/up
```

---

## 5) Frontend

### A) Hızlı geliştirme (önerilen)

```bash
cd frontend
pnpm install
pnpm --filter @alatax/superadmin dev   # http://localhost:3001
pnpm --filter @alatax/company dev      # http://localhost:3002
pnpm --filter @alatax/portal dev       # http://localhost:3003
```

API base URL: uygulama env / Vite proxy — mevcut monorepo ayarlarına uyun (`VITE_API_URL` vb.).

### B) İsteğe bağlı: production build + basit serve

Compose’a dokunmadan host’ta:

```bash
cd frontend
pnpm install
pnpm build
# Örnek: herhangi bir static server
pnpm --filter @alatax/company exec vite preview --port 3002 --host
```

veya `npx serve apps/company/dist -l 3002`. SPA history fallback gerekir.

---

## 6) İlk login

Seed sonrası demo/superadmin hesapları proje seed dokümanında (`docs/BURADAN_BASLA.md` / seeder) tanımlıdır. Company admin ile:

1. http://localhost:3002 → giriş
2. 2FA açıksa: 6 haneli kod / kurtarma kodu ekranı (`requires_2fa`)
3. Yönetim → **Listeler** (`/lookups`) — `management.lookups.view` gerekir

---

## 7) Sorun giderme

| Belirti | Ne yapılır |
|---------|------------|
| Port çakışması (`8000`/`5432`) | `.env` / compose host port mapping’ini değiştirin veya çakışan süreci kapatın |
| Eski config cache | `docker compose exec app php artisan config:clear && php artisan cache:clear` |
| Migration hatası | `docker compose logs app` / `postgres`; DB credentials |
| Lookup boş dropdown | `php artisan db:seed --class=LookupSeeder` |
| Frontend API CORS / 401 | API URL, Sanctum token, HTTPS mismatch |
| Queue işleri gitmiyor | `docker compose ps queue` ve loglar |

```bash
docker compose exec app php artisan config:clear
docker compose exec app php artisan route:clear
docker compose logs -f nginx app
```

---

## Notlar

- Frontend varsayılan olarak Docker **dışında**dır (README ile aynı model).
- Bu dosya compose dosyalarını değiştirmez; sadece kurulum yolunu anlatır.
- XAMPP ile paralel kullanımda host portlarına dikkat edin.
