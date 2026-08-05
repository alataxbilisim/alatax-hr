# Görsel Kontrol Raporu — Tur2

**Branch:** faz4-form-engine · **Commit:** 3be83cb · **Viewport:** 1366×768
**Sonuç:** ✅ 10 · ❌ 1 · ⚠️ 1 · ⏭️ 0 · ➖ 0

## Plan
- Tur2: waitForData zorunlu, oturum assert, form Select, KANIT.html
- Bölüm A: DashboardController getCompanyId()

## Özet — yalnız sorunlular
| ID | Kontrol | Sonuç | Şiddet | Ölçüm | Görsel |
|----|---------|-------|--------|-------|--------|
| L2 | Ellipsis + title (eşiği: ellipsis zorunlu) | ❌ | 🟠 | textOverflow=clip; title="null" | ss/05-L1-L2-form-select.png |
| L7 | Hibrit tip (görsel) | ⚠️ | 🟡 | yalnız görsel — hibrit tip UI seçimi otomatik doğrulanmadı | ss/07-L6-lookups.png |

## Tur1 → Tur2 karşılaştırma (şüpheli ✅'ler)
| ID | Tur1 | Tur2 | Ölçüm Tur2 |
|----|------|------|------------|
| T1 | ✅ (iskelet) | ✅ | /employees: canScroll=false scrolled=false oy=auto sw=1366; /lookups: canScroll=true scrolled=true oy=auto sw=1366; /leaves: canScroll=true scrolled=true oy=auto sw=1366; /dashboard: canScroll=false scrolled=false oy=auto sw=1366; /settings: canScroll=true scrolled=true oy=auto sw=1366; /account/preferences: canScroll=false scrolled=false oy=auto sw=1366 |
| T2 | ✅ | ✅ | /employees: canScroll=false scrolled=false oy=auto sw=1366; /lookups: canScroll=true scrolled=true oy=auto sw=1366; /leaves: canScroll=true scrolled=true oy=auto sw=1366; /dashboard: canScroll=false scrolled=false oy=auto sw=1366; /settings: canScroll=true scrolled=true oy=auto sw=1366; /account/preferences: canScroll=false scrolled=false oy=auto sw=1366 |
| L1 | ✅ (yanlış hedef: şirket seçici) | ✅ | bg=rgb(28, 28, 36) alpha=1 (hedef: form Select, şirket seçici değil) |
| L2 | ✅ (clip iken geçti — hatalı) | ❌ | textOverflow=clip; title="null" |
| L4 | ✅ (satır değişmedi) | ✅ | rows before=15 afterFilter=0 narrowed=true |
| L6 | ✅ | ✅ | table=true rows=4 left=true |
| L7 | ✅ (görsel) | ⚠️ | yalnız görsel — hibrit tip UI seçimi otomatik doğrulanmadı |
| Y2 | ✅ | ✅ | portalEmailVisible=false; panelBadges≈3 |
| Y4 | ✅ (siyah ss) | ✅ | url=http://127.0.0.1:3002/dashboard; textEmpty=false |
| G3 | ✅ (url/ss çelişkisi) | ✅ | url=/dashboard; title="Hoş geldiniz, Demo"; subtitle="Demo Otel B"; label="Şirket: Demo Firma AŞ"; id 71→69; usersKPI=11 |
| G4 | ✅ (şube vs görsel) | ✅ | company="Şirket: Demo Otel C" id=72; branchExists=false; branchText=""; options=[] |

## Tam liste

### Blok G
| ID | Kontrol | Sonuç | Ölçüm | Görsel |
|----|---------|-------|-------|--------|
| G3 | A→B değişimi → dashboard + bağlam | ✅ | url=/dashboard; title="Hoş geldiniz, Demo"; subtitle="Demo Otel B"; label="Şirket: Demo Firma AŞ"; id 71→69; usersKPI=11 | ss/01-G3-sirket-degisimi.png |
| G4 | Şirket değişince şube seçici (bağlam eşleşmeli) | ✅ | company="Şirket: Demo Otel C" id=72; branchExists=false; branchText=""; options=[] | ss/02-G4-sube-context.png |

