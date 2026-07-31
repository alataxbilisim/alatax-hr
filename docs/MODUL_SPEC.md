# ALATAX HR — MODÜL ŞARTNAMESİ (MODUL_SPEC)

**Amaç:** Hangi özellik hangi modülde yaşar, her modülün ekranları ve işlemleri (CRUD+) nedir — Cursor'a modül geliştirme promptu verirken referans şartname. ROADMAP Faz 6'nın detay karşılığıdır.

**Gösterim:** C=Create R=Read/List U=Update D=Delete(soft). "+" işaretli olanlar CRUD dışı özel işlemlerdir. 🆕 = yeni eklenen, 🔄 = mevcut ama taşınan/birleşen yapı.

**Ana operasyonel modül sayısı:** **14** (aşağıda B1–B14). Çekirdek platform (A) ve premium katman (C) bu 14’ün dışındadır.

---

## Ortak standart (her modül — tekrar yazılmaz)

Her ana / çekirdek / premium modül için zorunlu ortak katman:

| Standart | Açıklama |
|---|---|
| **Modül panosu** | `dashboards.module_key` ile modüle bağlı, düzenlenebilir pano (Dashboard v2). Rol varsayılanları + kullanıcı kişiselleştirme. |
| **Rapor motoru dataset** | Semantic layer’da en az bir dataset kaydı; hazır raporlar motorda tanımlı, kopyalanıp özelleştirilebilir. |
| **Form Engine bağı** | Entity formları Form Engine üzerinden (layout, koşullu görünürlük, alan izinleri, custom field). |
| **Workflow motoru bağı** | Onay / atama / eskalasyon gerektiren işlemler Workflow Engine v2 zincirine bağlanır (tek seviyeli hardcoded onay yasak). |
| **KVKK sınıflandırması** | Modül verisi: **normal** / **kişisel** / **özel nitelikli**. Sağlık, biyometri, konum, TCKN, ücret gibi alanlar işaretlenir; API Resource + alan izni + saklama politikası buna göre. |
| Liste görünümleri + Excel export | Kayıtlı görünümler; DataTable ortak bileşeni. |
| İzin anahtarları | `{modul}.{sayfa}.{aksiyon}` — korumasız route yok. |
| Bildirim + audit | Kritik olaylarda bildirim; tüm yazma işlemleri audit’li; tenant scope (`company_id`). |

---

## A. ÇEKİRDEK PLATFORM (her lisansta, 14’ün dışında)

### A1. Kullanıcı & Rol Yönetimi
**Ekranlar:** Kullanıcılar, Kullanıcı Detay, Roller, Rol Detay (izin matrisi)
**İşlemler:** Kullanıcı CRUD + davet (e-postalı) + import/export + toplu güncelleme + aktif/pasif + şifre sıfırlat + 2FA yönet + oturumları görüntüle/sonlandır + avatar. Rol CRUD + izin matrisi (modül→sayfa→aksiyon) + veri kapsamı (own/team/department/branch/company) + alan izinleri + rol kopyala.
**Not:** `user.type` yalnızca super_admin ayrımı için kalır; firma içi tüm yetki Spatie rollerinden.
**KVKK:** kişisel (e-posta, telefon, oturum).

### A2. Self-Servis Portal (personel yüzü)
**Ekranlar:** Dashboard, Profilim, İzinlerim, Belgelerim, Bordrolarım, Eğitimlerim, Performansım, Anketler, Puantajım, Masraflarım, Duyurular, Taleplerim, 🆕 KVKK Onaylarım, 🆕 Zimmetlerim, 🆕 Avanslarım / Harcırahlarım (Ücret lisanslıysa)
**Kural:** Portal hiçbir yönetim işlemi içermez; yalnızca kendi verisi + talep başlatma. Her modülün "personel yüzü" bu uygulamada yaşar.
**KVKK:** kişisel + (sağlık/biyometri portalda gösterilirse) özel nitelikli — alan izni zorunlu.

### A3. Duyurular & İç İletişim
**Ekranlar:** Duyurular, Duyuru Detay (okunma takibi)
**İşlemler:** Duyuru CRUD + hedefleme (tüm firma/şube/departman) + yayınla/arşivle + okundu raporu + öne çıkarma.
**KVKK:** normal (içerik); okunma kaydı kişisel.

