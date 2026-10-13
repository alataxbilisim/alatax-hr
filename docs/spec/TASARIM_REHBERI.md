# ALATAX HR — TASARIM REHBERİ (Faz 3 Şartnamesi)

**Amaç:** "Ekranlar ve sayfalar büyük duruyor" sorununu somut değerlerle çözmek. Hedef cihaz: **13" laptop, 1366×768** — tüm ana akışlar bu çözünürlükte yatay scroll'suz ve rahat kullanılmalı. Bu dosya Faz 3'te Cursor'a verilecek şartnamedir; tüm değerler `packages/shared/src/styles/theme.css` token'larına yazılır, hiçbir bileşende hardcode edilmez.

---

## 1. Yoğunluk (Density) Sistemi

İki mod: `data-density="comfortable"` (varsayılan) ve `data-density="compact"`. Kullanıcı tercihi olarak `preferences`'a kaydedilir, themeSlice yönetir. Aşağıdaki tablolarda iki modun değerleri birlikte verilmiştir; **mevcut durum bugünkü "büyük" halden comfortable'a çekilir, compact ise veri-yoğun kullanıcılar içindir.**

## 2. Tipografi Ölçeği

Font: Plus Jakarta Sans (Company), Inter (diğerleri) — korunur.

| Token | Kullanım | Comfortable | Compact |
|---|---|---|---|
| --fs-page-title | Sayfa başlığı | 19px / 600 | 17px / 600 |
| --fs-section | Bölüm/kart başlığı | 15px / 600 | 14px / 600 |
| --fs-body | Gövde metni, form değerleri | 13.5px | 13px |
| --fs-table | Tablo hücreleri | 13px | 12.5px |
| --fs-label | Form etiketleri, tablo başlıkları | 12px / 500 | 11.5px / 500 |
| --fs-caption | Yardım metni, meta bilgi | 11.5px | 11px |
| --fs-badge | Rozet/etiket | 11px / 600 | 10.5px / 600 |

Kural: 11px altı metin yasak (okunabilirlik). Sayfa başlığı ile içerik arasında en fazla 1 satırlık breadcrumb/alt başlık.

## 3. Boşluk (Spacing) Ölçeği

4px taban: `--sp-1:4 --sp-2:8 --sp-3:12 --sp-4:16 --sp-5:20 --sp-6:24 --sp-8:32`

| Alan | Comfortable | Compact |
|---|---|---|
| Sayfa iç padding | 20px | 16px |
| Kart/panel padding | 16px | 12px |
| Kartlar arası boşluk | 16px | 12px |
| Form alanları arası (dikey) | 14px | 10px |
| Bölümler arası | 24px | 18px |

## 4. Kontrol Boyutları

| Bileşen | Comfortable | Compact |
|---|---|---|
| Input / select yüksekliği | 34px | 30px |
| Buton (md) | 34px | 30px |
| Buton (sm — tablo içi) | 28px | 26px |
| Checkbox/radio | 16px | 14px |
| İkon boyutu (genel) | 18px | 16px |
| İkon buton | 30×30 | 26×26 |
| Tablo satır yüksekliği | 42px | 34px |
| Tablo başlık satırı | 38px | 32px |
| Sekme (tab) yüksekliği | 36px | 32px |

## 5. Yerleşim (Layout)

