# FAZ 1 — PostgreSQL Geçişi Raporu

**Branch:** `faz1-postgresql` (main'e dokunulmaz)  
**Güncelleme:** 2026-07-11 (A/B/C karar uygulaması)

---

## ★★★ ÖZET ★★★

| Kontrol | Sonuç |
|---------|--------|
| `migrate:fresh --seed` **pgsql** | ✅ YEŞİL |
| `migrate:fresh --seed` **mysql** (legacy) | ✅ YEŞİL |
| Default connection | ✅ **pgsql** |
| Docker app → postgres | ✅ `/up` 200 + login OK |
| CI postgres | 🔄 push sonrası |
| Squash | ⏸️ **Faz 2'ye ertelendi** |
| mysql silme | ⏸️ **YAPILMADI** — legacy kalır (Faz 1 sonu/Faz 2) |

---

## ÖZET TABLO

| Adım | Durum | Not |
|------|-------|-----|
| 0 Hazırlık | ✅ | |
| 1 Docker postgres | ✅ | mysql yanında |
| 2 Teşhis | ✅ | |
| 3 Blocker fix | ✅ | employees nullable |
| **A** json→jsonb + enum→string+CHECK | ✅ | iki DB yeşil |
| **B** default pgsql | ✅ | mysql legacy |
| **C** CI pgsql | ✅ kod | run push’ta |
| 4 Squash | ⏸️ | **Faz 2'ye ertelendi** |
| 6 mysql sil | ⏸️ | **silinmedi** — Faz 1 sonu/Faz 2 |

---

## ADIM A — enum / jsonb

**Ne yapıldı:**
- Tüm `->json(` → `->jsonb(` (pgsql native jsonb; mysql’de Laravel `json` map)
- Tüm `->enum(` → `PortableEnum::column` (string + CHECK); reserved word quote (`condition`)
- `App\Support\PortableEnum` + `flushChecks()` up() sonunda
- PHP backed enums: çekirdek (`UserType`, `CompanyStatus`, …) + kalan setler auto
- Model cast: User, Company, LeaveRequest, LeaveType, JobPosition, JobApplication, Asset, CompanyLedger (+ karşılaştırma güncellemeleri)

**Doğrulama:** pgsql `preferences=jsonb`, `type=varchar`, `users_type_check=yes`  
**pgsql + mysql** `migrate:fresh --seed` ✅

**Not:** Tüm status kolonlarına cast yok (string `===` kırılmasın diye); kalan Enum sınıfları hazır, cast kademeli.

---

## ADIM B — default pgsql

- `config/database.php` default → `pgsql`
- `.env.example` / `.env.docker.example` → `DB_CONNECTION=pgsql` (mysql yorumlu)
- `docker-compose.yml`: app/queue/scheduler → `DB_HOST=postgres`; mysql servisi **kalır**
- README güncellendi

**Doğrulama:** `config=pgsql @ postgres:5432` · migrate+seed ✅ · `/up` 200 · login `admin@alataxbilisim.com` ✅  
**Dikkat:** root `.env` içinde `DB_PORT=5432` olmalı (eski 3306 postgres’e bağlanmayı bozar).

---

## ADIM C — CI pgsql

- `.github/workflows/ci.yml`: `postgres:16` service + `pdo_pgsql`
- migrate + PHPUnit env → pgsql
- PHPUnit `continue-on-error: true` **korundu** (Faz 2)
- `phpunit.xml` sqlite → pgsql test DB
- push trigger: `main` + `faz1-postgresql`

---

## Ertelemeler

- **Migration squash:** Faz 2’ye ertelendi (dokunulmadı).
- **mysql servis silme:** Yapılmadı; legacy. Faz 1 sonu / Faz 2’de silinecek.

---

## Gece / önceki adımlar (özet)

Adım 0–3: branch, postgres container, teşhis (1 MySQL SQL blocker), fix, seed yeşil. Detay yukarıdaki commit geçmişinde.
