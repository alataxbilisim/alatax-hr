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

## NEREDEN BAŞLAYACAKSIN — İLK 3 ADIM

### ADIM 0: Belgeleri yerleştir (bugün, 15 dk)
`.cursorrules`'ı köke, diğerlerini `docs/`'a koy. ROADMAP + SISTEM_ISLEYIS'i bir kez baştan sona oku.

### ADIM 1: Tek kararı ver (Faz 1'i kilitler)
SQLite'taki 118 MB **test verisi mi**? 
- Evet → Faz 1'de veri taşımadan temiz PostgreSQL baseline'a geçeriz (kolay yol).
- Hayır (korunacak veri var) → Faz 1'e pgloader migrasyon adımı ekleriz.

### ADIM 2: Faz 0'a başla (ROADMAP'in ilk bloğu)
Faz 0 = stabilizasyon. Yeni özellik YOK; önce kırıkları kapat, altyapıyı kur. Sıra:
1. Migration çakışmalarını çöz + eksik seeder'ları ekle → temiz kurulum
2. Frontend bug turu (loading/isLoading, notificationSlice, route düzeltmeleri)
3. Docker Compose (dev) + CI kurulumu
4. Forgot-password sayfaları + davet e-postası
5. i18n altyapısı + CORS/throttle temizliği

**Her madde için Cursor'a nasıl gideceksin:** maddeyi tek başına, net ve test edilebilir bir prompt olarak ver. Örnek:
> "docs/ROADMAP.md Faz 0'daki migration çakışmalarını çöz. Şu 3 çakışma var: document_categories (0001_03_00 ve 0001_03_01 ikisi de create), announcement_reads (0001_06_04 vs 0001_06_07 çift şema), request_types (çift set). Etkin şemaları koru, ölü migration'ları temizle. .cursorrules'daki migration kurallarına uy. Değişiklikten sonra migrate:fresh --seed'in temiz çalıştığını doğrula."

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
