# Ubuntu’da Docker ile ALATAX HR (temiz makine)

Bu belge **yalnızca dokümantasyondur**. `docker-compose.yml` ve compose servisleri değiştirilmez.

Hedef: temiz bir Ubuntu sunucu/VM’de API + PostgreSQL + Redis’i Docker ile ayağa kaldırmak; frontend’i host’ta pnpm ile (veya isteğe bağlı build+serve ile) çalıştırmak.

**Aşama 1 (gerçek deploy turu):** Ubuntu 26.04 @ LAN IP ile uçtan uca doğrulandı (13 Tem 2026). Aşağıdaki adımlar o turda çıkan eksikleri içerir — Faz 7 on-prem installer temeli.

---

## Gereksinimler

- Ubuntu 22.04+ (önerilir); **26.04 LTS** ile de çalıştığı doğrulandı (aşağıya bakın)
- Docker Engine + Compose v2 (`docker compose`)
- Git
- Frontend için: **Node.js 20** ve **pnpm 9.x** (aşağıdaki pin — `pnpm@latest` kullanmayın)

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

## 0) Docker + pnpm kurulumu (temiz makine)

### Docker Engine + Compose

Resmi Docker kurulumu sonrası kullanıcıyı `docker` grubuna ekleyin:

```bash
sudo usermod -aG docker "$USER"
# Aynı oturumda sudo’suz docker için:
newgrp docker
# veya oturumu kapatıp yeniden açın
docker compose version
```

`newgrp` / relogin yapılmazsa `permission denied` (docker.sock) alırsınız.

### Node 20 + pnpm 9 pin

**Node 20’de `pnpm@latest` (11.x) kırılır** (`node:sqlite` / Node 22 ister). Deploy turunda kanıtlandı — **pnpm@9.15.9** pinleyin:

```bash
# Node 20 (ör. NodeSource veya nvm)
node -v   # v20.x olmalı

# Tercih A — npm global (deploy turunda kullanılan yol)
sudo corepack disable   # corepack pnpm@latest’e zorluyorsa
sudo npm install -g pnpm@9.15.9
pnpm -v   # 9.15.9

# Tercih B — corepack ile açık pin (latest DEĞİL)
corepack enable
corepack prepare pnpm@9.15.9 --activate
pnpm -v
```

