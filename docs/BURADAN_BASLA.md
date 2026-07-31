# ALATAX HR — BURADAN BAŞLA

Projeye yeni bakan biri için **10 dakikalık** giriş. Detay ve kararlar: `GUNCEL_DURUM_RAPORU.md` + `ROADMAP.md`.

**Tarih:** 31 Temmuz 2026 · **Branch:** `faz4-form-engine` · **Kod yoksa dokunma:** yalnızca `docs/` / görev dalgası.

---

## 1. Bugün neredeyiz?

| Alan | Durum |
|------|--------|
| Test suite | **633 passed** (QA-3; `alatax_hr_testing`) |
| Faz 0–3 | ✅ Kapalı (stabilizasyon, pgsql, RBAC+audit, tasarım sistemi) |
| Faz 4 | 🔶 Platform motorları (Lookup/Form/Workflow/Bildirim/Ayar — kısmen; Form Engine 4A + iade W2 açık) |
| Faz 5 | ✅ Rapor & analitik motoru kapalı |
| FAZ A / B / KVKK (D2a–c) | ✅ Çekirdek kapalı (saklama job / görsel borçlar açık) |
| Faz G (Grup/Holding) | ☐ **Sıradaki mimari faz** — Faz 6 modüllerinden **önce** |
| Faz 6 → 7 → 8 | ☐ Modüller → on-prem/lisans → yardım motoru + ufuk |

**Kritik kural:** Yalnız `faz4-form-engine` üzerinde çalış. `alatax_hr` DB silinmez; testler `alatax_hr_testing`.

---

## 2. Müşteri ve ölçek (tasarımın pusulası)

İlk müşteri: **Dobedan Otel Grubu** · kurulum **ON-PREM** · aynı kod tabanı ileride cloud + on-prem paket.

- Ölçek: 3 şirket (→10) · 6 şube (→40) · ~3000 personel  
- Yönetim: **tek merkez İK** — veri karışmaz, raporlama gruba yayılır  
- Bordro: Logo’da kalır → çıktımız **puantaj aktarım paketi**  
- PDKS: donanım yok → telefonda QR; vardiya başı **500+ eşzamanlı** okutma  
- İlk sürüm: **14 modülün tamamı** (kısmi çıkış yok)

Her mimari karar buna göre verilir → ayrıntı: `ROADMAP.md` §0.

---

## 3. Belge haritası (arşiv hariç)

| Belge | Ne için |
|-------|---------|
| **GUNCEL_DURUM_RAPORU.md** | Tek sayfa handoff — önce bunu oku |
| **ROADMAP.md** | Faz sırası + DoD + lisans + müşteri bağlamı |
| **SISTEM_ISLEYIS.md** | Hedef yaşam döngüsü (+ [AKIS_SEMA_EDITOR.html](AKIS_SEMA_EDITOR.html)) |
| **MODUL_SPEC.md** / **AKIS_SPEC.md** | Modül kapsamı / talep→onay durum makinesi |
| **TASARIM_REHBERI.md** | Token, density, Portal Liquid Glass |
| **QA_RAPOR.md** | Otomatik + tarayıcı QA turları; açık görsel borç listesi |
| **TEST_TURU.md** | Manuel smoke checklist |
| **FAZ\*_RAPOR.md**, **KVKK_RAPOR.md**, **PDKS_RAPOR.md**, … | Faz/dalga kapanış notları |
| **AYAR_MOTORU.md**, **I18N.md**, **DEPLOY_UBUNTU.md** | Motor / i18n / deploy |
| **arsiv/** | Tarihsel teşhisler — güncel değil |

Kök: `.cursorrules` (sürekli kurallar + DoD’ler).

---

## 4. Çalışma ritmi

1. Faz/dalga başı: ROADMAP maddesini tek prompt’a çevir (tek DoD, yıkıcı DB yok).  
2. Geliştirme → test (`alatax_hr_testing`) → commit.  
3. Modül dalgası bitmeden: Settings Registry + PersonalDataCollector + `docs/help/{modul}/{sayfa}.md` içerik.  
4. Yeni fikir → ROADMAP Backlog; sırayı bozma.

---

## 5. Sıradaki dalga (özet sıra)

| # | Faz | Not |
|---|-----|-----|
| 1 | **Faz G — Grup/Holding** | SEÇENEK 1 onaylı; DataScope `group`; sızıntı riski DoD |
| 2 | **Faz 6** | W2 iade akışı **başta**; sonra 14 modül derinleştirme |
| 3 | **Faz 7** | On-prem installer + imzalı lisans + yedekleme + izleme (canlıdan önce) |
| 4 | **Faz 8** | Yardım motoru (D4b) + mobil/AI/entegrasyon ufku |

QA düzeltme: **QA-4** (DemoSeeder çakışması, retention `active`, …) — bkz. `GUNCEL_DURUM_RAPORU.md` / `QA_RAPOR.md`.

---

## 6. Tema (tek cümle tutarlılık)

Company / SuperAdmin: mevcut davranış (kayıt yoksa koyu tercihe yakın). **Portal: açık tema varsayılan.**
