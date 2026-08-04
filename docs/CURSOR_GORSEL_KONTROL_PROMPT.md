# CURSOR PROMPT — Görsel Kontrol Otomasyonu (tüm birikmiş borç)

> Bu dosyanın tamamını Cursor'a tek mesaj olarak ver. Sonunda `docs/gorsel-kontrol/<tarih>/` klasörü + tek rapor dosyası üretilmiş olacak; o klasörü olduğu gibi bana yükleyeceksin.

---

## 0. GÖREV

Projede **Faz 3, Faz 4, Faz 4B/4C ve Faz G1** raporlarında "KULLANICI GÖRSEL KONTROLÜ BEKLİYOR / DUR — görsel kontrol" diye biriken **tüm** maddeleri, headless tarayıcı ile **otomatik** koş; her madde için **ölçüm + ekran görüntüsü** üret; sonucu tek bir Markdown raporunda topla.

Amaç: gözle bakılacak 40 küçük maddeyi tek koşuda kanıtlanabilir hale getirmek. Sen kod düzeltmeyeceksin — **kanıt üreteceksin**.

---

## 1. PAZARLIKSIZ KURALLAR

1. **Branch:** yalnız `faz4-form-engine`. Yeni branch açma, merge etme, rebase etme.
2. **DB wipe YOK.** `migrate:fresh`, `db:wipe`, `migrate:refresh` yasak. Yalnız `migrate` (gerekirse) + idempotent seed.
3. **Ürün kodu değiştirme.** Bu görevde `app/`, `resources/`, SPA `src/` altına **düzeltme** yazma. İzin verilen tek yeni kod:
   - `scripts/gorsel-kontrol/**` (Playwright koşucusu)
   - `backend/app/Console/Commands/GorselFixtureCommand.php` (yalnız demo verisi hazırlayan, idempotent komut)
   - `docs/gorsel-kontrol/**` (çıktı)
   - `package.json`'a tek script satırı
4. **Bulduğun hatayı DÜZELTME.** Rapora yaz, şiddet ver (🔴 kırık / 🟠 sapma / 🟡 kozmetik), devam et. Koşuyu hata yüzünden durdurma; o maddeyi ❌ işaretle ve sıradakine geç.
5. **Şifre tahmin etme.** Demo kullanıcı şifrelerini `DemoSeeder` / `DemoDataSeeder` içinden oku. Bulamazsan koşuyu durdur ve bana sor.
6. Koşu sonunda `git status` temiz olmalı — yalnız izin verilen dosyalar eklenmiş olsun. Commit at, **push etme**; push kararını ben vereceğim.

---

## 2. HAZIRLIK

### 2.1 Ortam tespiti (varsayma, oku)

- Company / Portal / SuperAdmin SPA portlarını `vite.config.*` dosyalarından oku (bildiğim kadarıyla company `:3002`, portal `:3003`, ama **doğrula**).
- API base URL'i `.env` / SPA env dosyalarından oku.
- Üç SPA + backend'in ayakta olduğunu kontrol et (`GET /up` → 200). Ayakta değilse başlat; başlatma komutlarını rapora yaz.

### 2.2 Fixture komutu

`php artisan gorsel:fixture` yaz. **Idempotent**, yalnız `local` ortamda çalışır, `demo-firma` / `demo-otel-b` / `demo-otel-c` yoksa hata verip çıkar (başka DB'ye asla dokunmaz). Şunları garanti eder:

| Fixture | Amaç |
|---------|------|
| `admin@demo.test` → 3 şirkete membership | çok şirketli seçici testi |
| `tek@demo.test` → **yalnız** `demo-otel-b` membership | seçici gizli testi |
| `demo-otel-b`'de `admin@demo.test` için ≥1 okunmamış bildirim | `other_company_unread` rozeti |
| `portal@demo.test` → yalnız `employee` rolü, Employee bağlı | panel erişim engeli testi |
| `2fa@demo.test` → TOTP aktif, secret'ı dosyaya yazar (`docs/gorsel-kontrol/<tarih>/.secrets.local`, .gitignore'a ekle) | 2FA challenge testi |
| Her şirkette ≥1 personel, ≥1 izin talebi, ≥1 doküman, ≥1 başvuru (kanban dolu olsun) | boş ekran ≠ geçti |
| En az bir lookup değeri "kullanımda" (K-B pasifleştirme testi için) | |

