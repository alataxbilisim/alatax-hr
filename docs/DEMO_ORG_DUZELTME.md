# Demo organization bütünlüğü — G2 sonrası düzeltme

**Branch:** `faz4-form-engine` · **DB wipe yok** · **push yok**  
**Tarih:** 2026-08-05

---

## 1) Teşhis (düzeltme öncesi — prod `alatax_hr`)

### companies

| id | name | slug | organization_id |
|----|------|------|-----------------|
| 69 | Demo Firma AŞ | `demo-firma` | **1** |
| 70 | Alatax Demo A.S. | `alatax-demo-as` | **2** |
| 71 | Demo Otel B | `demo-otel-b` | **3** |
| 72 | Demo Otel C | `demo-otel-c` | **4** |

### organizations

| id | name | slug |
|----|------|------|
| 1 | Demo Firma AŞ | `org-demo-firma` |
| 2 | Alatax Demo A.S. | `org-alatax-demo-as` |
| 3 | Demo Otel B | `org-demo-otel-b` |
| 4 | Demo Otel C | `org-demo-otel-c` |

`admin@demo.test` membership: **69, 71, 72** (70 yok).

### Neden ayrı organization’lar?

| Soru | Cevap |
|------|--------|
| FAZ_G_RAPOR “aynı organization” diyor — DB öyle miydi? | **Hayır.** Teşhis anında 69/71/72 **üç ayrı** org’daydı (1 / 3 / 4). |
| `DemoDataSeeder` niyet | Kardeş şirketleri ana şirketin `organization_id`’sine `forceFill` ile çekmek (Dobedan: tek holding). |
| Kod ↔ DB | **Uyuşmuyordu.** Seeder niyeti tek org; yerel DB G1 sonrası 1:1 org’da kalmıştı. |
| Bozulma noktası | `Company::created` → `ensureOrganizationForCompany` + `group:backfill-company-context` her şirket için **ayrı** org üretir. Eski seeder hizası bu ortamda kalıcı tutunmamış / yeniden koşulmamış. **`gorsel:fixture` şirket/org üretmez** — yalnızca mevcut üç slug’a kullanıcı/içerik yazar. |
| `demo-otel-d-ix` vb. | **Prod’da yok.** PHPUnit `GroupScopeIntersectionTest` vb. `RefreshDatabase` ile **`alatax_hr_testing`** üzerinde `*-ix` slug üretir; geliştirme DB’sine yazılmaz. |

---

## 2) Düzeltme

### Kod

- `App\Services\Demo\DemoOrganizationAligner` — stabil holding: slug `org-demo-holding`
- `DemoDataSeeder::seedSisterCompanies` — kardeşleri oluşturduktan sonra **her zaman** `align()` + merkez membership
- Artisan: `php artisan demo:align-organizations [--with-memberships]`  
  - Idempotent; `organization_id` hizalar  
  - Boş kalan eski org id’lerini **raporlar, silmez**  
  - `production`’da çalışmaz

### Bu ortamda uygulanan (wipe yok)

```text
php artisan demo:align-organizations --with-memberships
→ 69, 71, 72 → organization_id = 5 (org-demo-holding)
→ Orphan org önerisi (silinmedi): 1, 3, 4
```

### Düzeltme sonrası companies (demo üçlüsü)

| id | slug | organization_id |
|----|------|-----------------|
| 69 | demo-firma | **5** |
| 71 | demo-otel-b | **5** |
| 72 | demo-otel-c | **5** |

`alatax-demo-as` (70) / org 2 — holding dışı; dokunulmadı.

### Test artığı / orphan temizliği (karar sizde — otomatik silinmedi)

```sql
-- Boş kalan eski demo org'lar (hizalama sonrası; şirket bağlı değilse):
-- DELETE FROM organizations WHERE id IN (1, 3, 4);

-- Test artığı şirket (yalnız testing DB'de beklenir; prod'da yoktu):
-- SELECT id, slug, organization_id FROM companies WHERE slug LIKE '%-ix' OR slug LIKE 'demo-otel-d%';
```

Şirket hard-delete önerilmez; cascade / FK riski. Orphan `organizations` satırları güvenli adaydır.

---

## 3) Test

`Tests\Feature\DemoHoldingOrganizationTest`

1. Seed sonrası üç slug → tek `organization_id` (`org-demo-holding`)
2. `admin@demo.test` üçüne üye; `GroupScopeService::reportableCompanyIds` / `resolveForReport(..., 'group')` üç id döner
3. `demo:align-organizations` dağınık org’dan sonra idempotent

---

## 4) `gorsel:fixture` ayrımı

| | |
|--|--|
| Varsayılan hedef | Uygulama default connection (`alatax_hr` local) |
| Koruma | `_testing` dışı DB’ye yazmak için **`--allow-dev-db`** zorunlu |
| Alternatif | `--use-testing-db` → `alatax_hr_testing` |
| Holding | Fixture başında `DemoOrganizationAligner::align()` (şirket yaratmaz) |
| İşaretler | `GORSEL-*` employee_code, `gorsel-fixture` leave reason, `gorsel.fixture` notification event, `gorsel-aday-*` e-posta |
| Temizlik | `php artisan gorsel:fixture-cleanup [--dry-run]` — **şirket silmez** |

`*-ix` test şirketlerini fixture yazmaz; onlar PHPUnit isolation fixture’ıdır.

---

## 5) Belge

| Dosya | Durum |
|-------|--------|
| `FAZ_G_RAPOR.md` “aynı organization” | **Kod + hizalı DB için doğru**; seeder artık `DemoOrganizationAligner` ile garanti eder. |
| `FAZ_G2_KAPANIS.md` | Seed dağınıklığı kapatıldı notu eklendi. |

---

## Suite / commit

| | |
|--|--|
| Yeni test | `DemoHoldingOrganizationTest` (3) |
| Suite | **716 passed** (713 + 3; Docker) |
| Commit | lokal; **push yok** |
