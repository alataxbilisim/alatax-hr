# ALATAX HR — UÇTAN UCA ÇALIŞMA MANTIĞI (SISTEM_ISLEYIS)

**Amaç:** Programın bütününün nasıl çalıştığını, bir çalışanın **işe alım öncesinden çıkışına kadarki tüm yaşam döngüsü** boyunca hangi modüllerin nasıl, ne zaman, kim tarafından devreye girdiğini uçtan uca anlatır. Diğer dosyalar parçaları anlatır; bu dosya **bütünü** anlatır ve hepsini birbirine bağlar.

**Nasıl okunmalı:** Bu bir hikaye/senaryo belgesidir. Bir firmanın (tenant) sisteme girişinden başlar, bir çalışanın tüm hayat döngüsünü izler, günlük operasyonu ve yönetim/platform katmanını kapsar. Her aşamada devreye giren modül **[Mn]** ile MODUL_SPEC'e, akış **AKIS §n** ile AKIS_SPEC'e bağlanır.

**Belge haritası (hangi soru hangi dosyada):**
| Soru | Dosya |
|---|---|
| Sistem uçtan uca nasıl işler? | **bu dosya** |
| Company + Portal görsel akış şeması (düzenlenebilir) | [AKIS_SEMA_EDITOR.html](AKIS_SEMA_EDITOR.html) |
| Ne zaman, hangi sırayla geliştirilir? | ROADMAP.md |
| Hangi modülde hangi ekran/işlem var? | MODUL_SPEC.md |
| Bir işlem başlayınca ne olur (akış)? | AKIS_SPEC.md |
| Ekranlar nasıl görünür (boyut/tasarım)? | TASARIM_REHBERI.md |
| Kod nasıl yazılır (konvansiyon)? | cursor-rules.md |

---

## 0. SİSTEMİN İKİ DÜNYASI

Program üç arayüz + bir motor katmanından oluşur:

- **SuperAdmin (cloud):** Platform sahibi. Firmaları, lisansları, modülleri, cari hesabı yönetir. On-prem'de gizlidir (lisans dosyası bu rolün yerine geçer).
- **Company (yönetim):** Firmanın İK'sı ve yöneticileri. Kurulum, tanım, onay, rapor — işin beyni burası.
- **Portal (personel):** Çalışanın kendi yüzü. Kendi verisi, talepleri, görevleri.
- **Platform motorları (görünmez):** Form Engine, RBAC, Audit, Workflow, Bildirim, Rapor — her modülün altında çalışır. Kullanıcı bunları "modül" olarak görmez; her yerde vardır.

Aşağıdaki bölümler kronolojik: firma kurulumu → çalışan yaşam döngüsü → günlük operasyon → yönetim/platform → çıkış.

---

## AŞAMA 1 — FİRMANIN SİSTEME GİRİŞİ (Tenant Kurulumu)

**Cloud senaryosu:** Firma kayıt olur (`register`) → 14 günlük trial + starter paket + çekirdek modüller otomatik açılır → firma admin'i oluşur (Spatie "admin" rolü). SuperAdmin dilerse paketi/modülleri/limiti değiştirir, lisansı uzatır, cari işler. **[SuperAdmin, M-A1/A2]**

**On-prem senaryosu:** `install.sh` + imzalı lisans dosyası → tek firma otomatik kurulur, SuperAdmin gizli, modül/limit lisanstan okunur. Kod aynı; yalnızca `APP_MODE` farklı. (ROADMAP Faz 7)

**İlk konfigürasyon (firma admin, Ayarlar Stüdyosu):**
1. Firma bilgileri, şubeler, departmanlar, pozisyon kataloğu **[M-A2]**
2. Roller ve izinler: kim neyi görür/yapar; veri kapsamı (own/team/department/branch/company); alan izinleri (kim maaşı görür) **[M-A1, RBAC]**
3. Modül seçimi: hangi modüller aktif (lisansa göre) **[M platform]**
4. Modül parametreleri: izin türleri + politikalar, masraf kategorileri + limitler, talep tipleri, onay akışları, bildirim şablonları — hepsi **Ayarlar Stüdyosu'ndan** **[Form Engine + Workflow + Bildirim]**
5. Formların özelleştirilmesi: personel formuna firma-özel alanlar (Zoho mantığı), gereksiz alanları gizleme, liste görünümleri **[Form Engine]**
6. Görünüm: tema, yoğunluk (density), logo **[TASARIM_REHBERI]**

> **Kilit fikir:** Firma kod yazmadan, tamamen Ayarlar Stüdyosu'ndan kendine göre şekillenir. Akış mekanizması sabit, içerik firmaya özel.

---

## AŞAMA 2 — İŞE ALIM (Çalışan henüz "aday")

Çalışan yaşam döngüsü aslında **işe alınmadan önce** başlar. **[M-B3, AKIS §3]**

