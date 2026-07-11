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
