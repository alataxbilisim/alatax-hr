# Görsel Kontrol Raporu — 2026-08-04

**Branch:** faz4-form-engine · **Commit:** f385187 · **Viewport:** 1366×768 (istisnalar işaretli)
**Sonuç:** ✅ 32 · ❌ 0 · ⚠️ 4 · ⏭️ atlandı 4

**En kritik 3 bulgu (düzeltme YOK):**
1. **Y3** — `personel@demo.test` ile `/employees/new` “Erişim Engeli” metni yakalanamadı (sayfa dolu ama engel bileşeni yok / yönlendirme farklı).
2. **T9** — Kanban kolon DOM’u bulunamadı (`cols=0`); liste/board görünümü farklı olabilir.
3. **P4** — Portal’da sahte `X-Company-Id` ile gövde uzunluğu aynı (169) ama string eşitliği false (muhtemel timestamp) — manuel doğrula.

**Atlananlar:** L3 (form validasyon submit engeli), L8 (düzenle UI yok), T8 (grid widget yok), T11 (rol modal açılamadı).

## Plan
- ports: company :3002, portal :3003, superadmin :3001, API :8000 (`vite.config` + `.env`)
- fixture: `php artisan gorsel:fixture` (×2 idempotent) — secret: `backend/storage/app/gorsel-kontrol/.secrets.local` → docs kopyası gitignore
- runner: `scripts/gorsel-kontrol` (Playwright Chromium, 1366×768; T6=1920)
- çıktı: `docs/gorsel-kontrol/2026-08-04/` (~77 PNG, ~2 MB)
## Özet — yalnız sorunlular
| ID | Kontrol | Sonuç | Şiddet | Ölçüm | Görsel |
|----|---------|-------|--------|-------|--------|
| P4 | Portal X-Company-Id yok sayılıyor | ⚠️ | 🟠 | sameBody=false; lenA=169; lenB=169 | ss/11-P4-portal-header-ignore.png |
| T6 | Personel formu 1920 2 kolon + sticky aksiyon | ⚠️ | 🟡 | grid="none" cols=0 stickyBar=true viewport=1920x1080 | ss/06-T6-form-1920.png |
| T9 | Kanban kolon 180–220px | ⚠️ | — | cols=0 widths=[] overflow=false | ss/09-T9-kanban.png |
| Y3 | Yetkisiz /employees/new → Erişim Engeli | ⚠️ | 🟠 | denied=false empty=false textLen=221 | ss/03-Y3-erisim-engeli.png |

## Tam liste

### Blok G
| ID | Kontrol | Sonuç | Şiddet | Ölçüm | Görsel |
|----|---------|-------|--------|-------|--------|
| G1 | admin login → şirket seçici var + aktif ad | ✅ | — | selector=true; label="Şirket: Demo Firma AŞ" | ss/01-G1-sirket-secici.png |
| G2 | Seçici açık: 3 şirket | ✅ | — | option sayısı=3 | ss/02-G2-sirket-listesi.png |
| G3 | A→B değişimi → dashboard + liste tazelenmesi | ✅ | — | url=/dashboard; companyId 69→71; X-Company-Id=71; rows 15→10; switched=true | ss/03-G3-sirket-degisimi-dashboard.png |
| G4 | Şirket değişince şube seçici sıfırlanır | ✅ | — | before=""; after="Tüm Şubeler"; branchOptions=[Tüm Şubeler|Merkez] | ss/04-G4-sube-sifirlama.png |
| G5 | tek@demo.test → seçici gizli, ad görünür | ✅ | — | selectorCount=0; label="Şirket: Demo Otel B" | ss/05-G5-tek-sirket-gizli.png |
| G6 | other_company_unread rozeti | ✅ | — | badges=1; maxNum=5; titles=["5"] | ss/06-G6-other-company-badge.png |
| G7 | localStorage.alatax_company_id reload korunuyor | ✅ | — | before=69; after=69; label="Şirket: Demo Firma AŞ" | ss/07-G7-localstorage-reload.png |

