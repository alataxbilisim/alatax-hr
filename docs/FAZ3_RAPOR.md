# Faz 3 — Dalga 1 Raporu (Token + Density + Pilot)

**Branch:** `faz3-tasarim`  
**Tarih:** 11 Temmuz 2026  
**Kaynak:** `docs/TASARIM_REHBERI.md`  
**Kapsam:** Frontend only — backend dokunulmadı.

---

## Amaç

1366×768’de rahat, kompakt UI. Token-merkezli yaklaşım: `theme.css` ölçek token’ları → ortak bileşenler → Personel Listesi pilot.

---

## 1. Token’lar (`packages/shared/src/styles/theme.css`)

### Tipografi (comfortable → compact)

| Token | Comfortable | Compact |
|-------|-------------|---------|
| `--fs-page-title` | 19px / 600 | 17px / 600 |
| `--fs-section` | 15px / 600 | 14px / 600 |
| `--fs-body` | 13.5px | 13px |
| `--fs-table` | 13px | 12.5px |
| `--fs-label` | 12px / 500 | 11.5px / 500 |
| `--fs-caption` | 11.5px | 11px |
| `--fs-badge` | 11px / 600 | 10.5px / 600 |

### Spacing

`--sp-1..8` (4–32px) + `--page-padding`, `--card-padding`, `--card-gap`, `--form-field-gap`, `--section-gap`

### Kontroller

`--control-height`, `--btn-height-md/sm`, `--checkbox-size`, `--icon-size`, `--icon-btn-size`, `--table-row-height`, `--table-header-height`, `--tab-height`, modal `--modal-sm/md/lg/xl`

### Renk

- Kimlik renkleri **korundu** (emerald/indigo/sky, dark varsayılan)
- Durum soft arka planlar **%12 opaklık**
- `--neutral` / `--neutral-soft` / `--neutral-text` eklendi

---

## 2. Density

- DOM: `document.documentElement` → `data-density="comfortable|compact"`
- CSS: `[data-density="compact"]` token override
- Redux: `themeSlice.density` + `setDensity` / `toggleDensity`
- Persist: tema ile aynı desen — `localStorage('density')` + `user.preferences.density` okuma
- Varsayılan: **comfortable**
- Kalıcı UI toggle: **yok** (Faz 4 Ayarlar Stüdyosu); altyapı hazır
- Company + SuperAdmin `App.tsx` density attribute senkronu

---

## 3. Bağlanan bileşenler

| Katman | Dosya | Ne değişti |
|--------|-------|------------|
| CSS | `components.css` | `.btn`, form controls, `.table` (+ sticky thead), `.badge`, `.modal*`, `.tabs` → token |
| CSS | `layout.css` | `.page-header` tek satır; `.list-filter-bar` şablonu |
| React | `company/.../DataTable.tsx` | Sticky table, token font/padding, `emptyAction`, satır seçim stili |
| React | `Modal.tsx` | Zaten CSS sınıfları; modal boyut token’ları CSS’te |

Hardcoded hover renkleri (`#dc2626` vb.) btn danger/success’te token + brightness’e çekildi.

---

## 4. Pilot ekran — Personel Listesi

**Dosya:** `apps/company/src/pages/employees/EmployeesPage.tsx`

Şablon (TASARIM_REHBERI §6 Liste):

1. Tek satır başlık: `Personel` + kayıt sayısı + aksiyonlar (Import/Export/Yeni)
2. Yatay filtre şeridi (arama + Filtreler paneli)
3. Ortak `DataTable` (sticky header, sayfalama footer)
4. Toplu işlem / import / confirm — işlev korundu

Özel inline `<table>` kaldırıldı → `DataTable`.

---

## 5. Doğrulama

| Kontrol | Sonuç |
|---------|--------|
| Backend | Dokunulmadı |
| 3 SPA lint + build | ✅ exit 0 |
| Density attribute | `setDensity` / initial load |
| Personel filtre/sayfalama/seçim | Korundu |

---

## 6. Sonraki dalga (beklemede — görsel onay sonrası)

- ContextSidebar 216px + daraltma
- Diğer yoğun ekranlar (izin, dashboard, kullanıcılar…)
- Hardcoded px avı yayılımı
- Portal Bootstrap ölçeği

**DUR:** Pilot görsel kontrol için kullanıcı onayı bekleniyor.

---

## Kritik Bug Teşhisi (11 Temmuz 2026 — düzeltme YOK)

**Branch:** `faz3-tasarim` · **Kapsam:** yalnızca kök neden + kanıt + öneri · **Düzeltme bu turda yapılmadı.**

