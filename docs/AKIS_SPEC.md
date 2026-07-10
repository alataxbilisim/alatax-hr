# ALATAX HR — ÇALIŞMA MANTIĞI & AKIŞLAR (AKIS_SPEC)

**Amaç:** Modüllerin *nasıl çalıştığını* — portal (personel) ile yönetim (İK/yönetici) tarafı arasındaki uçtan uca olay akışlarını — tanımlar. MODUL_SPEC "hangi ekran/CRUD" der; bu dosya "bir işlem başlayınca ne olur, kim tetiklenir, veriye/bakiyeye ne olur, kime bildirim gider, sonra ne olur" der.

**Sınır:** Buradaki akışlar sistemin *mekanizmasıdır* (sabittir). İçerik (izin türleri, onay kademeleri, kategori limitleri, form alanları) her firma tarafından Ayarlar Stüdyosu'ndan doldurulur. Yani akış motoru sabit, parametreler firmaya özel.

**Referanslar:** ROADMAP (faz sırası) · MODUL_SPEC (kapsam) · Workflow Engine = ROADMAP Faz 4B · Bildirim Merkezi = Faz 4C.

---

## 0. ORTAK AKIŞ İSKELETİ — "Talep → Onay → Sonuç"

Aşağıdaki modüllerin neredeyse tamamı bu tek iskeleti paylaşır; farklar sadece **parametrelerdedir**. Bu bölüm bir kez okunur, sonra her modülde "standart talep akışı geçerli, farkı şu" denir.

