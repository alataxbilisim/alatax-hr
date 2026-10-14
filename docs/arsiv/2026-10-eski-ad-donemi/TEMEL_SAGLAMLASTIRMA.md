# TEMEL SAĞLAMLAŞTIRMA — Faz 6 öncesi

**Tarih:** 2026-08-06  
**Branch:** `faz4-form-engine` (push yok)  
**Suite:** **758 passed** (3263 assertions) · ~832s  
**Fresh seed:** öncesi ~29s · sonrası squash ~22s · final ~20s  
**3 SPA build:** yeşil (`pnpm run build`)

---

## 0) Repo hijyeni

- `.gitignore`: `docs/backups/`, `docs/**/*.log`, `docs/**/*.dump`, `docs/qa/**`, `docs/e2e/**`, `docs/**/KANIT.html`, `docs/denetim-raw.json`, `frontend/_archive_old_app/`
- `frontend/_archive_old_app` diskten silindi (git-track yoktu)
- Dump (`docs/backups/*.dump`) commit edilmemişti — ignore ile korunuyor

### Commit’te kalan büyük artifact (karar sizde)

Geçmişten temizleme yapılmadı. Örnekler:

| Tür | Örnek | Not |
|-----|--------|-----|
| `docs/qa/e2e/*.png` | 50–270 KB | ignore eklendi; hâlâ tracked |
| `docs/**/KANIT.html` | tur2/tur3 ~500+ KB | arşive taşındıysa path değişti; hâlâ tracked olabilir |
| `docs/arsiv/2026-08/gorsel-kontrol/**` | PNG/ss | tur raporlarıyla taşındı |

**MySQL:** Gerekli — proje kuralı + `docker-compose` `mysql` servisi + `config/database.php` mysql bağlantısı. Default yine `pgsql`; legacy yol bilinçli korunur.

---

## 1) Zaman dilimi ve puantaj

| Katman | Karar |
|--------|--------|
| Mutlak an | `timestamp` → `timestamptz` (UTC anı) |
| İş verisi | `date` / `time` kalır; yazım **Europe/Istanbul** duvar saati |

- `APP_TIMEZONE=Europe/Istanbul` (config varsayılan, `.env*`, docker, phpunit)
- Punch yolu `now()->toDateString()` / `H:i` → TR günü
- Escalation gün farkı: timestamptz UTC okunsa bile `config('app.timezone')` ile `startOfDay`
- Test: `AttendanceTimezoneTest` (23:30 / 00:30 TR, ay sınırları, audit anı)

---

## 2) Migration baseline (squash)

- App `pg_dump` 15 vs Postgres 16 uyumsuz → dump **postgres konteynerinden**; `\restrict` satırları strip
- 107 migration → `database/schema/pgsql-schema.sql` + **0** eski PHP migration
- Sonraki değişiklikler yeni migration: `home_company_id`, position drop, partial unique
- Temiz kurulum: schema load ~4s; migrate:fresh --seed **~20–22s** (öncesi ~29s; timestamptz migrate tek başına ~6s idi)

---

## 3) `users.company_id` → `home_company_id`

- Kolon yeniden adlandırıldı; `last_company_id` ve entity `company_id` dokunulmadı
- User/Company ilişkileri, Portal, CompanyContext, BelongsToCompany, factory/seeder/testler güncellendi
- FE: `User.home_company_id`
- `backend/app` içinde operasyonel `$user->company_id` **yok**

---

## 4) `employees.position` string drop

- Kolon drop; SSOT = `position_id` FK
- Dual-write kaldırıldı; factory/API/rapor/salary band `position_id` / `position.name`
- Demo: katalog adıyla çakışan DEMO_POS satırları mevcut pozisyona bağlanır (`Yazılım Geliştirici` vb.)
- **Karar:** `(company_id, name) WHERE deleted_at IS NULL` unique — evet (belirsiz ad eşleşmesinin kök nedeni)

---

## 5) Soft-delete + partial unique

Partial unique (`WHERE deleted_at IS NULL`):

- `employees (company_id, employee_code)`
- `users (email)`
- `branches (company_id, code)`
- `positions (company_id, code)` + `(company_id, name)`
- `employees (company_id, national_id)` WHERE `national_id IS NOT NULL`

Test: soft-delete sonrası aynı sicil/e-posta açılır; aktif çift TCKN reddedilir.

---

## 6) Silme guard’ları

| Senaryo | Davranış |
|---------|----------|
| Pozisyon silme | Personel kullanıyorsa **422** + sayı |
| User soft-delete | `model_has_roles` (+ token) detach |
| Rol silme | `approval_steps.specific_role` kullanımı → **422** (string guard; FK’ye çevrilmedi — Spatie name ile eşleşen tasarım) |
| Company forceDelete | Audit `company_id` null / sıralama — FK 23503 yok |

İlke: `docs/SISTEM_ISLEYIS.md` — soft-delete FK cascade/SET NULL tetiklemez; ilişki politikası modülde açık kodlanır.

---

## 7) Accrual transaction

`processMonthlyAccruals`: personel başına `DB::transaction` (bakiye + AccrualLog). Ortada exception → o personel geri alınır, diğerleri etkilenmez.

---

## 8) Belge senkronu

- `FAZ4_RAPOR.md` / arşiv `TUR8_KAPANIS.md` position_id SSOT
- `GUNCEL_DURUM_RAPORU.md` — 758, Faz G/G2 kapalı, 2026-08-06
- `docs/FAZ_G_KAPANIS_OZET.md` — Tur2–8 tek sayfa
- Tur + FAZ G serisi → `docs/arsiv/2026-08/`
- `SISTEM_PANOSU_DUZEN.md` → `SISTEM_ISLEYIS.md` içine

---

## Doğrulama özeti

```
migrate:fresh --seed     → FRESH_FINAL_SEC≈20  ✓
php artisan test         → 758 passed          ✓
pnpm run build (3 SPA)   → yeşil               ✓
```

Yeni test paketi: `tests/Feature/TemelSaglamlamaTest.php` (14 senaryo).
