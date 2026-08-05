# ALATAX HR — Güncel Durum Raporu

**Üretim:** 31 Temmuz 2026 (DOK-3) · **Branch:** `faz4-form-engine`  
**Amaç:** Tek sayfa handoff. Detay faz raporlarında; tarihsel teşhisler `docs/arsiv/`.

---

## DOK-3 — Teşhis bulguları (ADIM 1)

| # | Bulgu | Sonuç | Devret |
|---|--------|--------|--------|
| **1.1** | `DemoSeeder` (Faz A6) ve `DemoDataSeeder` (QA-1 / `demo:seed`) ikisi de `admin@demo.test` üretiyor; ikisi de `slug=demo-firma` (“Demo Firma AŞ”). Sentinel (`admin@demo.test` @ `alatax_hr`) hangi seeder’ın yazdığına bakmaz — kullanıcı varlığını kontrol eder. Çift kaynak / drift riski. | 🔴 | **QA-4** |
| **1.2** | `DemoDataSeeder` → `RetentionPolicy` seed: **`active => true`** (yorum: QA dry-run). D2c kuralı (“seed politika asla otomatik aktif olmaz”) **ihlal**. | 🔴 | **QA-4** |
| **1.3** | Onaylı izin → puantaj: `LeaveRequest::onWorkflowCompleted` yalnızca status + bakiye; `AttendanceRecord` (`status=leave`) üretmez. Model/UI “izinli gün”i destekler ama **otomatik wire yok** (AKIS_SPEC §1/§2 borcu). | ⬜ açık | Faz 6 B3 |
| **1.4** | `AKIS_SEMA_EDITOR.html` **repoda var** (`docs/`). `SISTEM_ISLEYIS.md` linki geçerli — kaldırma yok. | ✅ | — |

---

## 0. Müşteri ve ölçek bağlamı

| Madde | Değer |
|-------|--------|
| İlk müşteri | **Dobedan Otel Grubu** |
| Kurulum | **ON-PREM** (aynı kod → cloud + on-prem paket satışı) |
| Ölçek | 3 şirket (→10) · 6 şube (→40) · ~3000 personel |
| Yönetim | Tek merkez İK; şirket verisi karışmaz; raporlama gruba yayılır |
| Bordro | Logo’da; bizim çıktı = puantaj aktarım paketi (API/dosya) |
| PDKS | Donanım yok → telefon QR; vardiya başı **500+ eşzamanlı** okutma |
| İlk sürüm | **14 modülün tamamı** (kısmi çıkış yok) |

→ Her tasarım kararı buna göre: `ROADMAP.md` §0.

---

## 1. Bugün (30 saniye)

| Katman | Durum |
|--------|--------|
| Test | **690 passed** (Tur5; Docker `alatax_hr_testing`) |
| Faz 0–3 | ✅ |
| Faz 4 | 🔶 Lookup/Workflow/Bildirim/Ayar; Form Engine 4A + W2 iade açık |
| Faz 5 | ✅ Rapor motoru (11 dataset) |
| FAZ A/B + KVKK D2a–c çekirdek | ✅ |
| Faz G | G1 ✅ (CompanyContext) · G2 ☐ grup rapor kapsamı |
| Faz 6 → 7 → 8 | ☐ |

---

## 2. Kurulan 6 motor + 4 registry

| Motor | Durum | Not |
|-------|--------|-----|
| Lookup | ✅ | Cascading borç |
| Form Engine | 🔶 | 4A tam geçiş açık |
| Workflow / onay | ✅ motor | İade (`returned`) = **W2 / Faz 6 başı** |
| Bildirim | ✅ 4C çekirdek | Push → Faz 8; `approval.returned` DUR (W2) |
| Rapor | ✅ Faz 5 | 11 dataset |
| Ayar (Settings Registry) | ✅ D4a | Pilot: izin + rapor |

| Registry | DoD |
|----------|-----|
| Dataset registry | Modül dataset’siz bitmez |
| PersonalDataCollector | Kişisel veri modülü collector’sız bitmez |
| Settings Registry | Ayar + ⚙ panel bağlanmadan bitmez |
| Approval entity registry | Onaylı entity kayıtlı + outcome hook |

**Yardım içeriği DoD (motor Faz 8):** Her modül dalgası `docs/help/{modul}/{sayfa}.md` yazar; D4b sonda render eder. (`.cursorrules` + ROADMAP)

---

## 3. Revize yol haritası (faz sırası)

| Sıra | Faz | Durum |
|------|-----|--------|
| — | Faz 0–5 (+A/B/KVKK çekirdek) | ✅ / 🔶4 |
| **G1** | **Faz G — Grup/Holding** (SEÇENEK 1) | ✅ (Tur2–Tur5) |
| **G2** | Grup rapor/pano kapsamı | ☐ |
| **6** | Modül derinleştirme (W2 iade başta → 14 modül) | ☐ |
| **7** | On-prem installer + imzalı lisans + yedekleme + izleme | ☐ canlıdan önce |
| **8** | Yardım motoru (D4b) + mobil/AI/entegrasyon ufku | ☐ en sonda |

**Holding kararı (park iptal):** SEÇENEK 1 onay (`organizations` + DataScope `group`). SEÇENEK 2 reddedildi.  
**Risk:** cross-company veri sızıntısı. **DoD:** mevcut DataScope/Policy yeşil + yeni grup izolasyon paketi.

---

## 4. Lisans / SuperAdmin / tema

- **Lisans:** Her modül à la carte (`modules` + `company_modules`); SuperAdmin’den aç/kapa. İSG/PDKS/LMS dahil.  
- **SuperAdmin:** Müşteriye görünmez (değişiklik yok). On-prem’de panel gizlemek koruma değildir — gerçek koruma **imzalı lisans + sözleşme** (Faz 7/8).  
- **Tema:** Company/SuperAdmin mevcut davranış; **Portal açık tema varsayılan.**

---

## 5. Açık borçlar (tek liste)

| Borç | Sahip |
|------|--------|
| Onaylı izin → puantaj wire | Faz 6 B3 |
| İade (`returned`) akışı | Faz 6 W2 |
| E-posta doğrulama (Mailtrap E2E) | Faz 0 kalıntı |
| Bundle &lt;1MB | FE borç |
| i18n EN + dil switcher | Backlog / Faz 8 |
| Görsel kontroller (QA kalanları; elle) | `QA_RAPOR.md` / Tur3 C0 |
| Form Engine 4A tam geçiş | Faz 4 |
| Saklama politikaları job’ları | KVKK |
| Cascading picklist | Faz 4 sonu |
| **L2** — Select ellipsis / title (UI) | FE borç |
| G2 — grup rapor/pano kapsamı | Faz G |

---

## 6. Belge haritası

Giriş: `BURADAN_BASLA.md`. Pusula: `ROADMAP.md`. QA: `QA_RAPOR.md` + `TEST_TURU.md`. Arşiv: `docs/arsiv/` (PROJECT_SNAPSHOT, AKIS_ENVANTERI, MENU_PUANTAJ_TESHIS, CURSOR_PROJE_ANALIZ_PROMPT).

**Git kuralı:** Yalnız `faz4-form-engine`. DB wipe yok.