**Roller:** *Talep sahibi* (portalda başlatan personel) · *Onaycı(lar)* (workflow'un belirlediği) · *İzleyici* (İK, opsiyonel bilgilendirilen).

**Durum makinesi (genel):**
```
draft → submitted → (in_review) → approved → [uygulanır]
                          ├→ rejected  (gerekçe zorunlu)
                          ├→ returned  (revizyona iade, talep sahibine döner)
                          └→ cancelled (talep sahibi geri çeker; sadece approved öncesi)
```

**Adım adım akış:**
1. **Başlatma (portal):** Personel formu doldurur. Form, Form Engine'den gelir; alanlar + validasyon firma tanımına göre. Taslak kaydedilebilir (`draft`).
2. **Gönderim:** Personel gönderir → durum `submitted`. Sistem *ön kontrolleri* çalıştırır (bakiye/limit/çakışma/zorunlu evrak — modüle göre). Kontrol başarısızsa gönderim engellenir, personele neden gösterilir.
3. **Kaynak bloke:** Varsa ilgili kaynak rezerve edilir (izin bakiyesi `pending`, masraf bütçesi vb.) — onay öncesi çift kullanımı önler.
4. **Onay akışı devreye girer:** Talep tipine bağlı workflow bulunur. Yoksa **varsayılan kural:** talep sahibinin doğrudan yöneticisi (departman/employee manager) tek onaycıdır. Workflow çok adımlıysa (koşullu: "X günden fazlaysa üst yönetici de") adımlar sırayla ilerler.
5. **Bildirim (istek):** Sıradaki onaycıya in-app + (tercihse) e-posta/SMS gider. Onaycı yoksa/pasifse delegasyon veya İK'ya eskalasyon.
6. **Onaycı aksiyonu:** Onaycı kendi onay kuyruğunda görür → **onayla / reddet / iade / atla(skip)**. Reddet ve iade'de gerekçe zorunlu. Delege edilmişse delege eden adına işlem yapılır (audit'e ikisi de yazılır).
7. **Adım geçişi:** Onay çok adımlıysa bir sonraki onaycı tetiklenir (2'ye dön). Son adım onaylanınca durum `approved`.
8. **Uygulama (side-effect):** Onay tamamlanınca modülün gerçek etkisi işlenir (bakiye düş, takvime yaz, zimmeti ata, "ödendi" bekleyene al...). Bu adım idempotent ve audit'li.
9. **Sonuç bildirimi:** Talep sahibine sonuç (onay/ret + gerekçe) bildirilir. İzleyiciler bilgilendirilir.
10. **Kapanış:** Kayıt son durumda kilitlenir; tüm adımlar (kim, ne zaman, hangi aksiyon, gerekçe) `approval_records` + audit'te. Personel portalda durumu ve geçmişi izler.

**Ortak kurallar:**
- **Geri çekme:** Talep sahibi yalnızca `approved` olmadan `cancelled` yapabilir. Sonrası "iptal talebi" (yeni akış) gerektirir.
- **Çakışma/yarış:** Kaynak bloğu sayesinde iki eşzamanlı onay çift düşüm yapamaz (son yazan kazanır + kontrol).
- **Delegasyon:** Onaycı tarih aralıklı vekil atayabilir; o aralıkta bildirim + yetki vekile geçer.
- **Eskalasyon:** Onaycı N gün (firma ayarı) işlem yapmazsa hatırlatma → sonra üst kademeye/İK'ya bildirim (Workflow zamanlanmış tetikleyici).
- **SLA/süre:** Talep tipine hedef süre tanımlıysa geciken talep işaretlenir.
- **Audit:** Her durum değişikliği, her bildirim, her kaynak hareketi loglanır.

---

## 1. İZİN YÖNETİMİ

**Portal başlangıçlı — standart talep akışı geçerli.** Farkları:

- **Ön kontroller (adım 2):** (a) bakiye yeterli mi (talep günü ≤ kullanılabilir bakiye), (b) tarih aralığı başka onaylı/bekleyen izinle çakışıyor mu, (c) tür belge istiyorsa ek dosya, (d) min. bildirim süresi (örn. 3 gün önce), (e) cinsiyet/kıdem kısıtı.
- **Gün hesabı:** Talep günü = iş günü (hafta sonu + resmi tatil düşülür; firma "takvim günü" seçebilir). Yarım gün destekli. Hesap `LeaveCalculation` servisinde tek yerde.
- **Kaynak bloke (adım 3):** `leave_balances.pending_days` artırılır.
- **Uygulama (adım 8):** onayda `pending_days` → `used_days`; izin takvimine + ekip takvimine yazılır; (varsa) puantaja "izinli" olarak işlenir.
- **Ret/iptal:** `pending_days` geri çözülür, bakiye eski haline.
- **Modüller arası:** Onaylı izin → İzin Takvimi + Puantaj (izinli gün) + (yöneticinin) Ekip Takvimi. İşten çıkışta (Personel) kalan bakiye → Ücret modülüne "izin karşılığı" verisi olarak geçer.
- **Portal görünümü:** personel kendi bakiyesini (tür bazında kalan/kullanılan/bekleyen), talep geçmişini, ekip izin takvimini (yetkiliyse) görür.

---

## 2. PUANTAJ & VARDİYA

**Karma: hem otomatik akış hem talep.**

- **Giriş-çıkış:** Personel portaldan check-in/out (veya PDKS import) → günlük kayıt oluşur. Eksik/anormal kayıt (giriş var çıkış yok, geç kalma) işaretlenir.
- **Kaynak birleştirme:** Onaylı izinler (Modül 1) ve resmi tatiller puantaja otomatik yansır; personel o günleri tekrar girmez.
- **Düzeltme talebi (portal başlangıçlı — standart akış):** personel "unuttum/yanlış" düzeltme talebi açar → yönetici onayı → kayıt güncellenir.
- **Fazla mesai:** kural eşiği aşılınca fazla mesai önerisi üretilir → onaya düşer → onaylanınca aylık puantaja işlenir.
- **Dönem kapatma (yönetim başlangıçlı):** İK ayı kapatır → o ay kilitlenir (artık düzeltme talebi yeni akış gerektirir) → bordro-hazır puantaj export'u üretilir → (ileride) Bordro modülüne girdi.

---

## 3. İŞE ALIM

**Public + yönetim başlangıçlı; personel portalı devrede değil (aday dış kullanıcı).**

- **Aday akışı (durum makinesi):** `new → screening → interview → offer → hired / rejected / pool`. Sürükle-bırak Kanban ile ilerletilir; her geçiş audit'li.
- **Public başvuru:** Aday firma kariyer sayfasından başvurur (KVKK aday rızası alınır) → başvuru `new`.
- **Mülakat:** planlanır → ilgili mülakatçılara bildirim + takvim → scorecard doldurulur → skor aday kartında.
- **Teklif:** `offer` aşamasında teklif oluşturulur/gönderilir → sonuç kaydedilir.
- **Modüller arası (kritik zincir):** Aday `hired` olunca → **Onboarding süreci otomatik tetiklenir** (Workflow) + aday verisinden **Personel kaydı** ön-doldurulur (çift veri girişi yok) + (opsiyonel) portal erişimi hazırlanır.
- **Havuz:** reddedilen/uygun aday `pool`'a → sonraki ilanlarda aranabilir (KVKK saklama süresine tabi).

---

## 4. ONBOARDING / OFFBOARDING

**Yönetim/otomatik başlangıçlı; personel portalda görev tamamlar.**

- **Tetikleme:** İşe alımdan otomatik (aday `hired`) veya manuel. Şablondan süreç örneklenir; görevler sorumlulara (İK/yönetici/yeni personel) + gün ofsetleriyle atanır.
- **Görev akışı:** her görev bir sorumluya düşer → bildirim → tamamlanınca işaretlenir → milestone'lar ilerler. Yeni personel kendi görevlerini (evrak yükleme, form doldurma) **portalda** yapar.
- **Preboarding:** işe başlamadan önce token'lı link ile evrak/form ön toplama (portal hesabı henüz yokken).
- **Offboarding:** çıkış sihirbazı (Personel modülü) aynı görev motorunu kullanır → zimmet iadesi (Modül 7), erişim kapatma (Kullanıcı), evrak, ibraname; her adım tamamlanınca çıkış onaylanır.

---

## 5. PERFORMANS

**Yönetim başlangıçlı; personel portalda katılır.**

- **Dönem akışı:** İK dönem açar (kapsam + kriter/yetkinlik seti + takvim) → değerlendirmeler ilgili taraflara atanır.
- **Değerlendirme:** öz-değerlendirme (personel portalda) + yönetici değerlendirmesi + (varsa) 360° → skorlar hesaplanır → yönetici onayı → personelle paylaşılır → personel görür/imzalar (portal).
- **OKR:** hedef atanır → personel key-result ilerlemesini portaldan günceller → yönetici izler.
- **360°:** geri bildirim sağlayıcılar davet edilir → anonim yanıt toplanır (anonimlik garantili) → rapor oluşur.
- **1:1:** yönetici/personel görüşme planlar → gündem/not/aksiyon; aksiyonlar takip edilir.

---

## 6. EĞİTİM

**Karma.**

- **Katalog & oturum:** İK eğitim/oturum açar (tarih/eğitmen/kontenjan).
- **Talep (portal başlangıçlı — standart akış):** personel eğitim talebi açar → yönetici/İK onayı → onaylanınca katılımcı listesine eklenir.
- **Zorunlu eğitim (otomatik):** pozisyon/departman bazlı atama → personele bildirim + portalda "yapılacak eğitim" → tamamlanınca işaretlenir; süresi dolan sertifika için yeniden atama (Workflow zamanlanmış).
- **Sertifika:** tamamlamada üretilir (geçerlilik süreli) → süre yaklaşınca personele + İK'ya uyarı.
- **Öğrenme yolu:** sıralı eğitim seti atanır → personel portaldan ilerler.

---

## 7. VARLIK / ZİMMET

**Karma.**

- **Zimmet verme (yönetim başlangıçlı):** İK varlığı personele zimmetler → imza alanlı tutanak (PDF) → varlık durumu `zimmetli` → personelin "Zimmetlerim" (portal) listesine düşer.
- **Zimmet talebi (portal başlangıçlı — standart akış):** personel varlık talep eder → onay → uygun varlık zimmetlenir.
- **İade:** personel iade başlatır veya offboarding tetikler → İK teslim alır → durum `stokta` → tutanak güncellenir.
- **Bakım:** varlık `bakımda` → maliyet/kayıt → tekrar `stokta`.
- **Modüller arası:** Offboarding (Modül 4) açık zimmetleri iade görevine bağlar; iade tamamlanmadan çıkış kapanmaz.

---

## 8. MASRAF

**Portal başlangıçlı — standart talep akışı geçerli.** Farkları:

- **Ön kontrol:** kategori limiti (tutar/ay, rol/kişi bazlı) aşılıyor mu; fiş zorunluysa ek.
- **Form:** talep + çok kalemli (her kalem: kategori/tutar/tarih/fiş) → toplam hesaplanır.
- **Durum makinesi:** `draft → submitted → approved/rejected → paid`. Onay sonrası **"ödendi" adımı** ayrıdır (finans/İK ödeme referansıyla işaretler).
- **Portal:** personel taleplerini + durumlarını + ödeme durumunu izler.
- **Modüller arası (ileride):** onaylı+ödenmiş masraf → Bordro/muhasebe entegrasyonuna girdi.

---

## 9. ANKET & eNPS

**Yönetim başlangıçlı; personel portalda yanıtlar.**

- **Akış:** İK anket oluşturur (sorular + hedef kitle) → yayınlar → hedef personele bildirim + portalda "yanıtla" → hatırlatma (yanıtlamayanlara) → kapanışta sonuç/analiz.
- **Anonimlik:** anonim ankette yanıt-kimlik ilişkisi hiç yazılmaz (yalnızca "yanıtladı mı" bilgisi ayrı tutulur, içerik anonim).
- **eNPS:** periyodik gönderim → skor + trend.

---

## 10. DOKÜMAN+

**Karma.**

- **Kütüphane:** İK doküman yükler → kategori/görünürlük → versiyonlama; onay gerekiyorsa standart akış.
- **Zorunlu evrak seti:** işe girişte istenen evraklar tanımlanır → personel portaldan yükler → İK onaylar → eksik takibi.
- **Süreli evrak:** sertifika/sağlık raporu bitiş takibi → yaklaşınca personel + İK uyarısı → yenileme.
- **Personel görünümü:** kendi evrakları + firma dokümanları (yetkiye göre) portalda.

---

## 11. ÜCRET YÖNETİMİ

**Yönetim başlangıçlı; yüksek gizlilik.**

- **Ücret değişikliği:** efektif tarihli, gerekçeli kayıt → (firma isterse) onay akışı → uygulanınca ücret geçmişine yazılır.
- **Zam dönemi:** İK dönem açar → toplu öneri (bant/performansa göre) → onay akışı → uygulama.
- **Alan izni:** ücret verisi yalnızca `salary.view` iznine açık; personel portalda **yalnızca kendi** güncel ücretini/geçmişini görür (yetkiyse).
- **Modüller arası:** Performans (zam gerekçesi), İzin (izin karşılığı ücret), ileride Bordro (ana girdi).

---

## 12. TALEP & VAKA (İK HELPDESK)

**Portal başlangıçlı — standart akış + genel amaçlı.**

- Yukarıdaki modüllerde karşılığı olmayan her talep buraya düşer (genel İK talebi, belge talebi, bilgi güncelleme...).
- Talep tipi Form Engine'li dinamik form + kendi onay akışı + SLA.
- **Durum:** `open → in_progress → waiting → resolved → closed`; kategoriye göre otomatik atama.
- **Portal:** personel talebini açar, yanıtları görür, kapanınca değerlendirir.

---

## 13. MODÜLLER ARASI TETİKLEME HARİTASI (özet)

| Kaynak olay | Tetiklenen |
|---|---|
| Aday `hired` (İşe Alım) | Onboarding süreci başlar + Personel kaydı ön-dolar + portal erişimi |
| İzin `approved` | İzin Takvimi + Puantaj (izinli gün) + Ekip Takvimi |
| İşten çıkış başlar (Personel) | Offboarding görevleri + Zimmet iadesi + Erişim kapatma + kalan izin → Ücret |
| Zorunlu eğitim atandı | Portal görevi + hatırlatma + (süreli) sertifika yenileme |
| Süreli evrak/sertifika bitişi yaklaşıyor | Personel + İK uyarısı (zamanlanmış) |
| Masraf `paid` | (ileride) Bordro/muhasebe girdisi |
| Puantaj dönem kapandı | Bordro-hazır export |
| Herhangi talep `submitted` | Workflow + Bildirim (onaycıya) |
| Herhangi kayıt değişti | Audit log + (kuralda varsa) Workflow aksiyonu |

---

## 14. PORTAL vs YÖNETİM SORUMLULUK ÇİZGİSİ

| Portal (personel) | Yönetim (İK/yönetici) |
|---|---|
| Kendi verisini görür | Tüm/yetki kapsamındaki veriyi görür |
| Talep başlatır (izin/masraf/eğitim/zimmet/doküman/genel) | Talepleri onaylar/yönetir |
| Kendi görevlerini tamamlar (onboarding, zorunlu eğitim, evrak) | Süreç/şablon/kural tanımlar |
| Öz-değerlendirme, anket, OKR günceller | Dönem/anket açar, sonuç analiz eder |
| Kendi bakiye/bordro/zimmet/sertifikasını izler | Bakiye düzeltir, atama yapar, rapor çeker |
| **Hiçbir yönetim/onay yetkisi yok** (yönetici rolü hariç) | Yetki + veri kapsamı kadar |

---

*Not: Bu akışların tümü Workflow Engine (Faz 4B) ve Bildirim Merkezi (Faz 4C) hazır olunca tam otomatik çalışır. Faz 6'da her modül geliştirilirken bu dosyanın ilgili bölümü o modülün fonksiyonel referansıdır. Firma-özel parametreler (onay kademeleri, limitler, türler) Ayarlar Stüdyosu'ndan doldurulur; akış mekanizması sabittir.*