1. İK pozisyon ilanı açar → public kariyer sayfasında yayınlanır.
2. Aday firma slug'lı sayfadan başvurur → **KVKK aday rızası** alınır **[M-A9]** → başvuru `new`.
3. İK Kanban'da adayı ilerletir: `screening → interview → offer`. Mülakat planlanır, mülakatçıya bildirim gider, scorecard doldurulur, teklif oluşturulur.
4. Aday `hired` olur → **otomatik zincir tetiklenir** (AKIS §13):
   - Onboarding süreci başlar **[M-B4]**
   - Aday verisinden **Personel kaydı ön-doldurulur** (çift veri girişi yok) **[M-A3]**
   - Portal erişimi hazırlanır **[M-A1]**

---

## AŞAMA 3 — İŞE BAŞLAMA (Onboarding)

Aday artık "çalışan". **[M-B4, AKIS §4]**

1. **Preboarding:** başlamadan önce token'lı link → evrak/form ön toplama (portal hesabı henüz yokken).
2. **Onboarding süreci:** şablondan görevler oluşur, sorumlulara (İK, yönetici, yeni çalışan) gün ofsetleriyle atanır.
3. **Personel dosyası tamamlanır [M-A3]:** TR alan seti (TCKN doğrulamalı, SGK sicil, İŞKUR kodu, eğitim, engel oranı, BES...), acil durum kişisi, banka/iletişim. Firma-özel alanlar Form Engine'den gelir.
4. **Zorunlu evrak [M-B10]:** işe giriş evrak seti çalışana atanır → çalışan **portaldan** yükler → İK onaylar → eksik takibi.
5. **KVKK aydınlatma [M-A9]:** çalışan portal ilk girişte aydınlatma metnini onaylar; rıza kaydı tutulur.
6. **Zimmet [M-B7]:** laptop/telefon/araç zimmetlenir → imzalı tutanak → çalışanın "Zimmetlerim"ine düşer.
7. **Zorunlu eğitim [M-B6]:** pozisyona bağlı eğitimler (iş güvenliği vb.) otomatik atanır → portalda "yapılacak eğitim".
8. **İzin bakiyesi [M-B1]:** işe giriş tarihine göre hakediş başlar (politikaya göre).
9. **Ücret [M-B11]:** başlangıç ücreti efektif tarihli kaydedilir (yüksek gizlilik, alan izni).

Onboarding milestone'ları tamamlandıkça İK izler; çalışan kendi görevlerini portalda bitirir.

---

## AŞAMA 4 — GÜNLÜK OPERASYON (Aktif çalışma dönemi)

Çalışanın rutin hayatı — hem portal hem yönetim tarafı sürekli etkileşimde.

**Çalışanın portaldaki günü [Portal]:**
- **Giriş/çıkış [M-B2, AKIS §2]:** check-in/out veya PDKS. İzinli/tatil günler otomatik yansır.
- **İzin talebi [M-B1, AKIS §1]:** portal → bakiye/çakışma kontrolü → yöneticiye düşer → onay → takvim + puantaj + bakiye güncellenir → sonuç bildirimi. (Senin bildiğin akış.)
- **Masraf talebi [M-B8, AKIS §8]:** çok kalemli + fiş → limit kontrolü → onay → "ödendi".
- **Eğitim talebi / zorunlu eğitim [M-B6]:** talep açar veya atanmış eğitimi tamamlar → sertifika.
- **Genel talep [M-A6/12]:** izin/masraf dışı her şey (belge talebi, bilgi güncelleme) → İK helpdesk → SLA'lı.
- **Bilgi görüntüleme:** bordro (ileride), zimmet, sertifika, izin bakiyesi, duyurular, kendi performansı.

**Yöneticinin/İK'nın günü [Company]:**
- Onay kuyrukları (izin/masraf/eğitim/zimmet/genel talep) — tek tek veya toplu onay.
- Ekip takvimi (kim izinde), puantaj takibi, düzeltme talepleri.
- Duyuru yayınlama **[M-A5]**, personel kayıt güncelleme, bakiye düzeltme.

**Arka planda sürekli çalışanlar (zamanlanmış — Scheduler):**
- Aylık izin hakedişi + devir işlemleri **[M-B1]**
- Süreli evrak/sertifika bitiş uyarıları **[M-B10/B6]**
- Deneme süresi/doğum günü/yıldönümü hatırlatmaları
- SLA gecikme eskalasyonları **[Workflow]**
- Zamanlanmış raporların e-posta ile gönderimi **[M-C1]**

---

## AŞAMA 5 — GELİŞİM VE DÖNEMSEL SÜREÇLER

Yıl içinde tekrar eden yönetim süreçleri.