> Alternatif: Node 22 + pnpm 11 — bu belgenin varsayılanı **Node 20 + pnpm 9**’dur.

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
- LAN’dan tarayıcı açacaksanız `CORS_ALLOWED_ORIGINS` (bölüm **5b**)

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
# Beklenen: 200
```

### Seed sonrası hesap durumu (önemli)

`migrate:fresh --seed` **SuperAdmin** üretir; **Company admin / Portal personeli YOKTUR**.

1. **Firma + admin:** Company SPA register (`POST /api/v1/auth/register`) veya SuperAdmin üzerinden firma oluşturun.
2. **Portal:** Company’de personel oluşturun → portal erişimi verin (`POST /employees/{id}/portal-access` geçici şifreyi response’ta döner; e-posta kuyruğu yoksa şifreyi buradan alın).

Örnek API akışı (Company admin token ile):

```bash
# 1) Login → token
# 2) POST /api/v1/employees  (create_portal_access: false, alanlar dolu)
# 3) POST /api/v1/employees/{id}/portal-access
#    body: { "email": "...", "name": "..." }
#    → data.temporary_password
```

Portal girişinde SPA `portal_login: true` gönderir. Panel URL’leri: SuperAdmin `:3001`, Company `:3002`, Portal `:3003`.

---

## 5) Frontend

### A) Hızlı geliştirme (önerilen)

```bash
cd frontend
pnpm install
```

**LAN’dan (başka makineden) erişim için `--host` şarttır** (yoksa yalnızca localhost dinler):

```bash
pnpm --filter @alatax/superadmin dev -- --host   # :3001
pnpm --filter @alatax/company    dev -- --host   # :3002
pnpm --filter @alatax/portal     dev -- --host   # :3003
```

Aynı makinede tarayıcı yeterliyse `--host` olmadan da çalışır; uzak tarayıcı için her zaman `--host` kullanın.

### 5a) SSH / PC kapanınca paneller ölmesin (kalıcı Vite)

**Sorun:** `pnpm ... dev` doğrudan SSH terminalinde çalıştırılırsa, Windows PC kapanınca veya SSH kopunca Vite süreçleri ölür. Docker (API `:8000`) genelde ayakta kalır; `:3001/:3002/:3003` kapanır.

**Çözüm — nohup script (önerilen deneme ortamı):**

```bash
cd ~/alatax-hr
git pull
# Docker ayakta mı?
docker compose up -d
# Frontend SSH bağımsız:
chmod +x scripts/ubuntu-frontend-start.sh scripts/ubuntu-frontend-stop.sh
bash scripts/ubuntu-frontend-start.sh
```

Loglar: `~/alatax-hr/logs/frontend/*.log`  
Durdur: `bash scripts/ubuntu-frontend-stop.sh`

> Not: Bu hâlâ **Vite dev** modudur. Sunucu reboot sonrası script’i yeniden çalıştırmanız gerekir (systemd unit Faz 7 / production). Reboot sonrası Docker `restart: unless-stopped` ile API’yi kendi açar; frontend script’ini bir kez daha çalıştırın.

### 5b) LAN erişimi (Windows tarayıcı → Ubuntu Docker) — kritik

`SUNUCU_IP` = Ubuntu’nun LAN IP’si (ör. `192.168.10.156`).

#### Backend CORS (host `.env`)

`CORS_ALLOWED_ORIGINS` içine üç SPA origin’ini ekleyin:

```text
CORS_ALLOWED_ORIGINS=http://localhost:3001,http://localhost:3002,http://localhost:3003,http://SUNUCU_IP:3001,http://SUNUCU_IP:3002,http://SUNUCU_IP:3003
```

**Uzun satır tuzağı:** `nano` satırı kırabilir / kesebilir. `echo` veya `sed` tercih edin:

```bash
# Örnek: mevcut CORS satırını güvenli yaz (IP’yi değiştirin)
SUNUCU_IP=192.168.10.156
# .env içinde CORS_ALLOWED_ORIGINS=... satırını düzenleyin; tek satır olduğundan emin olun
grep '^CORS_ALLOWED_ORIGINS=' .env

# Değişiklikten sonra config cache temizleyip app’i yenileyin
docker compose exec app php artisan config:clear
docker compose up -d app nginx
```

CORS preflight doğrulama: SPA origin’den `OPTIONS` → **204** beklenir.

#### Her SPA — `VITE_API_URL`

```bash
# frontend/apps/{superadmin,company,portal}/.env.local
echo 'VITE_API_URL=http://SUNUCU_IP:8000/api/v1' > frontend/apps/company/.env.local
echo 'VITE_API_URL=http://SUNUCU_IP:8000/api/v1' > frontend/apps/superadmin/.env.local
echo 'VITE_API_URL=http://SUNUCU_IP:8000/api/v1' > frontend/apps/portal/.env.local
# Vite’ı yeniden başlatın (.env.local yalnızca start’ta okunur)
```

> **UYARI — localhost tuzağı:** SPA `VITE_API_URL` olarak `http://localhost:8000/...` bırakılırsa, Windows tarayıcısındaki JS **Windows’taki** localhost’a gider (XAMPP / boş API). Ubuntu’daki hesaplar orada yoktur → **401 Unauthorized**. Network sekmesinde login URL’si `http://SUNUCU_IP:8000/api/v1/auth/login` olmalıdır — `localhost` değil.

### B) İsteğe bağlı: production build + basit serve

Compose’a dokunmadan host’ta:

```bash
cd frontend
pnpm install
pnpm build
pnpm --filter @alatax/company exec vite preview --port 3002 --host
```

veya `npx serve apps/company/dist -l 3002`. SPA history fallback gerekir. Build öncesi aynı `VITE_API_URL` (LAN IP) ile build alın.

---

## 6) İlk login

Seed / register sonrası tipik hesaplar (ortama göre değişir; seed dokümanı: `docs/BURADAN_BASLA.md`):

| Panel | Port | Not |
|--------|------|-----|
| SuperAdmin | `:3001` | Seed’den gelir |
| Company | `:3002` | Register ile oluşturulur |
| Portal | `:3003` | Personel + portal-access gerekir |

Company admin ile:

1. `http://SUNUCU_IP:3002` → giriş
2. 2FA açıksa: 6 haneli kod / kurtarma kodu (`requires_2fa`)
3. Yönetim → **Listeler** (`/lookups`) — `management.lookups.view` gerekir

---

## 7) Sorun giderme

| Belirti | Ne yapılır |
|---------|------------|
| Port çakışması (`8000`/`5432`) | `.env` / compose host port mapping’ini değiştirin veya çakışan süreci kapatın |
| `docker.sock` permission denied | `usermod -aG docker` + `newgrp docker` / relogin |
| `pnpm` / `node:sqlite` hatası | pnpm 11 + Node 20 — `pnpm@9.15.9` pinleyin |
| Eski config cache | `docker compose exec app php artisan config:clear && php artisan cache:clear` |
| Migration hatası | `docker compose logs app` / `postgres`; DB credentials |
| Lookup boş dropdown | `php artisan db:seed --class=LookupSeeder` |
| CORS / preflight fail | `CORS_ALLOWED_ORIGINS` + `config:clear`; nano satır kırılması |
| Login **401** (LAN) | `VITE_API_URL` hâlâ `localhost` mi? → SUNUCU_IP; Vite restart |
| Portal “erişim yok” | Personelde `user_id` + aktif status; `portal_login` |
| Queue işleri gitmiyor | `docker compose ps queue` ve loglar |
| `sudo -E` uyarısı (Ubuntu 26) | zararsız olabilir; env’i açıkça geçirin |

```bash
docker compose exec app php artisan config:clear
docker compose exec app php artisan route:clear
docker compose logs -f nginx app
```

---

## Bilinen tuzaklar

1. **pnpm@latest (11) + Node 20** → kurulum/çalışma kırılır; **9.15.9** kullanın.
2. **Docker grubu** unutulursa her komutta `sudo` veya sock hatası.
3. **`VITE_API_URL=localhost`** + uzak tarayıcı → yanlış makinenin API’si → 401.
4. **CORS satırı nano’da kırılır** → origin listesi eksik kalır.
5. **Vite `--host` yok** → LAN’dan `:300x` erişilemez.
5b. **Vite SSH terminalinde** → PC/SSH kapanınca paneller ölür; `scripts/ubuntu-frontend-start.sh` kullanın.
6. **Seed ≠ hazır firma/portal** — register + personel + portal-access şart.
7. **Windows XAMPP** ile aynı portlar açıksa localhost karışıklığı artar; LAN testinde her zaman SUNUCU_IP kullanın.
8. **Otomatik deploy yok** — `git push` sunucuyu güncellemez (aşağıya bakın).

---

## Manuel güncelleme (şu anki model)

Otomatik CI → sunucu deploy **yok**. Güncelleme:

```bash
cd ~/alatax-hr
git pull
docker compose up -d --build          # backend değiştiyse
docker compose exec app php artisan migrate
cd frontend && pnpm install
# Vite süreçlerini yeniden başlat; .env.local’i koruyun
```

İleride (henüz yok):

- Basit `scripts/update-ubuntu.sh` (pull + migrate + restart)
- GitHub Actions → SSH deploy

Bunlar Faz 7 / cloud backlog’unda; bu belge installer için manuel yolu sabitler.

---

## Ubuntu 26.04 notları

Deploy turunda **Ubuntu 26.04 LTS** (resolute) kullanıldı:

- Docker Engine 29.x + Compose v5.x sorunsuz ayağa kalktı.
- `sudo -E` “ignored” uyarıları görülebilir; kritik değil.
- Paket adları / apt kaynakları 22.04 ile büyük ölçüde aynı; Node 20’yi ayrı kurun (distro Node sürümü farklı olabilir).
- `usermod -aG docker` sonrası mutlaka `newgrp` veya yeni login.

---

## Sıradaki adımlar

1. Aşama 2: sistematik test turu (`docs/TEST_TURU.md`) — onay zinciri, Lookups, Settings Studio, Kanban, 2FA, RBAC, CRUD; bulguları logla.
2. Bu belgedeki LAN / pnpm / seed adımlarını installer checklist’ine taşı (Faz 7).
3. İsteğe bağlı: manuel update script; ardından GitHub Actions SSH deploy (henüz yok).
4. Production: Vite `dev` yerine build + reverse proxy; secret’lar yalnızca sunucu `.env`.

---

## Notlar

- Frontend varsayılan olarak Docker **dışında**dır (README ile aynı model).
- Bu dosya compose dosyalarını değiştirmez; sadece kurulum yolunu anlatır.
- XAMPP ile paralel kullanımda host portlarına dikkat edin.
- Bu belge Faz 7 on-prem installer için temel girdi sayılır.
