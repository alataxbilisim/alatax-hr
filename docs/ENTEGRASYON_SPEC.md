# ALATAX HR — Entegrasyon Şartnamesi (ENTEGRASYON_SPEC)

**Amaç:** Dış sistemlerle (bordro, İK, PDKS dosyası, vb.) veri alışverişinin ürün çerçevesi. Uygulama detayı Faz 6/8’de; bu belge sözleşme ve güvenlik sınırıdır.

**İlkeler:** Ürün genel pazara geliştirilir; pilot müşteri formatı gereksinim kilidi değildir. Aynı motor cloud ve on-prem’de çalışır; bağlayıcı görünürlüğü lisansa + `APP_MODE`’a bağlıdır.

---

## 1. Üç katman

| Katman | Ne yapar | Kim özelleştirir |
|--------|----------|------------------|
| **Profil** | Alan eşlemesi: kaynak alan → hedef alan (standart + **özel alanlar**). Eşleştirme anahtarı (sicil no / TCKN vb.) profilde seçilir. | Sistem hazır profil + müşteri kopyası |
| **Bağlayıcı** | Taşıma kanalı: dosya yükleme, izlenen klasör, doğrudan DB, API, webhook | Lisans + ortam (aşağı §3) |
| **Alan sahipliği** | Çakışma kuralı (aşağı) | Profil satırı başına |

### Alan sahipliği (üç durum)

1. **Dıştan gelir — salt okunur:** UI’da düzenlenemez; her import günceller.
2. **Bizde düzenlenir ama import üstüne yazar:** Kullanıcı değiştirebilir; sonraki başarılı import değeri ezer.
3. **Import dokunmaz:** Yalnız ürün içinde yaşar; profil eşlemesinde yok sayılır / yazılmaz.

---

## 2. Profil modeli

- Standart alanların yanı sıra **özel alanlara** (`field_key`) eşlenebilir.
- **Hazır profiller sistem katmanındadır** (`company_id` null / `is_system`); müşteri **kopyalayarak** özelleştirir — sistem panosu / sistem rapor deseniyle aynı.
- Sürüm güncellemesi sistem şablonunu günceller; müşteri kopyasına **otomatik ezme yok** (kopya bağımsız satır).
- İçe aktarma **idempotent:** aynı dosya / aynı eşleştirme anahtarı iki kez yüklenince çift kayıt oluşmaz; güncelle veya no-op.
- Eşleştirme anahtarı profilde seçilir: tipik olarak **sicil no** veya **TCKN** (firma politikasına göre).

---

## 3. Güvenlik sınırı

| Bağlayıcı | Cloud SaaS | On-prem + lisans |
|-----------|------------|------------------|
| Dosya yükleme / indirme | ✅ | ✅ |
| İzlenen klasör (UNC/SMB vb.) | ❌ listede yok | ✅ lisans açıkken |
| Doğrudan DB | ❌ listede yok | ✅ lisans + salt okunur DB kullanıcısı zorunlu |
| API / webhook (ağ) | Kısıtlı (çıkış politikası) | ✅ lisans; kimlik bilgileri şifreli |

- Kimlik bilgileri (DB, API key) **şifreli** saklanır; düz metin log/UI yok.
- Doğrudan DB: **okuma-yalnız** kullanıcı zorunlu; yazma hesabı kabul edilmez.
- Export, **alan iznini** ve **veri kapsamını** (DataScope) delmez — ücret göremeyen kullanıcı ücret kolonunu dışa aktaramaz.

---

## 4. İlk hedefler (v1 adaptörler)

| Sistem | Yön | Not |
|--------|-----|-----|
| **Logo Bordro Plus** | Sicil kartı **içe**; puantaj **dışa** | Format/alan listesi adaptörde; müşteri Logo sürümüne göre profil kopyası |
| **Sedna İK** | İçe/dışa (kapsam müşteri formatına göre) | Format müşteriden alınır; genel profil şablonu sonra sabitlenir |

Diğer bordro/İK adaptörleri (Netsis, Mikro, generic CSV) aynı üç katman üstüne eklenir; yeni motor yazılmaz.

---

## 5. Özel alan yaşam döngüsü

Özel alanlar standart alanlardan **ayrı isim alanında** saklanır; anahtarları çakışamaz (`field_key` ↔ sistem `system_key` / kolon adı ayrımı).

| Kural | Davranış |
|-------|----------|
| Anahtar | `field_key` **değişmez** (API `prohibited` + model guard). |
| Etiket | `field_label` / `label_override` serbestçe değişir; etiket değişikliği profil, rapor kolonu (`cf_{field_key}`) veya form düzenini **kırmaz**. |
| Terfi yok | Özel alan **asla** otomatik standart alana dönüşmez. |

**Yeni standart alan geldiğinde:** Sistem, benzer özel alanı olan firmaya **öneri** gösterir. Taşıma:

1. **Tek seferlik** ve **kullanıcı onaylıdır**.
2. Taşıma öncesi o alana bağlı export profilleri, raporlar ve form düzenleri listelenir (ör. “bu alan 3 profilde ve 2 raporda kullanılıyor”).
3. Taşınan özel alan **silinmez** — “kullanımdan kaldırıldı” (emekli) olarak işaretlenir; JSONB verisi yerinde kalır (okuma/audit için).

---

## 6. Senkronizasyon tasarımı

Üç tetikleyici **birlikte** çalışır; biri diğerinin yerine geçmez:

| # | Tetikleyici | Amaç |
|---|-------------|------|
| 1 | Elle **“Şimdi çek”** + tek kayıt getirme | Aynı gün işe giren personel vb. anlık ihtiyaç |
| 2 | Yapılandırılabilir aralıklı **artımlı** senkron | Gün içi değişiklikler |
| 3 | **Gecelik tam mutabakat** | Artımlının kaçırdıkları |

### Artımlı senkron

- Kaynaktaki **değişiklik zaman damgası** kolonu profilde seçilir.
- Son başarılı senkron anı saklanır; yalnız sonrasında değişenler istenir (**su işareti**).
- Kaynakta güvenilir damga yoksa eşlenen alanların özeti (**hash**) karşılaştırılır.

### Uygulama kuralları

| Durum | Davranış |
|-------|----------|
| Kaynağa ait alan yalnız kaynakta değişmiş | Otomatik uygula |
| Yeni kayıt | Otomatik oluştur; **“yeni geldi”** işareti |
| Çakışma (iki tarafta da değişmiş) | **İnceleme kuyruğu** — asla otomatik uygulama |
| Kaynakta silinme / pasifleşme | **İnceleme kuyruğu** — asla otomatik uygulama |

Çakışma tespiti için her eşlenmiş alanın **son senkron değeri** saklanır.

### Her koşu zorunlulukları

1. **Şema doğrulaması:** Profilde beklenen kolon kaynakta yoksa senkron **durur**, uyarır, **yarım veri yazmaz**.
2. **Sonuç raporu:** yeni / güncellenen / çakışan / hatalı sayıları. **Sessiz çalışan senkron kabul edilmez.**

---

## 7. Modül DoD bağlantısı

Her modül dalgası: ana kayıt tipi özel alan destekler + içe/dışa aktarma profiline açıktır (`MODUL_SPEC.md` ortak standart). Detay uygulama `ROADMAP` Faz 6/8.
