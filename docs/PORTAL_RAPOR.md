# PORTAL_RAPOR

## PORTAL-1 — Mobil-öncelikli kabuk + ana ekran

**Branch:** `faz4-form-engine`  
**Tarih:** 2026-07-16  
**Kapsam:** Token + UI kit + AppShell + Dashboard yeniden tasarım (Bootstrap kaldırılmadı)

### Bileşen listesi (`apps/portal/src/components/ui`)

| Bileşen | Rol |
|---------|-----|
| AppButton | primary / ghost / danger, lg dokunma |
| AppCard | Kart kabuk |
| AppInput | Token'lı input |
| AppSelect | Radix Select mobil sarmalayıcı |
| BottomNav | 5 yuva + ortada taşan QR |
| PageHeader | Geri + başlık |
| BottomSheet | Mobil “Diğer” sheet |
| StatusBadge | Durum rozeti |
| SkeletonLoader | Yükleme iskeleti |
| EmptyState | Boş durum |

Shell: `PortalLayout` (AppShell) + `DesktopRail` (≥1024px)

### Ekran özeti

1. **Kabuk:** Mobil alt nav (Ana · İzinler · QR · Talepler · Profil); masaüstü sol rail + içerik max ~720px ortalı.
2. **QR:** `/timesheet/qr` (PDKS-1 sayfası korunur).
3. **Diğer:** Profil + masaüstü sheet — Bordro, Ücret, Belge, Eğitim, Performans, Anket, Masraf, Duyuru, Puantaj.
4. **Dashboard:** Selamlama + vardiya · Bugün kartı + QR CTA · 2×2 hızlı işlem · duyurular · bekleyen talepler.
5. **Tema:** Açık varsayılan (kayıt yoksa); geçiş Profil’den (`themeSlice` / localStorage).

### Kullanılan API’ler (yeni uç yok)

| Widget | API |
|--------|-----|
| Özet / bakiye / duyuru / bekleyen | `GET /portal/dashboard` |
| Bugün puantaj | `GET /portal/timesheet/today` |
| Bugün vardiya | `GET /portal/timesheet/shifts` |

### Doğrulama

| Kontrol | Sonuç |
|---------|--------|
| portal `tsc` | **0** |
| portal lint | **0 error** (RequestsPage hooks warning — önceden vardı) |
| Select sentinel | **PASSED** |
| Suite | BE değişmedi — 457 hedef (regresyon yok) |
| DB wipe | **yok** |
| PUSH | **yok** |
| Bootstrap | **kaldırılmadı** (PORTAL-3) |

### Notlar

- Company / SuperAdmin’e dokunulmadı.
- Token katmanı: `apps/portal/src/styles/portal-theme.css`

---

**KULLANICI BEĞENİ KONTROLÜ BEKLİYOR**