### 2.3 Koşucu

- Playwright + Chromium.
- **Birincil viewport 1366×768** (TASARIM_REHBERI hedefi). Yalnız iki kontrol 1920×1080 (iki kolon form + geniş dashboard) — ayrıca işaretle.
- Her sayfada **console error + failed request** topla; sayfa başına kaydet.
- Ekran görüntüsü: tam sayfa değil, **viewport** (1366×768 gerçeği görünsün). Ek olarak scroll testlerinde alt kısmın görüntüsü.
- Dosya adı: `NN-<blok><id>-<slug>.png` (örn. `07-G3-sirket-degisimi-dashboard.png`).
- PNG'leri optimize et (hedef ≤300 KB/dosya). Toplam klasör 40 MB'ı geçerse rapora not düş.
- Ağ/animasyon yüzünden flaky olmasın: her ekranda `networkidle` + kısa `waitForTimeout` yerine **açık locator bekleme** kullan.

---

## 3. KONTROL LİSTESİ

Her madde için: **ölçüm** (DOM'dan sayısal/boolean kanıt) + **ekran görüntüsü**. Ölçüm mümkün değilse "yalnız görsel" yaz — ben bakacağım.

### BLOK G — Grup/şirket bağlamı (Faz G1 borcu)

| ID | Kontrol | Ölçüm |
|----|---------|-------|
| G1 | `admin@demo.test` login → navbar'da şirket seçici **var** + aktif şirket adı görünüyor | seçici DOM'da var; aktif ad metni boş değil |
| G2 | Seçici açık hâli: 3 şirket listeleniyor | option sayısı = 3 |
| G3 | Şirket A→B değişimi → **dashboard'a dönüş** + liste tazelenmesi | URL dashboard; `X-Company-Id` yeni id; personel listesi satırları A'dan farklı |
| G4 | Şirket değişince **şube seçici sıfırlanır** + yeni şirketin şubeleri gelir | branch select değerleri değişti; eski şube id yok |
| G5 | `tek@demo.test` login → seçici **gizli**, aktif şirket adı yine görünür | seçici DOM'da yok/hidden; ad metni var |
| G6 | Bildirim rozeti: `other_company_unread` göstergesi görünüyor | rozet elementi + sayı > 0 |
| G7 | `localStorage.alatax_company_id` yazılıyor; sayfa yenilendiğinde aktif şirket korunuyor | reload sonrası aynı şirket |

### BLOK P — Portal (dokunulmadı kanıtı)

| ID | Kontrol | Ölçüm |
|----|---------|-------|
| P1 | Portal login (`portal@demo.test`) → **şirket seçici YOK** | seçici selector'ı 0 eşleşme |
| P2 | Portal açık tema varsayılan | `body`/root tema attribute = light |
| P3 | Portal ana sayfalar (profil, izinler, masraflar, talepler) 1366'da scroll'lu ve taşmasız | `scrollHeight > clientHeight` olan sayfada scrollbar erişilebilir; yatay taşma yok (`scrollWidth <= clientWidth`) |
| P4 | Portal'da şirket değiştirme denemesi (elle `X-Company-Id` header'ı) → yok sayılıyor, veri değişmiyor | iki isteğin gövdesi aynı |

### BLOK T — Tasarım / yoğunluk / scroll (Faz 3 borcu)

