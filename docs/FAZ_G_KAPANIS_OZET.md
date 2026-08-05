# FAZ G — Kapanış Özeti (Tur2–8 + G1/G2)

**Tarih:** 6 Ağustos 2026 · **Branch:** `faz4-form-engine`  
**Suite:** **758 passed** (Docker `alatax-hr-app`)  
**Detay arşiv:** `docs/arsiv/2026-08/`

---

## Tek cümle

Holding hibrit modeli (SEÇENEK 1) operasyonel tek-şirket bağlamı + isteğe bağlı grup rapor/pano kapsamı olarak kapandı; Tur2–8 bağlam sızıntıları, demo org hizası, personel kimliği (`full_name`), panel erişimi, pozisyon SSOT (`position_id`) ve sistem panosu salt okunurluğu testli yeşil.

---

## Faz G dalgaları

| Dalga | Kapsam | Durum | Kaynak |
|-------|--------|-------|--------|
| **G1** | `organizations`, `company_user`, `CompanyContext`, navbar şirket seçici, BelongsToCompany kaynağı | ✅ | `arsiv/2026-08/FAZ_G_RAPOR.md` |
| **G2** | `scope=group` = org ∩ membership + `reports.scope.group`; KVKK/CRUD hariç; DataScope Group kaldırıldı | ✅ | `arsiv/2026-08/FAZ_G2_{RAPOR,KARAR,KAPANIS}.md` |

**Kilit kararlar:** Grup kümesi organization'daki tüm şirketler değil — yalnız kullanıcının üye olduğu şirketler ∩ aktif şirketin org'u. KVKK yolları group kullanmaz. Demo holding: 69/71/72 tek `org-demo-holding` (`demo:align-organizations`).

---

## Tur serisi (G1/G2 borç kapatma)

| Tur | Odak | Sonuç |
|-----|------|--------|
| **Tur2** | Dashboard KPI home≠aktif bağlam; G3 şirket değişimi | CompanyContext Dashboard/KPI |
| **Tur3** | G3 doğrulama; bağlam taraması | Görsel + BE regresyon |
| **Tur4** | Settings/P3–P4 aktif bağlam; dataset constrain | SettingsRegistry + 11 dataset izolasyon |
| **Tur5** | Portal PDKS güvenlik; SettingsWriter; kalıcı bağlam kuralları | `SISTEM_ISLEYIS.md` §AŞAMA 1 |
| **Tur6** | G2 öncesi son kontroller | G2'ye hazırlık |
| **Tur7** | `full_name` SSOT; panel erişimi; Select UI | Elle kontrol düzeltmeleri |
| **Tur8** | `full_name` backfill; `roles.panel_access`; pozisyon code→`position_id` SSOT; sistem panosu readonly | Kapanış turu |

Görsel kontrol raporları: `docs/arsiv/2026-08/gorsel-kontrol/tur2` … `tur6`.

---

## Pozisyon SSOT (Tur8 sonrası)

| Aşama | Ne oldu |
|-------|---------|
| Tur8 | FE Select `code` yazar; Resource `position_label` katalogdan çözümler |
| Faz 6 öncesi | `position_id` FK + backfill (`2026_08_05_220000`) |
| Temel sağlamlaştırma §4 | String kolon drop; SSOT = `employees.position_id` (`2026_08_06_010000`) |

Detay: `FAZ6_ONCESI_KAPATMA.md` §3 · eski teşhis: `arsiv/2026-08/TUR8_KAPANIS.md` §3.

---

## Sıradaki faz

**Faz 6** — Modül derinleştirme (W2 iade → 14 modül). Faz G tamamlandı; handoff: `GUNCEL_DURUM_RAPORU.md`.
