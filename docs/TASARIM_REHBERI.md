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

Mevcut kimlik korunur: Company emerald #10b981, SuperAdmin indigo #6366f1, Portal sky #0ea5e9; dark varsayılan. Yeni kural: durum renkleri tek settir (success/warning/danger/info/neutral) ve rozetlerde arka plan %12 opaklık + tam renk metin kullanılır. Kontrast: metinler WCAG AA (4.5:1) altına düşmez.

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
