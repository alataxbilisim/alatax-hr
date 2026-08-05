# Tur3 A — G3 FE doğrulama

**Commit:** 82097fe+ · **FE test altyapısı:** yok (`frontend/**/*.test|spec` = 0) — E2E ile yetinildi.

## SQL KPI beklenen
`69→51, 70→3, 71→11, 72→10`

## İki yönlü ölçüm

| Yön | Header label | Selector | Subtitle | Firma kartı | KPI | Beklenen KPI | Sonuç |
|-----|--------------|----------|----------|-------------|-----|--------------|--------|
| 69→71 dört alan + KPI | Şirket: Demo Otel B | Demo Otel B | Demo Otel B | Demo Otel B | 11 | 11 | ✅ |
| 71→69 dört alan + KPI | Şirket: Demo Firma AŞ | Demo Firma AŞ | Demo Firma AŞ | Demo Firma AŞ | 51 | 51 | ✅ |

## Ekran görüntüleri
- ss/01-G3-69-to-71.png
- ss/02-G3-71-to-69.png

## FE test durumu
FE unit/integration test dosyası bulunamadı. companyContext.version → loadDashboard bağımlılığı kodda mevcut (`DashboardPage.tsx` ~60–102); doğrulama bu E2E ile yapıldı.

## Karar
**A GEÇTİ — B bölümüne geçilebilir**