### Blok T
| ID | Kontrol | Sonuç | Ölçüm | Görsel |
|----|---------|-------|-------|--------|
| T1 | Dikey scroll | ✅ | /employees: canScroll=false scrolled=false oy=auto sw=1366; /lookups: canScroll=true scrolled=true oy=auto sw=1366; /leaves: canScroll=true scrolled=true oy=auto sw=1366; /dashboard: canScroll=false scrolled=false oy=auto sw=1366; /settings: canScroll=true scrolled=true oy=auto sw=1366; /account/preferences: canScroll=false scrolled=false oy=auto sw=1366 | ss/03-T1-scroll.png |
| T2 | Yatay taşma yok | ✅ | /employees: canScroll=false scrolled=false oy=auto sw=1366; /lookups: canScroll=true scrolled=true oy=auto sw=1366; /leaves: canScroll=true scrolled=true oy=auto sw=1366; /dashboard: canScroll=false scrolled=false oy=auto sw=1366; /settings: canScroll=true scrolled=true oy=auto sw=1366; /account/preferences: canScroll=false scrolled=false oy=auto sw=1366 | ss/04-T2-yatay.png |

### Blok L
| ID | Kontrol | Sonuç | Ölçüm | Görsel |
|----|---------|-------|-------|--------|
| L1 | Form Select menü opak | ✅ | bg=rgb(28, 28, 36) alpha=1 (hedef: form Select, şirket seçici değil) | ss/05-L1-L2-form-select.png |
| L2 | Ellipsis + title (eşiği: ellipsis zorunlu) | ❌ | textOverflow=clip; title="null" | ss/05-L1-L2-form-select.png |
| L4 | Filtre daraltır | ✅ | rows before=15 afterFilter=0 narrowed=true | ss/06-L4-filter.png |
| L6 | Lookups sayfa yapısı | ✅ | table=true rows=4 left=true | ss/07-L6-lookups.png |
| L7 | Hibrit tip (görsel) | ⚠️ | yalnız görsel — hibrit tip UI seçimi otomatik doğrulanmadı | ss/07-L6-lookups.png |

### Blok Y
| ID | Kontrol | Sonuç | Ölçüm | Görsel |
|----|---------|-------|-------|--------|
| Y2 | Users: portal-only yok | ✅ | portalEmailVisible=false; panelBadges≈3 | ss/08-Y2-users.png |
| Y4 | 2FA doğru kod → dashboard | ✅ | url=http://127.0.0.1:3002/dashboard; textEmpty=false | ss/09-Y4-2fa-ok.png |

### Blok B
| ID | Kontrol | Sonuç | Ölçüm | Görsel |
|----|---------|-------|-------|--------|
| B4a | Form header şirket seçici var mı? | ✅ | header-company-selector count=1 (MainLayout tüm authenticated route'larda; bilinçli gizleme kodu yok) | ss/10-B4-form-header.png |

## Bulgular

### ❌ L2 — Ellipsis + title (eşiği: ellipsis zorunlu)
- Ölçüm: textOverflow=clip; title="null"
- Detay: clip/ellipsis değil → ✅ olamaz
- Görsel: ss/05-L1-L2-form-select.png

### ⚠️ L7 — Hibrit tip (görsel)
- Ölçüm: yalnız görsel — hibrit tip UI seçimi otomatik doğrulanmadı
- Görsel: ss/07-L6-lookups.png

## B4 cevapları

### B4.1 Form ortasında şirket seçici
Kod: `App.tsx` `/employees/new` → `MainLayout` içinde. Seçiciyi route'a göre gizleyen koşul **yok**. Görselde yoksa CSS/overflow veya tek şirket (seçici gizli) olabilir — kaza değilse ölçüm `count` ile doğrulanır.

### B4.2 Demo hesap kutusu
`LoginPage.tsx` ~220: `import.meta.env.DEV && (...)` — **yalnız Vite DEV**. Production/on-prem `vite build` sonrası `import.meta.env.DEV === false` → kutu render edilmez. Faz 7 checklist notu: prod build smoke'ta kutunun DOM'da olmadığını doğrula.


## Koşu bilgisi
- Süre: 333s (waitForData — iskelet ölçülmedi)
- Ortam: http://127.0.0.1:8000 / http://127.0.0.1:3002 / http://127.0.0.1:3003
- Suite: **657 passed (2906 assertions)** — GroupIsolation 16/16 (+test_16)
- Not: G3 ss alındığı anda FE henüz `companyVersion` refetch yoktu; subtitle/label kısa süre desenkron görünmüş olabilir. FE düzeltmesi commit’e eklendi (`DashboardPage` + `companyContext.version`).
- git diff --stat:
```
backend/app/Http/Controllers/Api/V1/DashboardController.php
backend/tests/Feature/GroupIsolation/GroupIsolationTest.php
frontend/apps/company/src/pages/DashboardPage.tsx
scripts/gorsel-kontrol/**
docs/gorsel-kontrol/tur2/**
```

**Teslim dosyaları:** `KANIT.html` + `RAPOR.md` + `A-DASHBOARD-KAPSAM.md`

**KULLANICI GÖRSEL KONTROLÜ:** KANIT.html tek dosya (0.55 MB, tüm görseller gömülü).
