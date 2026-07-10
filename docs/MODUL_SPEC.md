# ALATAX HR — MODÜL ŞARTNAMESİ (MODUL_SPEC)

**Amaç:** Hangi özellik hangi modülde yaşar, her modülün ekranları ve işlemleri (CRUD+) nedir — Cursor'a modül geliştirme promptu verirken referans şartname. ROADMAP Faz 6'nın detay karşılığıdır.

**Gösterim:** C=Create R=Read/List U=Update D=Delete(soft). "+" işaretli olanlar CRUD dışı özel işlemlerdir. 🆕 = yeni eklenen, 🔄 = mevcut ama taşınan/birleşen yapı.

**Her modül için ortak standart (tekrar yazılmaz):** Form Engine'e bağlı formlar · liste sayfasında kayıtlı görünümler + Excel export · izin anahtarları `{modul}.{sayfa}.{aksiyon}` · rapor motoruna dataset kaydı · kritik olaylarda bildirim · tüm yazma işlemleri audit'li · tenant scope.

---

## A. ÇEKİRDEK MODÜLLER (her lisansta)

### A1. Kullanıcı & Rol Yönetimi
**Ekranlar:** Kullanıcılar, Kullanıcı Detay, Roller, Rol Detay (izin matrisi)
**İşlemler:** Kullanıcı CRUD + davet (e-postalı) + import/export + toplu güncelleme + aktif/pasif + şifre sıfırlat + 2FA yönet + oturumları görüntüle/sonlandır + avatar. Rol CRUD + izin matrisi (modül→sayfa→aksiyon) + veri kapsamı (own/team/department/branch/company) + alan izinleri + rol kopyala.
**Not:** `user.type` yalnızca super_admin ayrımı için kalır; firma içi tüm yetki Spatie rollerinden.

### A2. Organizasyon (Firma / Şube / Departman / Pozisyon)
**Ekranlar:** Firma Bilgileri, Şubeler, Departmanlar, 🆕 Pozisyonlar, Organizasyon Şeması
**İşlemler:** Şube CRUD + merkez işaretleme. Departman CRUD (hiyerarşik, yönetici atamalı). 🆕 Pozisyon CRUD (unvan kataloğu; `employees.title` serbest metinden pozisyon referansına evrilir — ücret bantları ve yetkinlikler pozisyona bağlanır). Org şeması görüntüle + PNG/PDF export.

### A3. Personel / Özlük
**Ekranlar:** Personel Listesi, Personel Detay (sekmeler: Genel · İş Bilgileri · Ücret · Evraklar · İzin Özeti · Zimmetler · Eğitimler · Performans · Geçmiş/Audit), Yeni Personel Sihirbazı, 🆕 İşten Çıkış Sihirbazı, Raporlar
**İşlemler:** Personel CRUD + import/export + toplu işlem + portal erişimi aç/kapat + evrak yükle/süre takibi. 🆕 İşten çıkış: çıkış nedeni (SGK kodlu), çıkış checklist'i (zimmet iadesi + evrak + erişim kapatma), ibraname çıktısı.
**TR alan seti:** TCKN (doğrulamalı), SGK sicil, İŞKUR meslek kodu, eğitim durumu, engel oranı, yabancı çalışma izni, BES katılım, acil durum kişisi.
**Sekme kuralı:** İzin/Zimmet/Eğitim/Performans sekmeleri ilgili modül lisanslıysa görünür (cross-module görünüm burada yaşar, veri sahibi ilgili modüldür).

### A4. Self-Servis Portal (personel yüzü)
**Ekranlar (mevcut 13 + eklenecekler):** Dashboard, Profilim, İzinlerim, Belgelerim, Bordrolarım, Eğitimlerim, Performansım, Anketler, Puantajım, Masraflarım, Duyurular, Taleplerim, 🆕 KVKK Onaylarım, 🆕 Zimmetlerim
**Kural:** Portal hiçbir yönetim işlemi içermez; yalnızca kendi verisi + talep başlatma. Her modülün "personel yüzü" bu uygulamada yaşar.

### A5. Duyurular & İç İletişim
**Ekranlar:** Duyurular, Duyuru Detay (okunma takibi)
**İşlemler:** Duyuru CRUD + hedefleme (tüm firma/şube/departman) + yayınla/arşivle + okundu raporu + öne çıkarma.

