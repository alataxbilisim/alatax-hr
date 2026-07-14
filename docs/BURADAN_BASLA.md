# ALATAX HR — BAŞLANGIÇ KILAVUZU (BURADAN_BASLA)

Bu klasördeki 7 belge, projenin tüm planını oluşturur. Bu dosya hepsini bağlar ve **nereden başlayacağını** söyler.

---

## BELGELER VE GÖREVLERİ

| # | Belge | Cevapladığı soru | Ne zaman kullanılır |
|---|-------|------------------|---------------------|
| 1 | **SISTEM_ISLEYIS.md** | Program uçtan uca nasıl çalışır? (çalışan yaşam döngüsü) | Bütünü anlamak için ÖNCE bu |
| 2 | **ROADMAP.md** | Ne zaman, hangi sırayla geliştirilir? | Planı yürütürken ana pusula |
| 3 | **MODUL_SPEC.md** | Hangi modülde hangi ekran/işlem var? | Bir modülü geliştirirken kapsam |
| 4 | **AKIS_SPEC.md** | Bir işlem başlayınca ne olur? (akış detayı) | Modül geliştirirken davranış |
| 5 | **TASARIM_REHBERI.md** | Ekranlar nasıl görünür? (boyut/token) | Faz 3 + her UI işinde |
| 6 | **cursor-rules.md** | Kod nasıl yazılır? (konvansiyon) | `.cursorrules` olarak repoda sürekli |
| 7 | **CURSOR_PROJE_ANALIZ_PROMPT.md** | Projenin güncel röntgenini nasıl çekerim? | Snapshot güncellemek gerektiğinde |

**Okuma sırası (ilk kez):** 1 → 2 → 3 → 4 → 5. (6 ve 7 araç niteliğinde.)
**Katman mantığı:** SISTEM_ISLEYIS = hedef resim · ROADMAP = ona giden yol · MODUL/AKIS/TASARIM = yolun her adımının detayı · cursor-rules = her adımda uyulacak kural.

---

## REPO'YA YERLEŞTİRME

```
alatax-hr/
├── .cursorrules              ← cursor-rules.md içeriği (bu adla, kök dizinde)
└── docs/
    ├── SISTEM_ISLEYIS.md
    ├── ROADMAP.md
    ├── MODUL_SPEC.md
    ├── AKIS_SPEC.md
    ├── TASARIM_REHBERI.md
    └── CURSOR_PROJE_ANALIZ_PROMPT.md
```

`.cursorrules` mutlaka kök dizinde olmalı ki Cursor otomatik okusun. Diğerlerini `docs/` altında topla; modül promptu verirken ilgili dosyayı Cursor'a bağlam olarak ekle.

---

## NEREDEN BAŞLAYACAKSIN — GÜNCEL DURUM (Temmuz 2026)

> **Eski “Faz 0’a başla / SQLite kararı” bloğu geçersizdir.** Aşağıdaki gerçek duruma göre devam edin.

### Güncel durum (kısa)

| Alan | Gerçek |
|------|--------|
| Branch | `faz4-form-engine` |
| Faz 0 / 1 / 2 | **Kapalı** (stabilizasyon, pgsql, RBAC+audit) |
| Faz 3 | Tasarım sistemi / kompakt UI **kodda** (theme tokens, density) |
| Faz 4 + FAZ A/B | **Aktif** — Lookup/Select, onay motoru (izin pilot), branch-context; B-1 izin, B-2 işe alım, B-3 masraf/puantaj HR, B-DB test izolasyonu |
| Veritabanı | **PostgreSQL** aktif (`alatax_hr`); testler yalnızca `alatax_hr_testing` |
| Test | ~**338** passed (B-3 sonrası) |

### Sıradaki odak

1. ROADMAP Faz 4 kalanları (Form Engine 4A, bildirim 4C, Stüdyo) + FAZ B akış tamiri devamı.
2. Yeni iş: ROADMAP/MODUL_SPEC’e bak; `.cursorrules` + `docs/FAZ_B_RAPOR.md` bağlamı kullan.
3. SQLite / “temiz MVP’den başla” yolu **yok** — mevcut pgsql + seed ile çalış.

**Cursor’a iş verirken:** tek madde, test edilebilir DoD, yıkıcı DB komutu yok (`alatax_hr` dokunulmaz).
---

## ÇALIŞMA RİTMİ (her faz için tekrarla)

1. **Faz başı:** Bana gel. O fazın ROADMAP maddelerini, ilgili spec dosyalarına dayanarak, Cursor'a verilecek **sıralı promptlara** birlikte çevirelim.
2. **Geliştirme:** Her prompt → Cursor → test → commit. Bir madde bitmeden diğerine geçme.
3. **Faz sonu:** DoD (ROADMAP'te her fazın sonunda) karşılandı mı kontrol et. Tutarlılık turu (isimlendirme, ölü kod). Bir sonraki faza geç.
4. **Kapsam kontrolü:** Araya giren yeni fikir → ROADMAP Backlog'a yaz, araya alma.

---

## KRİTİK HATIRLATMALAR

- **Önce platform, sonra modül.** Motorları (Faz 2/4/5) atlayıp modül yazma cazibesine kapılma; her modülü baştan yazmak zorunda kalırsın.
- **Backend her zaman asıl güvenlik kapısı.** Frontend yetki kontrolü sadece UX; API'de permission enforce edilmezse yetki yok demektir (Faz 2'nin özü bu).
- **Big-bang refactor yok.** Form Engine ve rapor motoruna geçiş modül modül; eski ekran, yenisi kanıtlanana kadar durur.
- **Solo geliştirici sınırı gerçek.** Derinlik > genişlik. Yarım 15 modül yerine tam 7 modül.
- **Bu belgeler yaşayan belgelerdir.** Her faz sonunda, pilot geri bildirimiyle güncellenir.

---

*Hazır olduğunda: "SQLite verisi test verisi" kararını ver ve "Faz 0'a başlayalım" de — ilk fazın Cursor promptlarını sıralı olarak birlikte çıkaralım.*
