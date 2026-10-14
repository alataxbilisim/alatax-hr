# Dobedan HR — Backend

Laravel 12 REST API (`/api/v1`), PHP 8.2, PostgreSQL 16, Redis. Paket adı: `dobedan/hr-backend`.

Kurulum ve çalıştırma kök [`README.md`](../README.md) içindedir; backend yalnız Docker üzerinden çalıştırılır:

```bash
docker compose exec app php artisan about
docker compose exec app php vendor/bin/phpunit
```

- Testler yalnız `dobedan_hr_testing` veritabanına bağlanır (`phpunit.xml`, `tests/bootstrap.php`).
- Güncel durum ve teknik borç: [`docs/DURUM.md`](../docs/DURUM.md) · kod kuralları: [`.cursorrules`](../.cursorrules).
