# CURSOR PROJE ANALİZ PROMPT'U

> **Kullanım:** Bu dosyanın "PROMPT BAŞLANGICI" satırından sonrasını kopyala ve Cursor'da
> **Agent modunda** (Claude Sonnet/Opus önerilir) yapıştır. Proje büyükse ve model tek seferde
> bitiremezse "kaldığın yerden devam et, PROJECT_SNAPSHOT.md dosyasına ekleyerek yaz" de.
> Çıktı olarak proje kök dizininde `PROJECT_SNAPSHOT.md` oluşacak. O dosyayı Claude'a geri yükle.

---

## PROMPT BAŞLANGICI

Sen kıdemli bir yazılım mimarısın. Görevin: bu projenin mevcut durumunun **eksiksiz bir teknik envanterini** çıkarmak ve proje kök dizinine `PROJECT_SNAPSHOT.md` adında TEK bir dosya olarak kaydetmek. Bu belge, projeyi hiç görmemiş bir mimarın projeyi %100 anlamasını sağlayacak detayda olmalı.

### KURALLAR (kesinlikle uy)

1. **Hiçbir şeyi varsayma, hatırladığını yazma.** Her bilgiyi doğrudan koddan, config dosyalarından, migration'lardan, route tanımlarından OKUYARAK çıkar. Yazmadan önce ilgili dosyaları gerçekten aç ve tara.
2. Kodda bulamadığın veya emin olmadığın her nokta için `⚠️ BELİRSİZ:` etiketi kullan ve neden belirsiz olduğunu yaz. Boşluk uydurma.
3. `.env` ve benzeri dosyalardaki **DEĞERLERİ asla yazma**; sadece değişken **İSİMLERİNİ** listele. Şifre, API key, connection string, token gibi hiçbir gizli değer belgeye girmeyecek.
4. Her önemli tespitte ilgili **dosya yolunu** referans ver (örn. `src/models/Employee.ts`, `database/migrations/2024_...`).
5. Bu belge sadece **MEVCUT DURUM** tespitidir. Öneri, iyileştirme fikri, yorum yazma.
6. Belgeyi **Türkçe** yaz; teknik terimler İngilizce kalabilir.
7. Uzunluk sınırı yok. Detay her zaman özetten iyidir. Belgeyi oluşturmadan önce projenin TAMAMINI tara: tüm klasörler, tüm migration'lar, tüm route/controller dosyaları, tüm frontend sayfaları.

### PROJECT_SNAPSHOT.md İÇERİĞİ (bu sıra ve başlıklarla)

#### 1. Proje Özeti
- Proje adı, amacı, tek paragraflık tanım
- Repo yapısı: monorepo mu, ayrı frontend/backend mi?
- Genel tamamlanma tahmini (%) ve bu tahminin gerekçesi

#### 2. Teknoloji Stack'i
- Backend: dil, framework, tam versiyon
- Frontend: framework, versiyon, UI kütüphanesi, CSS yaklaşımı (Tailwind vs.), state management, form kütüphanesi
- Veritabanı: motor (MySQL/PostgreSQL/...), versiyon, ORM/query builder ve kullanım şekli
- Kimlik doğrulama yöntemi (JWT, session, OAuth vb.)
- `package.json` / `composer.json` / `requirements.txt` içindeki TÜM bağımlılıkları versiyonlarıyla listele; her birinin projede ne için kullanıldığını tek cümleyle açıkla (kullanılmayan bağımlılık varsa işaretle)
- Runtime versiyonları (Node/PHP/Python), build araçları

#### 3. Klasör Yapısı
- 3 seviye derinliğe kadar dizin ağacı (node_modules, vendor hariç)
- Her ana klasörün görevi

#### 4. Veritabanı Şeması — EN DETAYLI BÖLÜM
- **TÜM tablolar:** her tablo için tüm kolonlar, veri tipleri, nullable/default değerler, PK/FK, unique kısıtlar, indeksler
- Tablolar arası ilişkiler (1-1, 1-N, N-N) ve hangi FK ile kurulduğu
- Bir **Mermaid ERD diyagramı** ekle
- Migration dosyalarının listesi ve kronolojik sırası; çalıştırılmamış/yarım migration var mı?
- Seed data var mı, neler içeriyor?
- Multi-tenancy izi var mı? (`company_id` / `tenant_id` benzeri kolonlar hangi tablolarda var, hangilerinde eksik?)
- Soft delete, timestamp (created_at/updated_at) tutarlılığı