- **Performans [M-B5, AKIS §5]:** İK dönem açar → öz-değerlendirme (çalışan portalda) + yönetici + 360° → skor → çalışanla paylaşım → çalışan portalda görür/imzalar. OKR'ları çalışan portaldan günceller. 1:1 görüşmeler.
- **Eğitim & kariyer [M-B6]:** öğrenme yolları, gelişim eğitimleri.
- **Ücret yönetimi [M-B11]:** zam dönemi → bant/performansa göre öneri → onay → ücret geçmişine işlenir. (Performans verisi zam gerekçesine bağlanır.)
- **Anket & eNPS [M-B9, AKIS §9]:** İK anket açar → çalışan portalda anonim yanıtlar → sonuç/trend analizi.
- **Duyurular & iletişim [M-A5]:** hedefli duyurular, okunma takibi.

---

## AŞAMA 6 — İZLEME, RAPORLAMA, YÖNETİM (Platform katmanı, her an)

Yaşam döngüsünün üstünde, sürekli açık olan yönetim gözü.

- **Raporlama [M-C1]:** İK hazır raporları kullanır veya Rapor Builder ile sıfırdan rapor kurar (dataset seç → kolon/filtre/grup/grafik → kaydet → paylaş → zamanla). Custom field'lar otomatik raporlanabilir. Dashboard'lara widget olarak eklenir. Yetkisiz kullanıcı yetkisiz veriyi raporda da göremez (RBAC + alan izni rapora da uygulanır).
- **Audit [M-A8]:** her işlem (kim, ne zaman, hangi kaydın hangi alanı, eski→yeni, IP) loglanır. Her detay sayfasında "Geçmiş" sekmesi. Hassas okuma (bordro/ücret görüntüleme) ve export'lar da loglanır.
- **Özelleştirme [Form Engine, her an]:** firma ihtiyaç değiştikçe alan ekler/gizler, form/liste/akış/bildirim düzenler — koda dokunmadan.
- **Workflow otomasyonu [M-C2]:** "X olursa Y yap" kuralları (bildirim gönder, alan güncelle, onay başlat, webhook çağır).
- **Entegrasyon [M-C3]:** API key + webhook ile dış sistemler (bordro, muhasebe, PDKS — ileride).

---

## AŞAMA 7 — İŞTEN ÇIKIŞ (Offboarding)

Yaşam döngüsünün kapanışı. **[M-A3 çıkış sihirbazı + M-B4 offboarding, AKIS §4/§7]**

1. İK çıkış sihirbazını başlatır → çıkış nedeni (SGK kodlu), tarih.
2. **Offboarding görevleri** otomatik oluşur (aynı görev motoru):
   - **Zimmet iadesi [M-B7]:** açık zimmetler iade görevine bağlanır; iade tamamlanmadan çıkış kapanmaz.
   - **Erişim kapatma [M-A1]:** portal + sistem erişimi.
   - **Evrak & ibraname:** çıkış evrakları, ibraname çıktısı.
   - **Kalan izin [M-B1 → M-B11]:** kullanılmayan izin bakiyesi "izin karşılığı ücret" olarak Ücret modülüne geçer.
3. Tüm görevler tamamlanınca çıkış onaylanır → çalışan `passive`.
4. **KVKK saklama [M-A9]:** saklama süresi dolunca veri anonimleştirme job'ı devreye girer (audit hariç — o immutable).

---

## AŞAMA 8 — VERİNİN SÜREKLİLİĞİ (Çalışan gitse de sistem hatırlar)

- Audit kayıtları kalıcı (immutable).
- Aday havuzu, eski çalışan verisi KVKK saklama politikasına tabi.
- Raporlar geçmişi kapsar (turnover, ortalama kıdem...).
- İşe geri alım: eski kayıt referans alınabilir.

---

## ÖZET: TEK CÜMLEYLE SİSTEM

> Firma sisteme girer ve **Ayarlar Stüdyosu'ndan kendine göre şekillenir** (Aşama 1); çalışan **aday olarak başlar** (2), **onboarding'le sisteme dahil olur** (3), **portal ve yönetim tarafı sürekli etkileşimle günlük hayatı yaşar** (4), **dönemsel süreçlerle gelişir** (5), tüm bunlar **RBAC, Audit, Workflow, Bildirim ve Rapor motorlarının görünmez katmanında** işler (6), ve çalışan **offboarding'le sistematik olarak çıkar** (7), ama **verisi KVKK sınırında sistemde yaşamaya devam eder** (8) — hepsi **cloud veya on-prem, tek kod tabanıyla**, **modül modül lisanslanarak**.

---

## GELİŞTİRME SIRASINA BAĞLANTI

Bu yaşam döngüsü **hepsi birden** çalışmaz; ROADMAP fazlarıyla parça parça ayağa kalkar:
- Önce görünmez motorlar (RBAC, Audit — Faz 2; Form Engine, Workflow, Bildirim — Faz 4; Rapor — Faz 5)
- Sonra Aşama 4'ün çekirdeği (İzin, Puantaj, Masraf — Faz 6A pilot)
- Sonra Aşama 2/3/5 modülleri (İşe Alım, Onboarding, Performans, Eğitim — Faz 6B)
- En son on-prem paketleme + lisans (Faz 7)

Yani bu belge **hedef resmi**; ROADMAP o resme **nasıl ulaşılacağını** söyler.