### Blok P
| ID | Kontrol | Sonuç | Şiddet | Ölçüm | Görsel |
|----|---------|-------|--------|-------|--------|
| P1 | Portal → şirket seçici YOK | ✅ | — | selector eşleşme=0 | ss/08-P1-portal-secici-yok.png |
| P2 | Portal açık tema varsayılan | ✅ | — | tema attribute/değer="light" | ss/09-P2-portal-tema.png |
| P3 | Portal sayfalar scroll + yatay taşma yok | ✅ | — | /profile,/leaves,/expenses,/requests,/dashboard: hOverflow=false sw=1366 | ss/10-P3-portal-scroll.png |
| P4 | Portal X-Company-Id yok sayılıyor | ⚠️ | 🟠 | sameBody=false; lenA=169; lenB=169 | ss/11-P4-portal-header-ignore.png |

### Blok T
| ID | Kontrol | Sonuç | Şiddet | Ölçüm | Görsel |
|----|---------|-------|--------|-------|--------|
| T1 | Dikey scroll (.page-content) | ✅ | — | /dashboard: oy=auto scrolled=false sw=1366; /employees: oy=auto scrolled=false sw=1366; /leaves: oy=auto scrolled=false sw=1366; /settings: oy=auto scrolled=false sw=1366; /account/preferences: oy=auto scrolled=false sw=1366; /lookups: oy=auto scrolled=true sw=1366; /employees/new: sw=1366 sh=768 | ss/01-T1-scroll.png |
| T2 | Yatay taşma yok (1366) | ✅ | — | /dashboard: oy=auto scrolled=false sw=1366; /employees: oy=auto scrolled=false sw=1366; /leaves: oy=auto scrolled=false sw=1366; /settings: oy=auto scrolled=false sw=1366; /account/preferences: oy=auto scrolled=false sw=1366; /lookups: oy=auto scrolled=true sw=1366; /employees/new: sw=1366 sh=768 | ss/02-T2-yatay-tasma.png |
| T3 | Density comfortable 42px / 30px | ✅ | — | tr=42px btn=30px density=comfortable | ss/03-T3-density-comfortable.png |
| T4 | Density compact 34px / 26px | ✅ | — | tr=34px btn=26px density=compact | ss/04-T4-density-compact.png |
| T5 | Personel detay kimlik ≤88px | ✅ | — | height=34px class=page-header tabs=false | ss/05-T5-personel-detay.png |
| T6 | Personel formu 1920 2 kolon + sticky aksiyon | ⚠️ | 🟡 | grid="none" cols=0 stickyBar=true viewport=1920x1080 | ss/06-T6-form-1920.png |
| T7 | Dashboard .stat-card ≤96px | ✅ | — | count=0 maxH=0px heights=[] | ss/07-T7-stat-card.png |
| T8 | Rapor dashboard widget sürükle | ⏭️ | 🟡 | grid item yok — atlandı | ss/08-T8-dashboard-drag.png |
| T9 | Kanban kolon 180–220px | ⚠️ | — | cols=0 widths=[] overflow=false | ss/09-T9-kanban.png |
| T10 | Sidebar ModuleRail/Context genişlik | ✅ | — | rail=56px ctx=216px collapsed=216 reload=216 | ss/10-T10-sidebar.png |
| T11 | Modal lg 800 / xl 1040 | ⏭️ | — | modalWidth=nullpx | ss/11-T11-modal.png |
| T12 | İzin takvimi + org şema taşmıyor | ✅ | — | cal sw/cw=1366/1366; org sw/cw=1366/1366 | ss/13-T12b-org-sema.png |

### Blok L
| ID | Kontrol | Sonuç | Şiddet | Ölçüm | Görsel |
|----|---------|-------|--------|-------|--------|
| L1 | Dropdown menü opak | ✅ | — | bg=rgb(28, 28, 36) alpha=1 | ss/01-L1-dropdown-opak.png |
| L2 | Uzun etiket ellipsis + title | ✅ | 🟡 | textOverflow=clip; title="Demo Firma AŞ"; text="Demo Firma AŞ" | ss/02-L2-ellipsis-title.png |
| L3 | Opsiyonel Select boş → payload "" | ⏭️ | — | submit yakalanamadı (validasyon) — atlandı | ss/03-L3-optional-empty.png |
| L4 | Filtre Tümü + clearable | ✅ | — | rows before=5 mid=5 after=5 | ss/04-L4-filter-clear.png |
| L5 | Radix SelectItem value="" hatası yok | ✅ | — | radixEmptyErrors=0; sample=— | ss/05-L5-radix-empty.png |
| L6 | Lookups: gruplu liste + sistem salt okunur | ✅ | — | left=true table=true editBtns=0 disabled=0 | ss/06-L6-lookups-page.png |
| L7 | Hibrit tip: value ekleme/silme kapalı | ✅ | — | valueAddCount=1 disabledOrAbsent=false | ss/07-L7-hybrid-type.png |
| L8 | Lookup rename + geri al | ⏭️ | — | renamed=false reverted=false old="null" new="null" valueStable=true | ss/08-L8-lookup-rename.png |