#### 5. Modüller ve Özellikler
Tespit ettiğin her modül/özellik için ayrı alt başlık aç:
- Modül adı ve amacı
- İlgili sayfalar/ekranlar
- Yapabildikleri (CRUD, filtreleme, arama, export, onay akışı, toplu işlem vs.)
- Kullandığı backend endpoint'leri ve tablolar
- Tamamlanma durumu: ✅ Çalışıyor / 🔶 Kısmen / ❌ Sadece iskelet
- Yarım kalan işler ve eksikler

#### 6. API Envanteri
- Tüm endpoint'ler tablo halinde: `Metod | Rota | Amaç | Auth gerekli mi | Rol kısıtı | Controller/dosya`
- API stili (REST/GraphQL), versiyonlama var mı, standart bir response formatı var mı, hata yönetimi nasıl?

#### 7. Kimlik Doğrulama ve Yetkilendirme
- Login / register / şifre sıfırlama / oturum yenileme akışları
- Mevcut roller ve nerede tanımlı (hardcoded mı, veritabanında mı?)
- Permission/izin sistemi var mı? Granularitesi ne? (modül / sayfa / aksiyon / alan seviyesi?)
- Superadmin mantığı nasıl çalışıyor, normal şirket kullanıcılarından nasıl ayrılıyor?
- Multi-tenant ayrımı nasıl sağlanıyor (middleware, global scope, query filter)?
- Tüm middleware'lerin listesi ve görevleri

#### 8. Frontend Envanteri
- Tüm route/sayfa listesi: `URL | Sayfa adı | Amaç | Hangi role açık | Durum`
- Ortak/paylaşılan component'ler (tablo, form, modal, layout, sidebar vs.) ve nerede tanımlı oldukları
- Tema/tasarım sistemi: renkler, font, spacing, boyutlar nasıl yönetiliyor (design token, Tailwind config, hardcoded)?
- Responsive durumu; hangi ekran boyutları hedeflenmiş?
- Form validasyon yaklaşımı
- i18n / çoklu dil altyapısı var mı?

#### 9. İş Akışları ve Otomasyon
- Onay süreçleri (izin onayı vb.) nasıl kurgulanmış? Sabit mi, konfigüre edilebilir mi?
- Bildirim sistemi (e-posta, in-app, push) var mı, nasıl tetikleniyor?
- Zamanlanmış işler (cron/queue/job/worker) var mı?

#### 10. Loglama, Audit ve Güvenlik
- Mevcut loglama: uygulama logu, hata logu, audit trail — ne var, ne yok?
- Input validation, rate limiting, CORS, CSRF, XSS önlemlerinin mevcut durumu
- Şifre hash'leme ve hassas veri saklama yaklaşımı
- KVKK/GDPR'a dönük herhangi bir yapı (veri maskeleme, silme talebi, rıza) var mı?

#### 11. Ayarlar ve Özelleştirme
- Mevcut ayar sayfaları neleri yönetiyor?
- Custom field / dinamik form / konfigüre edilebilir ekran benzeri HERHANGİ bir altyapı var mı?

#### 12. Raporlama
- Mevcut rapor/istatistik/dashboard ekranları ve ne gösterdikleri
- Export (Excel/PDF/CSV) yetenekleri

#### 13. Ortam ve Deployment
- `.env` değişken İSİMLERİ (değerler kesinlikle HARİÇ) ve her birinin görevi
- Docker / docker-compose var mı? İçeriği ne?
- Projeyi lokalde ayağa kaldırma komutları (adım adım)
- Ortam ayrımı (dev/staging/prod) var mı?

#### 14. Test ve Kalite
- Test dosyaları var mı? Ne test ediliyor, tahmini kapsam?
- Linter/formatter (ESLint, Prettier, PHP-CS vs.) konfigürasyonu

#### 15. Üçüncü Parti Servisler ve Entegrasyonlar
- Mail, dosya depolama, SMS, ödeme vb. dış servisler ve kullanım yerleri

#### 16. Teknik Borç, Sorunlar, TODO'lar
- Koddaki TODO/FIXME/HACK yorumlarının listesi (dosya yoluyla)
- Tutarsızlıklar: isimlendirme karmaşası, duplike kod, kullanılmayan dosya/component'ler
- Fark ettiğin buglar veya kırık akışlar
- Güvenlik açısından riskli gördüğün noktalar

#### 17. Genel Değerlendirme Tablosu
Şu formatta kapat:

| Alan | Durum | Not |
|------|-------|-----|
| Veritabanı şeması | 🔶 | ... |
| Auth & Roller | ... | ... |
| ... | ... | ... |

Belgeyi tamamladığında, en sona `--- ANALİZ TAMAMLANDI ---` satırını ekle. Eğer bağlam yetmediği için bir bölümü tamamlayamadıysan bunu açıkça belirt ki devam komutu verilebilsin.

## PROMPT SONU