| Alan | Değer |
|---|---|
| ModuleRail (sol ikon şeridi) | 56px sabit |
| ContextSidebar | 216px, **daraltılabilir** (48px'e); durum preferences'a kaydedilir |
| İçerik alanı min hedef | 1366px ekranda ≥ 1040px (sidebar daraltıldığında ≥ 1240px) |
| İçerik max genişlik | Sınırsız (fluid); yalnızca ayar/form sayfalarında 960px'e kadar ortalanabilir |
| Modal genişlikleri | sm 420 / md 560 / lg 800 / xl 1040; yükseklik max 85vh, gövde scroll |
| Sayfa başlık şeridi | Tek satır: başlık (sol) + arama/filtre/birincil aksiyon (sağ). İkinci satır YOK |

## 6. Sayfa Tipi Şablonları (tüm modüller bunlara uyar)

**Liste sayfası:** başlık şeridi (tek satır) → filtre çubuğu (yatay, chip'li; gelişmiş filtre açılır panelde) → DataTable (sticky header, tam genişlik) → alt sabit sayfalama. Sayfa içinde ayrıca "özet kart" bandı varsa max yükseklik 72px (mini KPI'lar), varsayılan gizlenebilir.

**Detay sayfası:** kompakt kimlik şeridi (avatar 40px + ad + 3-4 meta + aksiyonlar, toplam ≤ 88px yükseklik) → sekmeler → sekme içeriği. Büyük "hero" kartlar yasak.

**Form sayfası/modalı:** ≥1280px'te 2 kolon grid (etiket üstte); bölüm başlıkları --fs-section; uzun formlarda sağda bölüm navigasyonu; altta sticky aksiyon çubuğu (Kaydet/İptal).

**Dashboard:** widget başlığı 13px, widget padding 12px, grid gap 12px; KPI kartı max 96px yükseklik. react-grid-layout satır yüksekliği bu değerlere göre ayarlanır.

## 7. Renk ve Tema

Mevcut kimlik korunur: Company emerald #10b981, SuperAdmin indigo #6366f1, Portal sky #0ea5e9. **Tema varsayılanı (DOK-3):** Company / SuperAdmin mevcut davranış; **Portal açık tema**. Yeni kural: durum renkleri tek settir (success/warning/danger/info/neutral) ve rozetlerde arka plan %12 opaklık + tam renk metin kullanılır. Kontrast: metinler WCAG AA (4.5:1) altına düşmez.

## 8. Yapılacaklar Listesi (Faz 3 uygulama sırası)

1. theme.css token revizyonu (yukarıdaki tablolar) + density attribute altyapısı
2. DataTable: yeni satır yükseklikleri, sticky header, kolon genişlik/sıra, density desteği
3. Buton/Input/Modal/Tabs ortak bileşenlerinin token'lara bağlanması
4. ContextSidebar daraltma + genişlik güncellemesi
5. Hardcoded px/renk avı (Company → SuperAdmin → Portal sırasıyla)
6. En yoğun 15 ekranda 1366×768 turu: personel listesi/detayı, izin ekranları, dashboard, kullanıcılar, roller, işe alım panosu, ayarlar
7. Portal: Bootstrap değişkenleri aynı ölçeğe çekilir (tam geçiş mobil fazında)

## 9. Yasaklar

Hero/banner kartlar · 2 satırlı sayfa başlıkları · 44px+ input · tablo içinde 13px+ rozet · sayfa içinde sayfa scroll'u (tek scroll alanı) · hardcoded renk/boşluk · 1366'da yatay scroll.

---

## 10. Portal tasarım yönü — Apple iOS 26 "Liquid Glass"

> **Kapsam:** Yalnız `apps/portal`. **Company ve SuperAdmin bu karardan etkilenmez** (masaüstü; mevcut Faz 3 token/density devam eder).  
> Detay uygulama dalgaları: `PORTAL_RAPOR.md` · sıra: `ROADMAP.md` (PORTAL-2a → … → PORTAL-4 → Faz 8 Capacitor).

### 10.1 Karar

Portal arayüzü Apple iOS 26 **Liquid Glass** tasarım diline geçirilir: cam/saydam navigasyon katmanı + opak içerik + HIG ölçüleri + vurgu-renk teması. PORTAL-1 token/bileşenleri **sıfırdan yazılmaz**; iOS malzemesine göre yeniden giydirilir.

### 10.2 Malzeme kuralları (pazarlıksız)

| Kural | Zorunluluk |
|-------|------------|
| Cam / saydamlık | **Yalnız navigasyon katmanı:** alt sekme çubuğu, üst başlık, alt sayfa (sheet) |
| İçerik katmanı | **Her zaman opak.** Bordro tutarı, izin bakiyesi, puantaj saati vb. veriler saydam yüzey üzerine **yazılmaz** |
| Kontrast | Metin ≥ **4.5:1** (WCAG AA). Saydamlık eşiği düşürüyorsa efekt geri alınır |
| `backdrop-filter` | Uzun / kaydırılan listelerde **kullanılmaz** (GPU; düşük Android’de takılma) |
| Erişilebilirlik | `prefers-reduced-transparency` → cam opak yüzeye düşer; `prefers-reduced-motion` → animasyonlar kapanır |

### 10.3 Kademeli açılım

| Dalga | Kapsam | Ölçüt |
|-------|--------|--------|
| **PORTAL-2a** | Temel malzeme (alt çubuk, başlık, sheet) + token katmanı | Okunabilirliği düşüren efekt geri alınır |
| **PORTAL-2b** | Hareket: kaydırmaya duyarlı saydamlık, geçişler, arka plandan renk emme, katmanlı derinlik | Aynı ölçüt |

### 10.4 Ölçü ve tipografi (iOS HIG)

- Tipografi ölçeği (pt): **11 / 13 / 15 / 17 (gövde) / 22 / 28 / 34** (büyük başlık)
- Minimum dokunma hedefi: **44pt**
- Alt sekme çubuğu: ekran kenarlarından girintili, kapsül; içerik altından akar
- Büyük başlık: kaydırınca küçülüp üst başlığa yapışır
- Alt sayfalar: kademeli (yarım / tam yükseklik)
- Satır aksiyonları: kaydırma (swipe)

### 10.5 Lisans kısıtı — ihlal edilemez

| Yasak | Gerekçe / alternatif |
|-------|----------------------|
| **SF Pro / SF Compact / SF Mono** | Apple lisansı yalnız Apple OS arayüz taslakları için; web/Android’e gömmek ve ürüne dahil etmek yasak. → `font-family: -apple-system, system-ui, "Inter", sans-serif` (Apple’da sistem SF render — meşru; diğerlerinde Inter). **`@font-face` ile SF indirme/gömme YASAK.** |
| **SF Symbols** | Aynı kısıt → **kullanılmaz**. İkon: Lucide (mevcut) veya Phosphor/Tabler — nihai seçim **PORTAL-2a**. |

### 10.6 Tema sistemi (Portal)

- **10 vurgu rengi × açık/koyu = 20** kombinasyon. Tema **yalnız vurgu rengini** değiştirir; yüzey, metin, kenarlık token’ları sabit kalır.
- Palet: Mavi (varsayılan) · Indigo · Mor · Pembe · Kırmızı · Turuncu · Sarı · Yeşil · Turkuaz · Grafit
- Firma kurumsal renk girebilir → ton skalası otomatik üretilir.
- Tüm renkler token; bileşende sabit renk yok (mevcut kural).

### 10.7 Ana ekran düzeni (onaylanmış taslak)

1. Selamlama + tarih + vardiya  
2. **BUGÜN** kartı — giriş durumu + çalışılan süre + büyük QR aksiyonu  
3. **2×2** hızlı işlem — izin talebi/bakiye · masraf · bordro · talep  
4. Duyurular listesi  
5. Girintili cam sekme çubuğu: **Ana sayfa · İzinler · [ORTA: taşan QR] · Talepler · Profil**
