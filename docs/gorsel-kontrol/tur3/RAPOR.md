# Görsel Kontrol Raporu — Tur3

**Branch:** faz4-form-engine · **Commit:** 82097fe · **Viewport:** 1366×768
**Sonuç:** ✅ 4 · ❌ 3 · ⚠️ 1 · ⏭️ 4 · ➖ 1

## Tur2 → Tur3
| ID | Tur2 | Tur3 | Not |
|----|------|------|-----|
| G3 | ✅ (çelişkili) | A-G3-DOGRULAMA ✅ | FE refetch doğrulandı |
| L2 | ❌ clip/title | ölçüm (düzeltme yok) | Select.tsx:134 + components.css:1077 |
| L4 | ✅ afterFilter=0 | before→filter→clear | C2 sıkı ölçüm |
| C0 | — | ➖ + çelişki fail | finalizeResult |

## C1 / C2 ölçümler
| ID | Kontrol | Sonuç | Ölçüm | Görsel |
|----|---------|-------|-------|--------|
| C0a | C0 scroll: kısa içerik → ➖ | ➖ | scrollHeight=768 clientHeight=768 canScroll=false; canScroll=false → ➖ (içerik viewport'tan kısa) | ss/01-C0-scroll-na.png |
| Y3 | Yetkisiz /employees/new → Erişim Engeli | ❌ | sessionOk=false; denied=false; empty=false; form=true; textLen=221; url=/login | ss/02-Y3-erisim-engeli.png |
| T9 | Kanban kolon genişlik | ⚠️ | cols=0 widths=[] overflow=false | ss/03-T9-kanban.png |
| P4 | Portal X-Company-Id yok sayılıyor | ✅ | token=true; sameBody=true; statusA=200 statusB=200; lenA=169 lenB=169 | ss/04-P4-portal-header.png |
| T6 | Personel formu 1920 iki kolon | ✅ | grid="none" cols=0 twoColLayout=true fields=9 viewport=1920x1080 | ss/05-T6-form-1920.png |
| L3 | Opsiyonel Select boş → payload | ⏭️ | submit yakalanamadı; emptyOk=null | ss/06-L3-optional-empty.png |
| L8 | Lookup rename + geri al | ⏭️ | renamed=false reverted=false old="active	Aktif		10	Aktif	Firma	" mid="Aktif (GK)" final="" | ss/07-L8-lookup-rename.png |
| T8 | Rapor dashboard widget sürükle | ⏭️ | grid item yok | ss/08-T8-dashboard-drag.png |
| T11 | Modal lg/xl genişlik | ⏭️ | modalWidth=nullpx | ss/09-T11-modal.png |
| L4 | Filtre daralt + clear geri dönüş | ❌ | before=10 afterFilter=10 afterClear=10 filter="" narrowed=false restored=true | ss/10-L4-filter-clear.png |
| L2 | L2 Select trigger tespiti (düzeltme yok) | ❌ | trigger.textOverflow=clip; valueText.textOverflow=ellipsis; title=null; text="Seçiniz..."; loc=@shared/components/Select.tsx:134 title + components.css:1077-1084 .ax-select-value-text | ss/11-L2-select-locate.png |

## C0 kuralları
- `detectContradiction` + `finalizeResult` → `lib/harness.mjs`
- Ölçümde >1 şirket adı veya exclusive KPI/id → status zorla `fail`
- `canScroll=false` scroll kontrolü → `na` (➖)
- C0a bu koşuda ➖ kullanımını kanıtlar

## L2 ürün borcu (düzeltme yok)
Faz3 borcu: uzun etiket kesilsin + title. CSS ellipsis .ax-select-value-text üzerinde tanımlı; Tur2 yanlış hedef (#header-company-selector/combobox root clip) ölçmüş olabilir. Bu turda ürün kodu değiştirilmedi.
Ölçüm: trigger.textOverflow=clip; valueText.textOverflow=ellipsis; title=null; text="Seçiniz..."; loc=@shared/components/Select.tsx:134 title + components.css:1077-1084 .ax-select-value-text

## Suite / diff
```
Tests: 668 passed (2950 assertions)  · docker exec alatax-hr-app php artisan test
(Tur2: 657 + Tur3 B: 11 yeni = 668)
```

Y3 notu: `personel@demo.test` company panel oturumu kurulamadı (`url=/login`) — ürün bulgusu değil, test hesabı/panel erişimi (Tur1 ile aynı sınıf).

Süre: 226.2s

**Teslimat:** KANIT.html · RAPOR.md · A-G3-DOGRULAMA.md · B-BAGLAM-TARAMASI.md