| ID | Kontrol | Ölçüm |
|----|---------|-------|
| T1 | Scroll: dashboard, personel liste, personel detay, izinler, `/settings`, `/account/*`, `/lookups`, uzun form — hepsinde dikey scroll çalışıyor | her sayfada `.page-content` `overflow-y` = auto **ve** içerik uzun sayfada gerçekten kaydırılabiliyor (scrollTop değişiyor) |
| T2 | Yatay taşma yok (1366'da) | tüm sayfalarda `document.scrollWidth <= 1366` |
| T3 | Density comfortable → tablo satırı **42px**, ikon buton **30px** | `tr` yüksekliği ölçülür |
| T4 | Density compact → **34px** / **26px** | aynı |
| T5 | Personel detay: kimlik şeridi **≤88px**, sekmeler underline stil | `.detail-identity` yüksekliği |
| T6 | Personel formu 1920'de **2 kolon**, aksiyon çubuğu sticky | grid kolon sayısı; scroll'da aksiyon barı görünür kalıyor |
| T7 | Dashboard `.stat-card` **≤96px**; widget başlık/padding kompakt | yükseklik ölçümü |
| T8 | Rapor dashboard: widget sürükle + boyutlandır çalışıyor | sürükle sonrası grid item pozisyonu değişti |
| T9 | Kanban (`/recruitment/applications`): kolon genişliği **180–220px**, kolon içi scroll var, sayfa taşmıyor | kolon `offsetWidth` |
| T10 | Sidebar: ModuleRail **56px**, ContextSidebar **216px**, daraltılmış **48px**; toggle sonrası tercih reload'da korunuyor | genişlik ölçümü + reload |
| T11 | Modal boyutları: lg **800px**, xl **1040px** (Rol formu) | modal `offsetWidth` |
| T12 | İzin takvimi + org şeması 1366'da taşmıyor, hücreler okunur | yalnız görsel + taşma ölçümü |

### BLOK L — Lookup / Select (Faz 4 borcu)

| ID | Kontrol | Ölçüm |
|----|---------|-------|
| L1 | Açık dropdown menüsü **opak** — arkadaki metin okunamıyor | menü arka plan rengi alpha = 1 (computed `background-color`); ayrıca ekran görüntüsü |
| L2 | Uzun etiket trigger'da kesiliyor + `title` attribute var | `text-overflow: ellipsis` + title dolu |
| L3 | Opsiyonel Select boş bırakılıp submit → payload'da `''` gidiyor, ilk seçenek sızmıyor | ağ isteği gövdesi yakalanır |
| L4 | Filtre "Tümü" + X (clearable) temizliyor | filtre sonrası satır sayısı geri artıyor |
| L5 | Console'da Radix `SelectItem value=""` hatası **yok** | konsol logu boş |
| L6 | `/lookups`: sol tip listesi gruplu, sağ DataTable; **sistem tipi salt okunur** (düzenle/sil butonu yok veya disabled) | buton durumu |
| L7 | Hibrit tip: label/renk düzenlenebilir, **value ekleme/silme kapalı** | ilgili input/buton disabled |
| L8 | Lookup rename → liste/badge/form'da **yeni label**, DB value aynı; kanban kolon başlığı da güncelleniyor | rename öncesi/sonrası metin + API'de `status` kodu değişmedi |

> L8 rename'i koşu sonunda **geri al** (aynı komutla eski label'a döndür) — demo verisi kirlenmesin.

### BLOK Y — Yetki / panel erişimi / 2FA (Faz 4 borcu)

| ID | Kontrol | Ölçüm |
|----|---------|-------|
| Y1 | `portal@demo.test` ile Company paneline giriş denemesi → **engel** (403 / yönlendirme) | login yanıtı + son URL |
| Y2 | `/users` listesinde portal-only kullanıcı **yok**; panel erişimli olanda "Panel" rozeti var | satır metinleri |
| Y3 | Yetkisiz kullanıcı `/employees/new` → **Erişim Engeli ekranı** (boş sayfa değil) | ekranda engel bileşeni |
| Y4 | 2FA'lı kullanıcı: login → kod ekranı → doğru kod → dashboard | TOTP kodunu `otplib` ile secret'tan üret |
| Y5 | Yanlış 2FA kodu reddediliyor (hata mesajı görünüyor) | hata elementi |
| Y6 | 2FA'sız login akışı değişmemiş | admin login tek adımda geçiyor |

### BLOK X — Çapraz (her sayfada toplanacak)

