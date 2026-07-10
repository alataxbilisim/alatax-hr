# FAZ 1 — PostgreSQL Geçişi Raporu

**Branch:** `faz1-postgresql` (main'e dokunulmaz)  
**Başlangıç:** 2026-07-11  
**Kural:** Her adım ayrı commit; belirsizlikte DUR + buraya yaz.

---

## ÖZET TABLO (gece sonu güncellenir)

| Adım | Durum | Not |
|------|-------|-----|
| 0 Hazırlık | ✅ | branch + tarama |
| 1 Docker postgres | ✅ | mysql + postgres yan yana healthy |
| 2 Teşhis migrate | 🔄 | |
| 3 Uyumluluk fix | ⏳ | |
| 4 Squash | ⏳ | |
| 5 config/CI | ⏳ | |
| 6 mysql kaldır | ⏳ | |

---

## ADIM 0 — Hazırlık

**Tarih:** 2026-07-11  
**Ne yaptım:**
- `git checkout -b faz1-postgresql` (main'den)
- Mevcut durum okundu

**Mevcut durum:**

| Kaynak | Bulgu |
|--------|--------|
| `config/database.php` | default `env('DB_CONNECTION', 'sqlite')`; `pgsql` bağlantısı zaten tanımlı |
| `docker-compose.yml` | 6 servis: app, nginx, **mysql**, redis, queue, scheduler — postgres YOK (ADIM 1'de eklendi) |
| Migrations | **64** dosya (`backend/database/migrations/`) |
| `phpunit.xml` | `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` |
| `Dockerfile` | yalnızca `pdo_mysql` idi — ADIM 1'de `pdo_pgsql` + `libpq-dev` eklendi |
| `.env.docker.example` | `DB_CONNECTION=mysql` default |

**Ön tarama (migration grep, henüz çalıştırılmadı):**
- Çok sayıda `->enum(...)` (users.type, leave status, companies.status, …)
- Çok sayıda `->json(...)` (custom_fields, settings, widgets, …)
- MySQL tipi: `mediumText`, `longText`, `unsignedTinyInteger` (cache/jobs/telescope)

**Sonuç:** Hazırlık tamam. MySQL dokunulmadı.  
**Karar gerekiyor mu?** Hayır.

---

## ADIM 1 — Docker'a PostgreSQL (mysql kalır)

**Tarih:** 2026-07-11  
**Ne yaptım:**
- `docker-compose.yml`: `postgres:16-alpine`, volume `postgres_data`, healthcheck `pg_isready`, host port **5432** (boştu)
- `.env.docker.example`: `POSTGRES_*` + pgsql test komutu (yorum); mysql default kaldı
- `backend/Dockerfile`: `libpq-dev` + `pdo_pgsql` (teşhis için zorunlu)
- `docker compose up -d --build postgres app`

**Sonuç:**
- `alatax-hr-postgres` → **healthy**, `pg_isready` OK
- `alatax-hr-mysql` → **healthy** (yan yana)
- `pdo_pgsql` + `pdo_mysql` app container'da yüklü
- App default hâlâ mysql (`DB_HOST=mysql`)

**Karar gerekiyor mu?** Hayır.

---