### Bağlantı özeti

İki sorun **bağlantılı**. Company app her **30 sn** `checkAuth()` çağırıyor; bu `isLoading=true` yapıyor → `ProtectedRoute` / `ModuleProtectedRoute` tüm sayfayı **unmount** edip “Yükleniyor…” gösteriyor → `/auth/me` bitince sayfa **yeniden mount** → liste/API yeniden çekiliyor. Kullanıcı bunu “kendiliğinden refresh” olarak görüyor. `/auth/me` ~2 sn sürdüğü için her döngüde 2+ sn boş ekran + yeniden yükleme maliyeti birikiyor; yavaş API algısını da büyütüyor.

Faz 3 density effect’i (`data-density` setAttribute) **döngü üretmiyor** — yalnızca `density` state değişince çalışıyor.

---

### SORUN 1 — Sürekli refresh (~20–30 sn)

#### Kök neden (yüksek güven)

1. **`App.tsx` 30 sn `setInterval` → `dispatch(checkAuth())`**  
   ```157:166:frontend/apps/company/src/App.tsx
   // Periyodik olarak (30 saniyede bir) fresh data çek
   useEffect(() => {
     if (!authIsAuthenticated) return;
     const interval = setInterval(() => {
       dispatch(checkAuth());
     }, 30000);
   ```
2. **`checkAuth.pending` → `state.isLoading = true`** (`authSlice.ts`)
3. **`ProtectedRoute` `isLoading` iken tüm children’ı kaldırıp loading screen render ediyor**  
   ```114:120:frontend/apps/company/src/App.tsx
   if (isLoading) {
     return (
       <div className="loading-screen">
         ...
   ```
   Aynı pattern: `ModuleProtectedRoute.tsx`.
4. Sonuç: **tam sayfa unmount/remount** — F5 değil ama kullanıcı için “refresh”. Süre interval ile birebir (~30 sn).

#### Kanıt

| Kanıt | Detay |
|-------|--------|
| Zaman eşleşmesi | Kullanıcı “20–30 sn” ↔ kod `30000` ms |
| Network (beklenen) | Her ~30 sn tekrarlayan `GET /api/v1/auth/me` |
| `window.location.reload` otomatik yok | ErrorBoundary’de yalnızca butonla; density/effect’te yok |
| 401 hard redirect | `api.ts` interceptor: 401 → `window.location.href = '/login'` (oturum düşerse tam gezinme; asıl periyodik semptom bu değil) |
| Density | `setAttribute('data-density')` — state değişmeden tekrar tetiklenmez |

#### Önerilen çözüm (sonraki tur)

- Periyodik `checkAuth`’te **`isLoading` set etme** (ayrı sessiz thunk veya `checkAuth`’e `{ silent: true }`).
- Veya interval’i kaldır / çok uzat; focus’ta sessiz yenileme yeterli olabilir.
- `ProtectedRoute`: zaten authenticated iken `isLoading` ile unmount etme.

---

### SORUN 2 — API yavaş (login/list 5–11 sn algısı)

#### Ölçüm (Docker → `localhost:8000`, 8 personel, admin ~360 izin)

| İstek | HTTP süre | Not |
|-------|-----------|-----|
| `GET /auth/me` | **~2.2 s** | Response ~9.5 KB (permissions dizisi şişik) |
| `GET /employees?per_page=15` | **~0.75 s** | 8 kayıt |
| Login (yanlış şifre denemesi) | **~2.8 s** | bcrypt yine çalışır |
| CLI: list query sayısı | **3 query** | count + employees + users (eager) |
| CLI: `can()` 20×2 (warm) | **0 ekstra query / ~10 ms** | Spatie model cache OK |
| bcrypt tek başına | **~266 ms** | beklenen aralık |

#### Kök nedenler (öncelik sırası)

1. **Algılanan 5–11 sn çoğunlukla Sorun 1 ile birleşik:** her 30 sn unmount → `/me` (~2 s) + sayfa API’leri yeniden → üst üste biner. Tek başına list ~0.75 s; klasik N+1 (8 kayıt için 10+ query) **görülmedi**.
2. **`/auth/me` ve login `formatUser()` her seferinde `getAllPermissions()` → ~360 izin adı JSON’a gömülüyor** (`AuthController::formatUser`). Ağır payload + Spatie rol/izin join’leri her me/login’de.
3. **`company->fresh()` + `activeModules()`** her `formatUser` çağrısında ek sorgular.
4. **Gate::before** her hiyerarşik `can()` için permission listesini pluck eder; warm request’te DB N+1 değil ama CPU + her Resource satırında `canViewSalary` / `canViewTckn` tekrarları var (8 kayıt × 2 — ucuz ama gereksiz).
5. Ortam: Docker Desktop + Windows volume + `APP_DEBUG` — soğuk isteklerde ek gecikme; tek başına 5–11 sn’yi açıklamaz, birikimi büyütür.

