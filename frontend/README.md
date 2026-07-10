# ALATAX HR Frontend - Monorepo

Bu proje, ALATAX HR SaaS platformu için **3 ayrı frontend uygulaması** içeren bir monorepo yapısıdır.

## 📁 Proje Yapısı

```
frontend/
├── apps/
│   ├── superadmin/    # SuperAdmin Panel (Port 3001)
│   ├── company/      # Firma Panel (Port 3002)
│   └── portal/       # Personel Portal (Port 3003)
├── packages/
│   └── shared/       # Ortak kod, componentler, servisler
└── _archive_old_app/ # Eski tek uygulama (arşiv)
```

## 🚀 Hızlı Başlangıç

### Tüm Uygulamaları Başlat
```bash
pnpm install
pnpm dev
```

### Tek Bir Uygulamayı Başlat
```bash
# SuperAdmin Panel
pnpm dev:superadmin

# Firma Panel
pnpm dev:company

# Personel Portal
pnpm dev:portal
```

## 🌐 Uygulama URL'leri

| Uygulama | Port | URL | Açıklama |
|----------|------|-----|----------|
| **SuperAdmin** | 3001 | http://localhost:3001 | Platform yönetim paneli |
| **Company** | 3002 | http://localhost:3002 | Firma HR yönetim paneli |
| **Portal** | 3003 | http://localhost:3003 | Personel self-servis portalı |

## 📦 Paket Yönetimi

Bu proje **pnpm workspaces** kullanır. Tüm bağımlılıklar root seviyesinde yönetilir.

### Yeni Paket Ekleme
```bash
# Root seviyesinde (tüm uygulamalar için)
pnpm add <package-name> -w

# Belirli bir uygulamaya
pnpm add <package-name> --filter @alatax/superadmin
```

## 🏗️ Build

### Tüm Uygulamaları Build Et
```bash
pnpm build
```

### Tek Bir Uygulamayı Build Et
```bash
pnpm build:superadmin
pnpm build:company
pnpm build:portal
```

## 📝 Notlar

- Eski tek uygulama yapısı `_archive_old_app/` klasöründe arşivlenmiştir
- Her uygulama bağımsız olarak çalışır ve deploy edilebilir
- Ortak kod `packages/shared/` içinde tutulur
- Her uygulama kendi `vite.config.ts` ve `package.json` dosyasına sahiptir
