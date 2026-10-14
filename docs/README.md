# docs/ — Doküman haritası

## Okuma sırası

1. [`cursor-gorevleri/00_ORTAK_KURALLAR.md`](cursor-gorevleri/00_ORTAK_KURALLAR.md) — her görevde geçerli ortak kurallar
2. [`DURUM.md`](DURUM.md) — güncel durum, modül/motor olgunluğu, kritik hatalar, borç listesi
3. [`../.cursorrules`](../.cursorrules) — kod yazma kuralları
4. İlgili [`spec/`](spec/) belgesi — üzerinde çalışılan konunun hedef tasarımı

## Klasör yapısı

```
docs/
├── README.md              bu harita
├── DURUM.md               tek durum belgesi
├── spec/                  hedef tasarım (ne yapılacak, nasıl çalışacak)
├── operasyon/             kurulum ve işletim
├── denetim/<tarih>/       bağımsız durum denetimleri (salt-okunur çıktılar)
├── cursor-gorevleri/      adım adım görev tanımları + raporlar/
├── arsiv/                 tarihçe; aktif kaynak değildir, koddan referans verilmez
│   ├── 2026-08/           önceki arşiv
│   └── 2026-10-eski-ad-donemi/  yeniden adlandırma öncesi dönem raporları, ROADMAP, qa, ölçümler
└── backups/               pg_dump yedekleri (git dışı)
```

## Aktif dosyalar

| Dosya | Amaç |
|---|---|
| `DURUM.md` | Projenin tek durum ve teknik borç belgesi |
| `spec/MODUL_SPEC.md` | Hangi özellik hangi modülde; ekranlar ve işlemler |
| `spec/AKIS_SPEC.md` | Portal ↔ yönetim uçtan uca iş akışları |
| `spec/AKIS_SEMA_EDITOR.html` | Company + Portal akışlarının düzenlenebilir görsel şeması |
| `spec/SISTEM_ISLEYIS.md` | Bir çalışanın yaşam döngüsü üzerinden sistemin bütünü |
| `spec/TASARIM_REHBERI.md` | UI ölçüleri, token'lar, ekran tasarım kuralları |
| `spec/I18N.md` | Çeviri altyapısı ve `t()` kuralları |
| `spec/ENTEGRASYON_SPEC.md` | Dış sistem entegrasyonu, aktarım paketleri |
| `spec/AYAR_MOTORU.md` | Settings Registry ve bağlamsal ayar paneli |
| `spec/EXPORT_KAPSAM_BIRLIGI.md` | Export'ta DataScope birliği, chunk/stream, export registry |
| `spec/EXPORT_GUVENLIK_VE_ENTEGRASYON_EKLERI.md` | Export yetki kapatma, `field_key` değişmezliği |
| `operasyon/DEPLOY_UBUNTU.md` | Ubuntu sunucuya Docker ile kurulum |
| `operasyon/TEST.md` | Backend test altyapısı: test DB, komutlar, paralel, kapsam, mimari testler, test şablonu |
| `denetim/2026-10-13/` | Claude + Cursor durum denetimleri ve ham çıktıları |
| `cursor-gorevleri/` | Görev tanımları (`ADIM_XX_*.md`) ve raporları (`raporlar/`) |