#### N+1 / DataScope / Gate — net karar

| Hipotez | Sonuç |
|---------|--------|
| Employee list N+1 | **Çürütüldü** (3 query / 8 satır) |
| DataScope pahalı join | company_admin → company scope, ek join yok |
| Gate her can’da DB | Warm’da hayır; cold me’de permission join’ler var |
| EmployeeResource satır başı `can()` | Evet ama cache’li; asıl maliyet `/me` permission dump + refresh döngüsü |

#### Önerilen çözüm (sonraki tur)

1. Önce Sorun 1’i kes (en yüksek ROI).
2. `formatUser`: permissions’ı her me’de tam dump etme — özet/rol veya ayrı endpoint; Spatie permission cache ısındır.
3. Login sonrası permissions’ı bir kez yükle; polling’de yalnızca hafif profil.
4. İsteğe bağlı: Resource’ta salary/tckn flag’lerini request başına bir kez hesapla.

---

### Teşhis sonucu (karar)

| # | Kök neden net mi? | Düzeltmeye hazır mı? |
|---|-------------------|----------------------|
| 1 Refresh | **Evet** — 30s checkAuth + isLoading unmount | Evet |
| 2 Yavaşlık | **Evet (birleşik)** — /me ağır + döngü amplifikasyonu; list N+1 değil | Evet (1 önce) |

**Belirsizlik:** Kullanıcının “refresh”inin tarayıcı hard-reload mu yoksa SPA remount mu olduğu Network’te `Document` yenilenmesi ile teyit edilmeli; kod kanıtı remount’u kesin gösteriyor. Hard-reload yalnızca 401→`/login` yolunda.

---

## Kritik Bug Düzeltme (12 Temmuz 2026)

**Branch:** `faz3-tasarim` · Teşhis onaylandı → uygulandı.

### Frontend izin bağımlılığı (2a kontrol)

| Kullanım | Kaynak | Sonuç |
|----------|--------|--------|
| `usePermission` | `user.permissions` | **Bağımlı** — login + ilk `checkAuth` tam dump zorunlu |
| `ModuleProtectedRoute` / ModuleRail | `user.company.active_modules` | **Bağımlı** — aynı şekilde ilk yüklemede dolu olmalı |
| Periyodik / focus | Profil tazeleme yeterli | Light `/me` + FE merge ile authz korunur |

**Kırılmadı:** silent yanıtta boş `permissions` / `active_modules` → `mergeUserPreservingAuthz` önceki state’i tutar.

### Sorun 1 — uygulanan

| Madde | Karar / değişiklik |
|-------|-------------------|
| **1a** | `ProtectedRoute` / `ModuleProtectedRoute` / portal + superadmin: loading yalnızca `isLoading && !isAuthenticated` |
| **1b** | `checkAuth({ silent: true })` → pending/fulfilled `isLoading`’e dokunmaz; ağ hatasında oturum düşmez |
| **1c** | **5 dk interval + silent + focus’ta silent** (30 sn kaldırıldı). 401 interceptor hâlâ `/login`’e atar |

### Sorun 2 — uygulanan

| Madde | Değişiklik |
|-------|------------|
| **2a** | Silent → `GET /auth/me?light=1`; login + mount tam `/me` |
| **2b** | `formatUser($light)`: light’ta izin dump + `company->fresh()` + `activeModules()` yok |
| **2c** | Spatie cache zaten 24h (`config/permission.php`) — ekstra config gerekmedi |

### Doğrulama checklist

- [ ] 30 sn+ ekranda kal → kendiliğinden remount/yenileme yok (**manuel UI**)
- [x] Silent `/me?light=1` izin dump yok (`AuthMeLightTest`); tam `/me` permissions dolu
  - Not: Docker seed boşken HTTP süre ölçümü yapılamadı; light path `permissions=[]` + `fresh()`/`activeModules()` atlanıyor (teşhisteki ~2.2s dump’ın kaynağı).
- [ ] login → dashboard → gezinti: kesinti yok (**manuel UI**)
- [ ] `usePermission` / `ModuleProtectedRoute` çalışıyor (**manuel UI**)
- [x] 3 SPA lint+build exit 0
- [x] `AuthMeLightTest` 3 passed (Docker)
- [ ] CI yeşil (push sonrası)