| ID | Kontrol |
|----|---------|
| X1 | Ziyaret edilen **her** sayfada console error listesi (boş olmalı) |
| X2 | Ziyaret edilen her sayfada 4xx/5xx dönen istekler (beklenen 403 testleri hariç) |
| X3 | Türkçe metin bozulması (mojibake: "Ä°", "ÅŸ") taraması — sayfa metninde regex |

---

## 4. ÇIKTI YAPISI

```
docs/gorsel-kontrol/2026-08-04/
├── RAPOR.md              ← tek rapor (aşağıdaki format)
├── ss/                   ← ekran görüntüleri (NN-BLOKID-slug.png)
├── konsol.md             ← X1/X2/X3 ham çıktısı (sayfa bazlı)
└── .secrets.local        ← 2FA secret (gitignore)
```

### RAPOR.md formatı

```markdown
# Görsel Kontrol Raporu — <tarih>

**Branch:** faz4-form-engine · **Commit:** <sha> · **Viewport:** 1366×768 (istisnalar işaretli)
**Sonuç:** ✅ <n> · ❌ <n> · ⚠️ <n> · ⏭️ atlandı <n>

## Özet — yalnız sorunlular
| ID | Kontrol | Sonuç | Şiddet | Ölçüm | Görsel |
|----|---------|-------|--------|-------|--------|
| T3 | Density comfortable 42px | ❌ | 🟠 | ölçülen 47px | ss/12-T3-....png |

## Tam liste
(her blok için aynı tablo — ölçüm sütunu SAYI içerir, "OK" yazma)

## Bulgular (detay)
### ❌ T3 — Tablo satırı 42px değil
- Beklenen / ölçülen / hangi sayfada / ekran görüntüsü
- Kök neden **tahmini** (düzeltme YOK)

## Otomatikleştirilemeyenler (senin gözünle bakılacak)
| ID | Neden ölçülemedi | Görsel |

## Koşu bilgisi
- Süre, atlanan maddeler + neden, ortam (port/sürüm), fixture komut çıktısı
```

**Kural:** Ölçüm sütununa "geçti" yazma — **gerçek değeri** yaz (`42px`, `3 seçenek`, `0 console error`). Değer yoksa madde "yalnız görsel"dir.

---

## 5. BİTİŞ KRİTERLERİ (DoD)

- [ ] Listedeki **her** ID için ya sonuç ya da gerekçeli "atlandı" var — sessizce eksik bırakma
- [ ] Her madde en az 1 ekran görüntüsüne bağlı
- [ ] `RAPOR.md` özet tablosu ilk ekranda okunabiliyor (sorunlular üstte)
- [ ] Fixture koşusu ikinci kez çalıştırıldığında aynı sonucu veriyor (idempotent kanıtı — iki kez çalıştır)
- [ ] `git status`: yalnız izin verilen yollar; ürün kodunda **0 değişiklik** (`git diff --stat` raporda)
- [ ] Suite hâlâ yeşil: `php artisan test` sonucu rapora yazılır (656 bekleniyor)
- [ ] Commit atıldı, **push edilmedi**

---

## 6. ÇALIŞMA ŞEKLİ

1. Önce **plan** yaz: hangi dosyaları oluşturacaksın, hangi portlar, kaç ekran görüntüsü bekliyorsun. Bana göster, onay bekleme — ama plan raporun başında dursun.
2. Koşucuyu blok blok yaz ve **her blok bittiğinde çalıştır** (hepsini yazıp sonda koşma — 40 maddelik tek seferlik koşu debug edilemez).
3. Takıldığın yerde uydurma: madde ⏭️ + gerekçe.
4. Sonunda tek cümlelik özet: kaç ✅ / ❌ / ⚠️, en kritik 3 bulgu.

---

## 7. YASAK

- DB wipe · branch değiştirme · push · ürün kodu düzeltme · bulunan hatayı sessizce onarma · şifre/secret'ı repoya commit'leme · ekran görüntüsünde gerçek kişisel veri (demo verisi dışında) · "geçti" yazıp ölçüm koymama
