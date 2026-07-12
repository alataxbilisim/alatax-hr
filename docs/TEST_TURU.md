# ALATAX HR — Manuel Test Turu

Kısa smoke / regresyon listesi. Otomasyon yerine QA / geliştirici kontrolü içindir.

## Genel

- [ ] API `GET /up` 200
- [ ] Company / Portal / SuperAdmin login (2FA’sız hesap)
- [ ] Tenant izolasyonu: firma A verisi firma B’de görünmez

## Faz 2 (özet)

- [ ] RBAC: yetkisiz sayfa 403 / menüde yok
- [ ] Activity log kritik işlemde yazılıyor

## Faz 4 — Lookup + Select

### Radix Select (boş değer)

- [ ] Opsiyonel alan boş bırakılıp kaydet → payload’da ilk seçenek **gitmez** (`''`)
- [ ] Filtre “Tümü” seçilebilir; X (clearable) ile temizlenir
- [ ] Console’da Radix `SelectItem value=""` hatası yok
- [ ] Açık menü **opak** (`--bg-elevated`); arkadaki yazı okunmaz

### K-A (rename)

- [ ] Listeler’de bir lookup label rename → form/liste/badge yeni label; DB value aynı
- [ ] Kanban: `application_stage` label/renk değiştir → kolon başlığı/renk yeni; başvuru `status` kodu değişmedi

### Listeler sayfası (`/lookups`)

- [ ] Sol tip listesi gruplu; sağ DataTable
- [ ] Firma tipi: ekle / düzenle / sil
- [ ] Sistem tipi: kilitli salt okunur
- [ ] Hibrit: label/renk/sıra serbest; value ekleme/silme yok
- [ ] Kullanımdaki değer silinince pasifleştirme mesajı (K-B)

### İşe Alım kanban

- [ ] Kolonlar `application_stage` lookup’tan
- [ ] Kart durum geçişi çalışıyor (kod sabit)
- [ ] Detay modal durum Select lookup’tan

### Modül dropdown’lar (örnek)

- [ ] Personel: cinsiyet/medeni/sözleşme Lookup + Select
- [ ] İzin: tür zengin API; durum hibrit
- [ ] Varlık: status `disposed` (retired yok); condition Lookup
- [ ] Eğitim: kategori Lookup Select

### Custom field

- [ ] Select alan tanımı `field_options: [{value,label}]` kaydolur
- [ ] Zorunlu custom field boş → employee create 422
- [ ] Personel detayda özel alanlar sekmesi görünür

### 2FA UI

- [ ] 2FA’lı kullanıcı: login → kod ekranı → verify → dashboard
- [ ] Yanlış kod reddedilir
- [ ] Kurtarma kodu yolu çalışır
- [ ] 2FA’sız login değişmedi

## Not

Başarısız maddeyi issue / FAZ4_RAPOR “KARAR BEKLENİYOR” ile bağlayın.