### A6. Talep & Vaka Yönetimi (İK Helpdesk)
**Ekranlar:** Talep Kuyruğu, Talep Detay, Talep Tipleri, 🆕 SLA Ayarları
**İşlemler:** Talep tipi CRUD (Form Engine'li dinamik form + onay akışı bağlama). Talep aç + ata + yanıtla/yorum + durum değiştir + kapat. 🆕 SLA: tip bazlı hedef süre, gecikme uyarısı, otomatik atama kuralı.
**Durumlar:** open → in_progress → waiting → resolved → closed.

### A7. Bildirim Merkezi
**Ekranlar:** Bildirimler (in-app), Bildirim Tercihlerim (portal+company)
**Yönetim (Ayarlar Stüdyosu'nda):** olay→şablon eşleme, şablon düzenleme (değişkenli), kanal seçimi (in-app/e-posta/SMS), günlük özet.

### A8. Audit & Log
**Ekranlar:** Denetim Kayıtları (global arama/filtre/export), her detay sayfasında "Geçmiş" sekmesi
**İşlemler:** Salt okunur + export. Kapsam: CRUD diff'leri, giriş/çıkış, hassas okuma (bordro), export'lar, izin/rol/ayar değişiklikleri.

### A9. KVKK
**Ekranlar:** Rıza Yönetimi (metin versiyonları + onay durumu), Veri Talepleri (ihraç/silme kuyruğu), Saklama Politikaları, Veri Envanteri Raporu
**İşlemler:** Aydınlatma metni versiyonla + portal ilk girişte onay topla. Veri ihracı üret (JSON/PDF). Silme/anonimleştirme talebi → onay akışı → anonimleştirme job'ı. Saklama süresi CRUD (evrak/log/aday verisi bazında).

---

## B. SATILABİLİR MODÜLLER

### B1. İzin Yönetimi
**Ekranlar:** İzin Talepleri (onay kuyruğu), İzin Takvimi, Bakiyeler, İzin Türleri, Resmi Tatiller, Hakediş Politikaları, Raporlar
**İşlemler:** Tür CRUD (belge şartı, cinsiyet kısıtı, min. bildirim, onay akışı bağlama). Talep C-R-U(iptal) + onayla/reddet + belge yükle. Bakiye görüntüle + 🆕 manuel düzeltme (gerekçeli, audit'li) + devir/hakediş işlemleri (scheduler). Tatil CRUD (yarım gün destekli). Politika CRUD + aylık hakediş çalıştır.
**TR seed:** yıllık izin kıdem kuralları (14/20/26), yasal izin türleri (evlilik 3, babalık 5, vefat 3, doğum 16 hf, süt izni), resmi tatil takvimi.
**Durumlar:** pending → approved / rejected / cancelled (workflow entegre).

### B2. Puantaj & Vardiya
**Ekranlar:** Günlük Yoklama Panosu, Aylık Puantaj Çizelgesi (grid), Vardiyalar, Vardiya Atama Takvimi, Çalışma Takvimleri, 🆕 PDKS Import, Fazla Mesai Onayları, Raporlar
**İşlemler:** Yoklama kaydı CRU + onayla + toplu onay. Vardiya CRUD + personele/departmana atama. 🆕 PDKS import (CSV/Excel şablon + eşleştirme + hata raporu). 🆕 Dönem kapatma (kapanan ay kilitlenir) + bordro-hazır aylık puantaj export'u. Fazla mesai hesap kuralları (firma ayarı).

### B3. İşe Alım
**Ekranlar:** Pozisyon İlanları, Başvurular (🆕 Kanban + liste), Aday Detay, CV Havuzu, Mülakatlar (takvim), 🆕 Teklifler, Başvuru Form Builder 🔄(Form Engine'e bağlanır), Kariyer Sayfası Ayarları, Raporlar
**İşlemler:** İlan CRUD + yayınla/kapat + public link. Başvuru durum akışı (sürükle-bırak) + not/puanlama + CV havuzuna al. Mülakat planla + scorecard doldur. 🆕 Teklif oluştur/gönder/sonuç kaydet. Kaynak (source) yönetimi. Public başvuru (mevcut) + KVKK aday rızası.
**Durumlar:** new → screening → interview → offer → hired / rejected / pool.

### B4. Onboarding / Offboarding
**Ekranlar:** Şablonlar 🔄(kayıp route eklenir), Aktif Süreçler, Süreç Detay (görevler + milestone'lar), Preboarding, Buddy Yönetimi
**İşlemler:** Şablon CRUD (görev listesi + sorumlu rolleri + gün ofsetleri). Süreç başlat (işe alımdan otomatik tetiklenebilir — workflow) + görev tamamla/ata + milestone takip. Preboarding token linki (evrak ön toplama). Buddy ata. Offboarding checklist'i A3'teki çıkış sihirbazıyla ortak motoru kullanır.

### B5. Performans
**Ekranlar:** Dönemler 🔄(kayıp route), Kriterler & Yetkinlikler 🔄(kayıp route), Değerlendirmeler, Değerlendirme Detay, Hedefler/OKR, 360° Geri Bildirim, 1:1 Görüşmeler, Raporlar
**İşlemler:** Dönem CRUD + başlat/kapat (🆕 dönem sihirbazı: kapsam + kriter seti + takvim). Kriter/yetkinlik CRUD + pozisyona bağlama. Değerlendirme ata/doldur/onayla + skor hesaplama. Hedef (objective) CRUD + key result ilerleme güncelleme. 360: geri bildirim sağlayıcı davet + anonim yanıt. 1:1 planla + gündem/not/aksiyon.

### B6. Eğitim
**Ekranlar:** Eğitim Kataloğu, Eğitim Detay, Oturumlar 🔄(kayıp route), Katılımcılar, Sertifikalar, 🆕 Zorunlu Eğitimler, Eğitim Talepleri, Öğrenme Yolları, Raporlar
**İşlemler:** Eğitim CRUD + oturum CRUD (tarih/eğitmen/kontenjan). Katılımcı ekle + devam/sonuç işaretle + sertifika üret (geçerlilik süreli, süresi yaklaşınca bildirim). 🆕 Zorunlu eğitim atama (pozisyon/departman bazlı) + tamamlama takibi + hatırlatma (workflow). Talep aç → onay akışı. Öğrenme yolu CRUD + personele atama.

### B7. Varlık / Zimmet
**Ekranlar:** Varlıklar, Varlık Detay, Kategoriler 🔄(kayıp route), Zimmetler 🔄(kayıp route), Bakım, Yazılım Lisansları, Varlık Talepleri
**İşlemler:** Kategori CRUD. Varlık CRUD + durum (stokta/zimmetli/bakımda/hurda). Zimmet ver/iade + 🆕 imza alanlı zimmet tutanağı PDF. Bakım kaydı CRU + maliyet. Yazılım lisansı CRUD + koltuk atama. Talep aç → onay → zimmetle.

### B8. Masraf
**Ekranlar:** 🆕 Onay Kuyruğu (Company), 🆕 Tüm Talepler (Company), 🆕 Kategoriler & Limitler (Company), Masraflarım (Portal — mevcut), Raporlar
**İşlemler:** Kategori CRUD + 🆕 limit (tutar/ay, kişi/rol bazlı). Talep oluştur + kalem ekle + fiş yükle + gönder. Onayla/reddet + 🆕 "ödendi" işaretle (ödeme referanslı).
**Durumlar:** draft → submitted → approved / rejected → paid.

### B9. Anket & eNPS
**Ekranlar:** Anketler, Anket Builder, Sonuçlar & Analiz, 🆕 eNPS Trendi
**İşlemler:** Anket CRUD (soru tipleri: çoktan seçmeli, ölçek, açık uç, eNPS) + hedef kitle + yayınla/kapat + hatırlatma. Yanıt toplama (🆕 anonimlik garantisi: anonim ankette yanıt-kimlik ilişkisi hiç yazılmaz). Sonuç görüntüle + export + eNPS trend grafiği.

### B10. Doküman+ (Gelişmiş Evrak)
**Ekranlar:** Doküman Kütüphanesi, Kategoriler, 🆕 Zorunlu Evrak Setleri, 🆕 Süre Takibi, Onay Bekleyenler, Raporlar
**İşlemler:** Kategori CRUD. Doküman yükle/indir/paylaş + versiyonlama + onay akışı. 🆕 Zorunlu set tanımla (işe girişte istenen evraklar) + personel bazlı eksik takibi. 🆕 Süreli evrak takibi (sertifika, sağlık raporu) + bitiş uyarısı.
**Not:** Temel personel evrakı A3'te ücretsizdir; versiyonlama/onay/zorunlu set/süre takibi bu modülün lisansındadır.

### B11. Ücret Yönetimi 🆕 (bordro DEĞİL)
**Ekranlar:** Ücret Bantları, Personel Ücret Geçmişi, Zam Dönemleri, Toplam Gelir Görünümü
**İşlemler:** Bant CRUD (pozisyona bağlı min/orta/max). Ücret değişikliği kaydet (efektif tarihli, gerekçeli — mevcut maaş alanları geçmiş tablosuna evrilir). Zam dönemi oluştur + toplu öneri + onay akışı + uygula. Toplam gelir görünümü (maaş + yan hak kalemleri).
**Alan izni kritik:** ücret verisi varsayılan olarak yalnızca `salary.view` iznine açıktır.

---

## C. PREMIUM KATMAN

### C1. Rapor Builder (Self-Servis BI)
**Ekranlar:** Raporlarım, Rapor Builder (3 panel), Zamanlanmış Raporlar, Dashboard'lar
**İşlemler:** Rapor CRUD (dataset seç + kolon/filtre/grup/grafik) + paylaş (rol/kişi) + export + zamanla (cron + e-posta). Dashboard CRUD + widget ekle (kayıtlı rapor). Hazır raporlar kopyalanıp özelleştirilebilir.
**Not:** Modüllerle gelen hazır raporlar çekirdektedir; "kendin kur" bu lisanstadır. 🔄 `hr-analytics` sayfaları bu motorun üstüne taşınır (ayrı modül olarak kalkar).

### C2. Workflow Otomasyonu
**Ekranlar:** Akış Listesi, Akış Builder (tetikleyici→koşul→aksiyon), Onay Delegasyonları, Çalıştırma Logları
**İşlemler:** Akış CRUD + aktif/pasif + test çalıştır. Delegasyon CRUD (tarih aralıklı). Log görüntüleme.
**Not:** Modüllerin varsayılan onay akışları (izin onayı vb.) çekirdektedir; özel akış kurma bu lisanstadır.

### C3. API & Webhook
**Ekranlar:** API Anahtarları (mevcut), Webhooks (mevcut) + log görüntüleme
**İşlemler:** Mevcut CRUD korunur + izin kapsamlı anahtar (anahtar bazlı permission seti) + API dokümantasyon sayfası.

---

## D. PLATFORM (modül değil, altyapı)

**Ayarlar Stüdyosu sekmeleri:** Firma & Şubeler · Modüller & Lisans · Formlar & Alanlar (Form Engine) · Liste Görünümleri · İş Akışları · Bildirim Şablonları · Roller & İzinler · İzin/Tatil Politikaları · Görünüm (tema/density/logo) · API & Webhook · Veri (import/export/KVKK).

**SuperAdmin (yalnızca cloud):** mevcut 7 sayfa korunur (Firmalar, Paketler, Modüller, Kullanıcılar, Cari, Loglar, Dashboard) + 🆕 firma bazlı kullanım metrikleri.

---

## E. KALDIRILAN / BİRLEŞTİRİLEN YAPILAR

| Yapı | Karar |
|---|---|
| `hr-analytics` ayrı modülü | Rapor motoruna taşınır; ayrı satılan modül olmaktan çıkar (hazır raporlar çekirdeğe, builder premium'a) |
| `application_forms` form builder + `request_types.form_fields` | İki ayrı form altyapısı → tek Form Engine |
| `employee_dashboards` özel widget sistemi | Dashboard v2'ye (rapor motoru widget'ları) evrilir |
| `_archive_old_app/` | Repodan çıkar |
| react-hook-form/zod "ölü" bağımlılıklar | Ölü değil, Form Engine'in temeli olarak benimsenir |
| MySQL/SQLite desteği | Kaldırılır — yalnızca PostgreSQL |
| Bordro | Kapsam dışı (Faz 8 sonrası ayrı proje); B11 veri modelini hazırlar |

---

## F. MODÜL → FAZ EŞLEMESİ

| Faz 6A (pilot) | Faz 6B | Sürekli (Faz 2-5'te doğar) |
|---|---|---|
| A3 Personel + çıkış · B1 İzin · B2 Puantaj · B8 Masraf · B10 Doküman+ | B5 Performans · B3 İşe Alım · B4 Onboarding · B6 Eğitim · B7 Varlık · B9 Anket · B11 Ücret · A6 SLA | A1 Kullanıcı/Rol (F2) · A8 Audit (F2) · A7 Bildirim (F4) · C2 Workflow (F4) · C1 Rapor (F5) · A9 KVKK (F6C) |
