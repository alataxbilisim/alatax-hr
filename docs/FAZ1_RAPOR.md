# FAZ 1 — PostgreSQL Geçişi Raporu

**Branch:** `faz1-postgresql` (main'e dokunulmaz)  
**Başlangıç:** 2026-07-11  
**Kural:** Her adım ayrı commit; belirsizlikte DUR + buraya yaz.

---

## ★★★ KALİTİ SONUÇ ★★★

# `migrate:fresh --seed` POSTGRES'TE YEŞİL

- 64 migration DONE + tüm seeder'lar DONE (pgsql)
- Aynı komut **MySQL'de de YEŞİL** (geriye uyumluluk doğrulandı)
- App default hâlâ **mysql** (main/docker kurulum bozulmadı)
- Postgres container mysql'in **yanında** çalışıyor

---

## ÖZET TABLO

| Adım | Durum | Not |
|------|-------|-----|
| 0 Hazırlık | ✅ | `faz1-postgresql` + tarama |
| 1 Docker postgres | ✅ | postgres:16 + pdo_pgsql; mysql healthy kaldı |
| 2 Teşhis migrate | ✅ | 1 blocker kategorize edildi |
| 3 Uyumluluk fix | ✅ | blocker düzeltildi; pgsql+mysql seed yeşil |
| 4 Squash | ⏸️ | **ATLANDI** — riskli; karar bekliyor |
| 5 config/CI default pgsql | ⏸️ | **ATLANDI** — Adım 4 şartı; karar bekliyor |
| 6 mysql kaldır | ⏸️ | **YAPILMADI** — mysql bırakıldı (kural) |

---

## ADIM 0 — Hazırlık

**Ne yaptım:** `git checkout -b faz1-postgresql`; config/database.php, docker-compose, 64 migration, phpunit.xml okundu.

**Bulgular:** default sqlite/mysql env; pgsql connection tanımlı; Dockerfile'da yalnızca `pdo_mysql` vardı; phpunit sqlite `:memory:`.

**Karar?** Hayır.

---

## ADIM 1 — Docker'a PostgreSQL (mysql kalır)

**Ne yaptım:**
- `postgres:16-alpine`, volume `postgres_data`, `pg_isready`, host **5432**
- `.env.docker.example`: `POSTGRES_*` (mysql default)
- Dockerfile: `libpq-dev` + `pdo_pgsql`
- Rebuild + `docker compose up -d postgres app`

**Sonuç:** postgres healthy + mysql healthy yan yana; `pdo_pgsql` yüklü.

**Karar?** Hayır.

---

## ADIM 2 — Teşhis (squash öncesi)

**Ne yaptım:** pgsql'de `migrate:fresh`; FAIL sonrası dosyayı geçici atlayıp probe (geri yüklendi).

**Runtime:**
- 58 migration ilk denemede DONE (enum/json dahil)
- **FAIL:** `2025_01_25_000000_update_employees_table_make_user_id_nullable.php`
  - `DATABASE()` / `SHOW INDEXES` (MySQL SQL)
- Dosya atlanınca kalan migration'ların hepsi DONE

**Kategori:**

| Kategori | Runtime | Not |
|----------|---------|-----|
| MySQL ham SQL | BLOCKER (1 dosya) | yukarıdaki alter |
| `->enum` (~67) | Geçiyor | Laravel pgsql → varchar+check |
| `->json` (~56) | Geçiyor | pgsql `json` (jsonb değil) |
| mediumText/longText/unsignedTinyInteger | Geçiyor | Laravel map |

**⏸️ KARAR BEKLENİYOR (opsiyonel):** Toplu `enum`→`string`+CHECK ve `json`→`jsonb` (mysql için driver dalı gerekir). Runtime için **şart değil**.

---

## ADIM 3 — Uyumluluk düzeltmesi

**Ne yaptım:**
- `2025_01_25_000000_...` yeniden yazıldı: driver-aware; `Schema::getColumns` / `getForeignKeys` / `getIndexes`; fresh'te zaten nullable ise **no-op**
- Ham `DATABASE()` / `SHOW INDEXES` kaldırıldı

**Doğrulama:**
| Ortam | Komut | Sonuç |
|-------|-------|-------|
| PostgreSQL | `migrate:fresh --seed --force` | ✅ YEŞİL |
| MySQL (compose default) | `migrate:fresh --seed --force` | ✅ YEŞİL |

**Karar?** Toplu enum/jsonb dönüşümü hâlâ opsiyonel (yukarıda).

---

## ADIM 4 — Migration squash

**Durum:** ⏸️ **ATLANDI**

**Neden:** Squash “olsa iyi”; şart olan postgres uyumu sağlandı. 64→baseline birleştirme + `pg_dump` şema diff doğrulaması gece otonomunda veri/şema kaybı riski yüksek. Emin değilim → kural gereği DUR.

**⏸️ KARAR BEKLENİYOR:** Squash yapılsın mı? Yapılacaksa hangi modül grupları (`0001_core`, `0002_hr`, …)?

---

## ADIM 5 — config / env / CI → pgsql default

**Durum:** ⏸️ **ATLANDI** (kural: “SADECE 3-4 yeşilse”; 4 atlandı)

**⏸️ KARAR BEKLENİYOR:**
1. Squash olmadan default'u `pgsql` yapmak OK mi?
2. CI'yı sqlite → postgres service container'a çevirmek (PHPUnit hâlâ kırık / Faz 2 borcu — CI'yı pgsql'e almak ayrı iş)

---

## ADIM 6 — mysql kaldır

**Durum:** ⏸️ **YAPILMADI** — mysql bırakıldı; default mysql. Kural: emin değilsen bırak.

---

## Branch / commit'ler (bu gece)

| Commit | Konu |
|--------|------|
| (adım 0) | `docs/FAZ1_RAPOR.md` başlangıç |
| (adım 1) | docker postgres + Dockerfile pdo_pgsql |
| (adım 2+3) | teşhis raporu + employees nullable migration fix |

`main`'e merge/push yok. Push: `origin/faz1-postgresql`.

---

## Sabah için net sonraki adımlar

1. Onay: opsiyonel enum/jsonb toplu dönüşüm?
2. Onay: migration squash + şema diff?
3. Onay: default/CI → pgsql (mysql legacy kalsın)?
4. Onay sonrası: mysql servisini kaldırma / legacy yorum.

---