### A4. Talep & Vaka Yönetimi (İK Helpdesk)
**Ekranlar:** Talep Kuyruğu, Talep Detay, Talep Tipleri, 🆕 SLA Ayarları
**İşlemler:** Talep tipi CRUD (Form Engine’li dinamik form + onay akışı bağlama). Talep aç + ata + yanıtla/yorum + durum değiştir + kapat. 🆕 SLA: tip bazlı hedef süre, gecikme uyarısı, otomatik atama kuralı.
**Durumlar:** open → in_progress → waiting → resolved → closed.
**KVKK:** kişisel (talep içeriği firma politikasına göre).

### A5. Bildirim Merkezi
**Ekranlar:** Bildirimler (in-app), Bildirim Tercihlerim (portal+company)
**Yönetim (Ayarlar Stüdyosu'nda):** olay→şablon eşleme, şablon düzenleme (değişkenli), kanal seçimi (in-app/e-posta/SMS), günlük özet.
**KVKK:** kişisel (iletişim tercihleri).

### A6. Audit & Log
**Ekranlar:** Denetim Kayıtları (global arama/filtre/export), her detay sayfasında "Geçmiş" sekmesi
**İşlemler:** Salt okunur + export. Kapsam: CRUD diff’leri, giriş/çıkış, hassas okuma (bordro/ücret), export’lar, izin/rol/ayar değişiklikleri.
**KVKK:** kişisel + (maskelenmiş) özel nitelikli alanlar logda asla açık yazılmaz.

### A7. KVKK
**Ekranlar (9 sekme — D2c sonrası):** Veri Envanteri · Aydınlatma Metinleri · Rıza Durumu · Veri Sahibi Talepleri · Saklama Politikaları · İmha Kuyruğu · İmha Kayıtları · Hukuki Tutmalar · İhlal Defteri  
**İşlemler:** Aydınlatma metni versiyonla + portal ilk girişte onay topla. Veri ihracı üret (JSON/PDF). Silme/anonimleştirme talebi → onay akışı → anonimleştirme job’ı. Saklama süresi CRUD + dry-run imha aday listesi (gerçek imha ayrı onay).  
**Not (QA-2):** Spec’te eskiden 6 sekme yazıyordu; D2c imha/hold ekranları eklendiği için **9 sekme doğru** — gruplamaya gerek yok, operasyonel akış (politika → kuyruk → kayıt) ayrı sekmelerde net.
**Not:** Lisansla satılmaz; yasal zorunluluk — her pakette açık.

---

## B. ANA OPERASYONEL MODÜLLER (14)

### B1. Organizasyon 🔄 (Yönetim’den operasyonel modüle)
**Ekranlar:** Firma Bilgileri (özet), Şubeler, Departmanlar, Pozisyonlar, Organizasyon Şeması, 🆕 Norm Kadro, 🆕 Kadro Talebi
**İşlemler:** Şube CRUD + merkez işaretleme. Departman CRUD (hiyerarşik, yönetici atamalı). Pozisyon CRUD (unvan kataloğu; `employees.title` → pozisyon referansı). Org şeması görüntüle + PNG/PDF export. 🆕 Norm kadro tanımı (pozisyon × şube/dept hedef sayı). 🆕 Kadro talebi → workflow onayı → (opsiyonel) işe alım ilanı tetikleme.
**Not:** Ayarlar Stüdyosu’ndaki “Firma & Şubeler” kısa yolları bu modüle yönlendirir; veri sahibi buradır.
**KVKK:** normal (yapı); yönetici ataması kişisel.

### B2. Personel / Özlük
**Ekranlar:** Personel Listesi, Personel Detay (sekmeler: Genel · İş Bilgileri · Ücret · Evraklar · İzin Özeti · Zimmetler · Eğitimler · Performans · 🆕 Disiplin & Ödül · 🆕 Vekalet · Geçmiş/Audit), Yeni Personel Sihirbazı, 🆕 İşten Çıkış Sihirbazı, Raporlar
**İşlemler:** Personel CRUD + import/export + toplu işlem + portal erişimi aç/kapat + evrak yükle/süre takibi. 🆕 İşten çıkış: çıkış nedeni (SGK kodlu), checklist (zimmet iadesi + evrak + erişim kapatma), ibraname. 🆕 **Disiplin & Ödül:** savunma → tutanak → karar akışı (workflow); ödül kaydı. 🆕 **Vekalet & Geçici Görevlendirme:** tarih aralıklı vekalet (onay zincirine bağlanır) + geçici görev/lokasyon.
**TR alan seti:** TCKN (doğrulamalı), SGK sicil, İŞKUR meslek kodu, eğitim durumu, engel oranı, yabancı çalışma izni, BES katılım, acil durum kişisi.
**Sekme kuralı:** İzin/Zimmet/Eğitim/Performans sekmeleri ilgili modül lisanslıysa görünür (cross-module görünüm; veri sahibi ilgili modül).
**KVKK:** kişisel (kimlik, iletişim); engel oranı / sağlık notu → **özel nitelikli**; ücret → kişisel + alan izni `salary.view`.

### B3. İzin Yönetimi
**Ekranlar:** İzin Talepleri (onay kuyruğu), İzin Takvimi, Bakiyeler, İzin Türleri, Resmi Tatiller, Hakediş Politikaları, Raporlar
**İşlemler:** Tür CRUD (belge şartı, cinsiyet kısıtı, min. bildirim, onay akışı bağlama). Talep C-R-U(iptal) + onayla/reddet + belge yükle. Bakiye görüntüle + 🆕 manuel düzeltme (gerekçeli, audit’li) + devir/hakediş (scheduler). Tatil CRUD (yarım gün). Politika CRUD + aylık hakediş.
**TR seed:** yıllık izin kıdem (14/20/26), yasal türler (evlilik 3, babalık 5, vefat 3, doğum 16 hf, süt izni), resmi tatil takvimi.
**Durumlar:** pending → approved / rejected / cancelled (workflow entegre).
**PDKS bağı (zorunlu kural):** Onaylı izin kaydı, ilgili gün(ler) için **puantaj kartına otomatik akar** (devamsızlık/izin gün tipi). İptal/red → puantaj yansıması geri alınır veya kilitli dönemde düzeltme talebi açılır. İzin ↔ PDKS çift yönlü manuel çelişki çözümü audit’lidir.
**KVKK:** kişisel; sağlık raporu ekli izinler → **özel nitelikli**.

### B4. PDKS 🆕 (eski “Puantaj & Vardiya”nin genişletilmiş hali)
**Ekranlar / alt bölümler:**

| Alt bölüm | Ekranlar |
|---|---|
| Günlük Takip | Günlük yoklama panosu, anormal kayıtlar, canlı durum |
| Puantaj | Puantaj kartı listesi, kart oluşturma, onay kuyruğu, dönem kilidi |
| Vardiya | Vardiya tanımı, plan/atama takvimi, rotasyon, yasal kontrol (haftalık süre vb.) |
| Mesai | Fazla mesai talepleri / hesap / onay |
| Kurallar | Tolerans, yuvarlama, mola, çalışma profili |
| QR & Cihazlar | QR noktaları, cihaz kayıtları, eşleştirme |
| Manuel İşlemler | Audit’li düzeltme (gerekçe zorunlu) |
| Ziyaretçi Yönetimi | Ziyaretçi check-in/out, kart/etiket |
| Aktarım | Bordro aktarım paketi + dış sistem export |

**İşlemler:**
- Günlük kayıt CRU + anomali işaretleme; check-in/out (portal/QR/cihaz).
- **Puantaj kartı anahtarı:** `(dönem × departman|şube)`. Kart oluştur → doldur (otomatik + manuel) → **onay zinciri:** puantör → gözetmen → İK (Workflow Engine; adımlar firma yapılandırır) → **kilit**. Kilitli kart yazmaya kapalı; açma yetkisi ayrı permission + audit.
- Vardiya CRUD + personele/dept atama + rotasyon şablonu; yasal süre/dinlenme kontrolü (uyarı; parametreler ayar tablosunda).
- Mesai hesap + onay (workflow).
- Kurallar CRUD (tolerans dk, yuvarlama, mola düşümü, profil→personel/dept bağlama).
- QR nokta CRUD + cihaz kaydı; canlı cihaz protokolü → Faz 8 backlog.
- Manuel düzeltme: eski/yeni değer + gerekçe + audit (bypass yok).
- Ziyaretçi CRUD + giriş/çıkış.
- Aktarım: standart **bordro aktarım paketi** üret (bkz. “Dış Sistem Entegrasyonları”); CSV/Excel şablon import (PDKS cihaz dosyası).

**İzin bağı:** B3’teki kural — onaylı izin puantaja otomatik yansır.
**KVKK:** kişisel; **konum / biyometri (parmak, yüz)** varsa → **özel nitelikli**; ziyaretçi kimlik bilgisi kişisel.

### B5. Ücret & Ödemeler 🔄 (Masraf + Ücret Yönetimi birleşimi + genişletme)
**Ekranlar:** Ücret Bantları, Personel Ücret Geçmişi, Zam Dönemleri, Toplam Gelir Görünümü, Masraf Onay Kuyruğu, Masraf Kategorileri & Limitler, 🆕 Avans & Borç, 🆕 Harcırah / Seyahat, Raporlar, (Portal: Masraflarım / Avanslarım)
**İşlemler:**
- Bant CRUD (pozisyona bağlı). Ücret değişikliği (efektif tarihli, gerekçeli). Zam dönemi + toplu öneri + onay + uygula. Toplam gelir (maaş + yan hak).
- Masraf: kategori + limit; talep + fiş; onay; “ödendi” işaretle. Durumlar: draft → submitted → approved/rejected → paid.
- 🆕 **Avans-Borç:** avans talebi; taksit planı; faiz (opsiyonel); icra/nafaka kesinti kaydı; **kesinti öncelik sırası** (firma ayarı). Bordro aktarım paketine kalem olarak düşer.
- 🆕 **Harcırah/Seyahat:** seyahat talebi + gün/km/konaklama kuralları + masraf köprüsü + onay.
**Alan izni:** ücret varsayılan yalnızca `salary.view`.
**Not:** Tam bordro motoru kapsam dışı (Faz 8+); bu modül bordronun oturacağı veri + aktarım paketini hazırlar.
**KVKK:** ücret / borç / icra → **kişisel** (hassas); alan izni + audit zorunlu.

### B6. İşe Alım
**Ekranlar:** Pozisyon İlanları, Başvurular (🆕 Kanban + liste), Aday Detay, CV Havuzu, Mülakatlar (takvim), 🆕 Teklifler, Başvuru Form Builder 🔄(Form Engine), Kariyer Sayfası Ayarları, Raporlar
**İşlemler:** İlan CRUD + yayınla/kapat + public link. Başvuru durum akışı + not/puan + CV havuzu. Mülakat + scorecard. 🆕 Teklif oluştur/gönder/sonuç. Kaynak yönetimi. Public başvuru + KVKK aday rızası.
**Durumlar:** new → screening → interview → offer → hired / rejected / pool.
**KVKK:** kişisel (aday CV, iletişim); engel/sağlık sorusu varsa → özel nitelikli.

### B7. Oryantasyon & Çıkış 🔄 (eski Onboarding / Offboarding)
**Ekranlar:** Şablonlar 🔄, Aktif Süreçler, Süreç Detay (görevler + milestone’lar), Preboarding, Buddy Yönetimi, Çıkış Checklist’leri
**İşlemler:** Şablon CRUD (görev + sorumlu rol + gün ofseti). Süreç başlat (işe alımdan otomatik tetiklenebilir — workflow) + görev tamamla/ata + milestone. Preboarding token (evrak ön toplama). Buddy ata. Çıkış checklist’i Personel çıkış sihirbazıyla ortak motor.
**KVKK:** kişisel.

### B8. Performans
**Ekranlar:** Dönemler 🔄, Kriterler & Yetkinlikler 🔄, Değerlendirmeler, Değerlendirme Detay, Hedefler/OKR, 360° Geri Bildirim, 1:1 Görüşmeler, 🆕 Kariyer Yolları, 🆕 Yedekleme Planı, 🆕 Kalibrasyon, Raporlar
**İşlemler:** Dönem CRUD + başlat/kapat (sihirbaz: kapsam + kriter + takvim). Kriter/yetkinlik + pozisyona bağlama. Değerlendirme ata/doldur/onayla + skor. Hedef + KR ilerleme. 360: davet + anonim yanıt. 1:1 planla + gündem/aksiyon. 🆕 Kariyer yolu CRUD (pozisyon basamakları + yetkinlik eşikleri). 🆕 Yedekleme (successor) planı pozisyon/kişi bazlı. 🆕 Kalibrasyon oturumu (dağılım düzeltme, onaylı skor kilidi).
**KVKK:** kişisel (değerlendirme içeriği).

### B9. Eğitim (LMS) 🆕 (eski “Eğitim”in LMS genişlemesi)
**Ekranlar / alt bölümler:**

| Alt bölüm | İçerik |
|---|---|
| İçerik | Katalog, kurs editörü, ders tipleri, video kütüphanesi, soru bankası, sertifika şablonları |
| Atama | Öğrenme yolu, otomatik atama (pozisyon/dept), zorunlu takip, sınıf eğitimi oturumları |
| Öğrenme (Portal) | Oynatıcı, izleme süresi, ders arası soru, sınav |
| Ölçme | Sonuç analizi, izleme analitiği, değerlendirme |
| Eğitmen & Maliyet | Eğitmen kaydı, oturum maliyeti, bütçe özeti |

**İşlemler:** Kurs/ders CRUD; video: **kendi yükleme** + YouTube/Vimeo gömme — **indirme YOK**. Soru bankası + sınav. Sertifika üret (geçerlilik + bitiş bildirimi). Öğrenme yolu + zorunlu atama + hatırlatma (workflow). Sınıf oturumu (tarih/kontenjan/yoklama). Portal: progress, quiz geçişi, sınav sonucu.
**Faz 8 notu:** SCORM paket import — backlog; v1’de native içerik + gömülü video.
**İSG köprüsü:** İSG zorunlu eğitimleri bu LMS üzerinden atanır/tamamlanır (B10).
**KVKK:** kişisel (sonuç, izleme süresi); video yüz kaydı yok varsayılan.

### B10. İSG 🆕
**Ekranlar / alt bölümler:**

| Alt bölüm | İçerik |
|---|---|
| Yapılandırma | NACE, tehlike sınıfı, İSG ekibi, yasal yükümlülük takvimi |
| Risk Yönetimi | Tehlike/risk envanteri, risk değerlendirme, önlem takibi |
| Olay Yönetimi | İş kazası, ramak kala, meslek hastalığı, DÖF |
| Sağlık Gözetimi | Muayene/periyot, raporlar — **özel nitelikli veri** |
| Eğitim köprüsü | LMS (B9) zorunlu İSG eğitim atama/tamamlama |
| Saha & Ekipman | KKD, periyodik kontrol, denetim, acil durum tatbikatı |
| Kurul | İSG kurul toplantı/tutanak |
| Taşeron / Alt İşveren | Firma kaydı, belge/eğitim yeterlilik takibi |
| İBYS | Raporlama / dış bildirim hazırlık (adaptör Faz 8) |

**İşlemler:** Yapılandırma CRUD; risk CRUD + önlem kapatma; olay aç → soruşturma → DÖF → kapat (workflow); sağlık kaydı (sıkı alan izni); KKD zimmet/kontrol; kurul gündem/tutanak; taşeron yeterlilik.
**⚠️ Mevzuat parametreleri kuralı (şartname — kod sabiti YASAK):** Periyotlar, eşikler, destek elemanı oranları, tehlike sınıfı zorunlulukları vb. **kodda hardcode edilmez**. Ayar / lookup tablolarında tutulur; firma veya platform admin günceller. Mevzuat değişince migration/seed ile parametre güncellenir, iş kuralı kodu parametreyi okur.
**KVKK:** olay/kimlik → kişisel; **sağlık gözetimi → özel nitelikli** (ayrı permission + maskeleme + saklama politikası zorunlu).

### B11. Varlık / Zimmet
**Ekranlar:** Varlıklar, Varlık Detay, Kategoriler 🔄, Zimmetler 🔄, Bakım, Yazılım Lisansları, Varlık Talepleri
**İşlemler:** Kategori CRUD. Varlık CRUD + durum (stokta/zimmetli/bakımda/hurda). Zimmet ver/iade + 🆕 imza alanlı tutanak PDF. Bakım + maliyet. Yazılım lisansı + koltuk. Talep → onay → zimmetle.
**Faz 8 notu:** Araç & Filo (plaka, muayene, yakıt, ceza) bu modül altında genişletilir — v1 kapsamı dışı.
**KVKK:** kişisel (zimmet sahibi); varlık envanteri normal.

### B12. Anket & eNPS
**Ekranlar:** Anketler, Anket Builder, Sonuçlar & Analiz, 🆕 eNPS Trendi
**İşlemler:** Anket CRUD (çoktan seçmeli, ölçek, açık uç, eNPS) + hedef kitle + yayınla/kapat + hatırlatma. 🆕 Anonimlikte yanıt-kimlik ilişkisi yazılmaz. Sonuç + export + eNPS trend.
**KVKK:** anonim → normal (kimlik bağlanmaz); isimli → kişisel.

### B13. Doküman+ (Gelişmiş Evrak)
**Ekranlar:** Doküman Kütüphanesi, Kategoriler, 🆕 Zorunlu Evrak Setleri, 🆕 Süre Takibi, Onay Bekleyenler, Raporlar
**İşlemler:** Kategori CRUD. Yükle/indir/paylaş + versiyonlama + onay. 🆕 Zorunlu set (işe giriş) + eksik takibi. 🆕 Süreli evrak (sertifika, sağlık raporu) + bitiş uyarısı.
**Not:** Temel personel evrakı B2’de ücretsizdir; versiyonlama/onay/zorunlu set/süre takibi bu lisansındadır.
**KVKK:** kişisel; sağlık raporu / adli belge → **özel nitelikli**.

### B14. Analitik 🔄 (eski C1 Rapor Builder + panolar — ana modül)
**Ekranlar:** Panolar (modül bazlı, `module_key`), Raporlarım, Rapor Builder (3 panel), Ölçü Kütüphanesi, Zamanlanmış Raporlar
**İşlemler:** Pano CRUD + widget (kayıtlı rapor / inline) + çapraz/global filtre + paylaşım. Rapor CRUD (dataset + kolon/filtre/grup/grafik/pivot) + paylaş + export + zamanla. Ölçü (hesaplanan alan) CRUD — kapalı DSL. Hazır raporlar kopyalanır.
**Not:** Modüllerle gelen hazır raporlar çekirdek/ilgili lisansla gelir; “kendin kur” builder bu modülün (veya Enterprise paketinin) parçasıdır. 🔄 `hr-analytics` sayfaları bu motorun üstüne taşınır.
**KVKK:** çıktı, kaynak dataset’in sınıflandırmasını miras alır; maaş ölçüsü alan iznine bağlı.

---

## C. PREMIUM KATMAN (14’ün üstü / paket eklentisi)

### C1. Workflow Otomasyonu (gelişmiş)
**Ekranlar:** Akış Listesi, Akış Builder (tetikleyici→koşul→aksiyon), Onay Delegasyonları, Çalıştırma Logları
**İşlemler:** Akış CRUD + aktif/pasif + test. Delegasyon CRUD. Log.
**Not:** Modüllerin varsayılan onay zincirleri çekirdek/ilgili modülde; özel tetikleyici+aksiyon kataloğu ve stüdyo tasarımcısı bu katmanda. Motor altyapısı Faz 4’te doğar.

### C2. API & Webhook
**Ekranlar:** API Anahtarları, Webhooks + log
**İşlemler:** CRUD + izin kapsamlı anahtar + API dokümantasyon sayfası.

---

## D. PLATFORM (modül değil, altyapı)

**Ayarlar Stüdyosu sekmeleri:** Firma & Organizasyon · Modüller & Lisans · Formlar & Alanlar (Form Engine) · Liste Görünümleri · İş Akışları · Bildirim Şablonları · Roller & İzinler · İzin/Tatil Politikaları · PDKS Kuralları · İSG Parametreleri (lookup) · Görünüm (tema/density/logo) · API & Webhook · Veri (import/export/KVKK).

**SuperAdmin (yalnızca cloud):** Firmalar, Paketler, Modüller, Kullanıcılar, Cari, Loglar, Dashboard + 🆕 firma bazlı kullanım metrikleri.

---

## D2. MOBİL UYGULAMA (Portal native paketleme)

> UI dil: `TASARIM_REHBERI.md` §10. Sıra: ROADMAP PORTAL-2a…4 (web) → Faz 8 (Capacitor/mağaza). **Bugün paket kurulmaz.**

### D2.1 Teknoloji kararı

| Karar | Detay |
|-------|--------|
| **Capacitor** | Mevcut Portal React SPA native kabuğa alınır |
| Flutter / React Native | **YOK** — yeniden yazım yapılmaz |
| Gerekçe | Aynı kod tabanı; API-first zaten mobil hazır |

### D2.2 Platform gerçekleri (planlama girdisi)

| Platform | Gerçek |
|----------|--------|
| **Android** | Windows/Linux’ta Android Studio ile APK/AAB. Play Console tek seferlik ücret. |
| **iOS** | ⚠️ **macOS + Xcode zorunlu.** Seçenek: Mac donanımı, bulut Mac veya macOS CI. Apple Developer Program yıllık ücret. |
| **Mağaza incelemesi** | Yalnız web saran “wrapper” uygulamalar reddedilebilir. **Karşı önlem (yayın şartı, süs değil):** QR/kamera, push, biyometrik giriş, çevrimdışı, dosya paylaşımı. |

### D2.3 Dağıtım modeli (güncel karar)

| Madde | Karar |
|-------|--------|
| Yayın kapsamı | Ürün **şu an tek müşteri** için yayınlanır |
| Firma/sunucu seçim ekranı | **OLMAYACAK** |
| Sunucu adresi | Tek build ayarı: `.env` / Capacitor config — **koda dağılmış sabit değil**. İkinci müşteri: ikinci build veya bu değer seçim ekranına çevrilir (bugün sıfır ekstra maliyet) |
| ⚠️ Mimari | Backend **çok kiracılı kalır** (`company_id`, lisans, DataScope). “Tek müşteri” pazarlama/dağıtım kararıdır; çok kiracılığı kaldıran değişiklik **yapılmaz** |
| İleride (backlog) | Kurumsal MDM dağıtımı; müşteriye özel build |

### D2.4 Native yetenekler (Faz 8 — bugün kurulmaz)

Kamera/QR · push (FCM/APNs) · biyometrik giriş · dosya indirme-paylaşma · derin bağlantı (bildirim→ekran) · güvenli alan (çentik) · çevrimdışı önbellek.

Önce **PORTAL-4 PWA** (mağazasız, düşük maliyet); Capacitor + mağaza Faz 8.

---

## E. ERTELENEN (Faz 8 / backlog — bölüm açılmaz)

Şartnamede bilinçli olarak **ana bölüm açılmayan** özellikler:

| Konu | Not |
|---|---|
| Yemekhane / Kantin | Backlog — PDKS/ücret ile entegrasyon potansiyeli |
| Bütçe Simülasyonu | Backlog — Ücret & Analitik üstüne |
| Sendika Yönetimi | Backlog — toplu iş sözleşmesi / üye takibi |
| Araç & Filo | Varlık (B11) altında Faz 8 notu |
| SCORM import | Eğitim LMS (B9) Faz 8 notu |
| Canlı PDKS cihaz protokolü | B4 QR/cihaz kaydı v1; canlı entegrasyon Faz 8 |
| Logo / Netsis / Mikro adaptörleri | Aktarım soyutlaması tasarlanır; adaptör kodu Faz 8 |
| Tam bordro / e-Bildirge | Ücret veri modeli hazırlar; motor ayrı proje |

---

## F. DIŞ SİSTEM ENTEGRASYONLARI

**Amaç:** PDKS puantaj kilidi ve Ücret & Ödemeler kalemlerinin dış bordro / muhasebe sistemlerine aktarımı — kod bugün yazılmaz; modül tasarımı bu soyutlamaya uygun olur.

### Bordro aktarım paketi (standart)

- **Üreticiler:** PDKS (kilitli puantaj kartı), Ücret & Ödemeler (ücret, avans/borç taksit, icra, harcırah, onaylı masraf).
- **Paket içeriği (mantıksal):** dönem, personel anahtarı (sicil/TCKN eşlemesi firma ayarı), gün tipi / süre / mesai, kesinti ve ödeme kalemleri, para birimi, hash/imza meta.
- **Desen:** `PayrollExportPackage` (standart JSON/CSV iç model) → **Adapter** arayüzü (`toLogo()`, `toNetsis()`, `toMikro()`, `toGenericCsv()`).
- **v1:** yalnızca standart paket + generic CSV/Excel indirme.
- **Faz 8:** Logo / Netsis / Mikro adaptörleri + İBYS bildirim adaptörü.
- Ham SQL / vendor’a özel schema sızıntısı domain modellerine yazılmaz; dönüşüm adaptörde kalır.

### Diğer (Faz 8 pazarı)

Takvim (Google/Outlook), SSO (Azure AD/Google), e-imza, canlı PDKS cihaz — ROADMAP Faz 8.

---

## G. KALDIRILAN / BİRLEŞTİRİLEN YAPILAR

| Yapı | Karar |
|---|---|
| `hr-analytics` ayrı satılan modül | B14 Analitik motoruna taşınır |
| Ayrı “Puantaj & Vardiya” + dar PDKS | **B4 PDKS** altında birleşir |
| Ayrı Masraf + Ücret Yönetimi | **B5 Ücret & Ödemeler** altında birleşir |
| Onboarding/Offboarding adı | **B7 Oryantasyon & Çıkış** |
| Organizasyon yalnızca Ayarlar’da | **B1** operasyonel ana modül |
| `application_forms` + `request_types.form_fields` | Tek Form Engine |
| `employee_dashboards` özel widget | Dashboard v2 (B14) ile genelleşir; personel BI path’i kırılmaz |
| `_archive_old_app/` | Repodan çıkar |
| MySQL servisi silme | **YASAK** — legacy korunur; default pgsql |
| Bordro motoru | Kapsam dışı; B5 + aktarım paketi hazırlar |

---

## H. MODÜL → FAZ EŞLEMESİ

| Faz 6 sırası (öneri) | Odak | Gerekçe |
|---|---|---|
| 0. **PORTAL-2a** | Portal Liquid Glass temel malzeme + token | PDKS/LMS/İSG portal ekranları iki kez yapılmasın |
| 1. Navigasyon / org | B1 + menü/rail | Omurga |
| 2. PDKS | B4 | Operasyon + izin yansıması + bordro paketi |
| 3. İzin derinleştirme | B3 | PDKS bağı + TR hakediş UI |
| 4. Ücret & Ödemeler | B5 | Masraf/avans/aktarım |
| 5. Eğitim (LMS) | B9 | İSG eğitim köprüsü önkoşulu |
| 6. İSG | B10 | LMS + org + personel sonrası |
| 7. Kalanlar | B2, B6–B8, B11–B14, A4, A7 | Pilot’a göre |
| 8. **PORTAL-2b → 3 → 4** | Hareket + kalan sayfalar + Bootstrap kaldırma + **PWA** | Modül portal yüzleri oturduktan sonra |

**Faz 8:** Capacitor paketleme + mağaza + push + biyometrik (`MODUL_SPEC` §D2).

**Sürekli (Faz 2–5):** A1 Rol (F2) · A6 Audit (F2) · A5 Bildirim (F4) · C1 Workflow (F4) · B14 Analitik (F5) · A7 KVKK (F6C).

Detaylı paket matrisi: `ROADMAP.md` §6.