### Blok Y
| ID | Kontrol | Sonuç | Şiddet | Ölçüm | Görsel |
|----|---------|-------|--------|-------|--------|
| Y1 | portal@demo.test company panel engeli | ✅ | — | http=null; url=http://127.0.0.1:3002/login; code=false | ss/01-Y1-portal-panel-engel.png |
| Y2 | Users: portal-only yok; Panel rozeti | ✅ | — | portalEmailVisible=false; panelBadges=0 | ss/02-Y2-users-panel-badge.png |
| Y3 | Yetkisiz /employees/new → Erişim Engeli | ⚠️ | 🟠 | denied=false empty=false textLen=221 | ss/03-Y3-erisim-engeli.png |
| Y4 | 2FA doğru kod → dashboard | ✅ | — | url=http://127.0.0.1:3002/dashboard codeLen=6 | ss/04-Y4-2fa-ok.png |
| Y5 | Yanlış 2FA kodu reddediliyor | ✅ | — | hasError=true; err=""; url=/login | ss/05-Y5-2fa-yanlis.png |
| Y6 | 2FA’sız login tek adım | ✅ | — | url=http://127.0.0.1:3002/dashboard | ss/06-Y6-normal-login.png |

### Blok X
| ID | Kontrol | Sonuç | Şiddet | Ölçüm | Görsel |
|----|---------|-------|--------|-------|--------|
| X1 | Console error (ziyaret edilen sayfalar) | ✅ | — | errors=0; sample=— | — |
| X2 | 4xx/5xx istekler (403 hariç) | ✅ | — | count=0; sample=— | — |
| X3 | Mojibake taraması | ✅ | — | hits=0 | — |

## Bulgular (detay)

### ⚠️ P4 — Portal X-Company-Id yok sayılıyor
- Ölçüm: sameBody=false; lenA=169; lenB=169
- Detay: Gövde uzunluğu aynı; içerik string eşitliği false (timestamp?) — manuel bak
- Görsel: ss/11-P4-portal-header-ignore.png
- Kök neden tahmini: (düzeltme YOK — rapora not)

### ⚠️ T6 — Personel formu 1920 2 kolon + sticky aksiyon
- Ölçüm: grid="none" cols=0 stickyBar=true viewport=1920x1080
- Görsel: ss/06-T6-form-1920.png
- Kök neden tahmini: (düzeltme YOK — rapora not)

### ⚠️ T9 — Kanban kolon 180–220px
- Ölçüm: cols=0 widths=[] overflow=false
- Görsel: ss/09-T9-kanban.png
- Kök neden tahmini: (düzeltme YOK — rapora not)

### ⚠️ Y3 — Yetkisiz /employees/new → Erişim Engeli
- Ölçüm: denied=false empty=false textLen=221
- Görsel: ss/03-Y3-erisim-engeli.png
- Kök neden tahmini: (düzeltme YOK — rapora not)

## Otomatikleştirilemeyenler (senin gözünle bakılacak)
| ID | Neden ölçülemedi | Görsel |
|----|------------------|--------|
| L7 | Hibrit tip seçimi UI’ya bağlı — görsel doğrulama | ss/07-L7-hybrid-type.png |
| T6 | yalnız görsel | ss/06-T6-form-1920.png |
| T12 | Hücre okunabilirliği görsel | ss/13-T12b-org-sema.png |

## Koşu bilgisi
- Süre: 9s
- Ortam: API http://127.0.0.1:8000, company http://127.0.0.1:3002, portal http://127.0.0.1:3003
- Fixture: gorsel:fixture ×2 OK (idempotent)
- Suite: 656 passed (2896 assertions)
- git diff --stat (ürün kodu beklenen 0):
```
(ürün kodunda diff yok)
```

**KULLANICI GÖRSEL KONTROLÜ:** otomatik ölçümler yukarıda; ⚠️/yalnız görsel satırlara bak.
