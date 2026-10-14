# Ortak Kurallar — Her Cursor Görevinde Geçerli

Bu dosya, `docs/cursor-gorevleri/` altındaki her görevin ön koşuludur. Göreve başlamadan önce tamamını oku ve uygula.

## Ürün vizyonu (asla küçük düşünme)
- Ürün: **Dobedan HR** — Türkiye odaklı, kurumsal, çok kiracılı (multi-tenant) B2B İK SaaS. Cloud ve on-prem tek kod tabanı.
- Ölçek hedefi: 10 kişilik tek şubeden on binlerce personelli, yüzlerce şubeli holdinglere kadar. Modül sayısı sürekli artacak (bugün 14, hedef çok daha fazla).
- Her karar bu ölçeğe göre verilir: "şimdilik yeter" çözümü yok. Geçici çözüm zorunluysa açıkça `GEÇİCİ` diye işaretlenir ve `docs/DURUM.md` borç listesine yazılır.
- Yeni bir şey eklerken sor: "40 modül, 50.000 personel, 500 şirket olduğunda bu yapı çalışır mı?"

## Teknik sabitler
- Veritabanı **yalnızca PostgreSQL 16**. MySQL, MariaDB ve SQLite hiçbir yerde (kod, config, Docker, CI, test, doküman) kullanılmaz ve önerilmez.
- Backend: Laravel 12 / PHP 8.2+, `/api/v1`. Frontend: pnpm monorepo, React 19 + TypeScript, 3 uygulama (company, portal, superadmin) + shared paket.
- Geliştirme ortamı Docker Compose (Windows host). Backend komutları container içinde çalışır: `docker compose exec app php artisan ...`. Host'ta `php artisan` çalıştırma.

## Çalışma kuralları
1. **Önce oku, sonra değiştir.** Her görev bir teşhis adımıyla başlar. Teşhiste bulduğun şey görev tanımıyla çelişiyorsa DUR ve raporla.
2. **Veri kaybı yok.** Canlı DB'ye dokunan her işlemden önce `pg_dump` yedeği alınır (`docs/backups/`, git dışı). `DROP DATABASE`, `docker volume rm`, `migrate:fresh` gibi geri alınamaz işlemler **yalnızca kullanıcı açıkça onaylarsa** yapılır.
3. **`APP_KEY` asla değişmez.** 2FA sırları ve şifreli alanlar bu anahtara bağlı.
4. **Git:** Her mantıksal adım ayrı bir commit (Conventional Commits, Türkçe açıklama serbest). `push` yalnızca kullanıcı isterse. `main`e dokunma (görev aksini söylemiyorsa). Commit mesajına BOM veya gizli karakter girmesin.
5. **Testler yeşil kalır.** Görev sonunda backend test paketi ve frontend `lint` + `build` çalıştırılır. Kırmızı bırakılan test varsa nedeni rapora yazılır; testi silerek veya atlayarak yeşile çevirmek yasak.
6. **Gizli bilgi** (şifre, token, APP_KEY) rapora, log'a, commit'e yazılmaz.
7. **Kapsam disiplini:** Görevde istenmeyen özellik geliştirme yapılmaz. Gördüğün ama kapsam dışı olan sorunları raporun "Kapsam dışı bulgular" bölümüne yaz.

## Rapor formatı (her görevin sonunda)
`docs/cursor-gorevleri/raporlar/ADIM_XX_RAPOR.md` dosyasına yaz:
1. Yapılanlar (dosya listesiyle)
2. Commit listesi (`git log --oneline` çıktısı)
3. Doğrulama komutları ve çıktıları (özet)
4. Kabul kriterlerinin tek tek durumu (✅ / ❌ + kanıt)
5. Kullanıcının elle yapması gerekenler
6. Kapsam dışı bulgular ve açık sorular
