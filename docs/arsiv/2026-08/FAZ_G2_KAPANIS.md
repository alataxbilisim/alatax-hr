# FAZ G2 KAPANIŞ — üç doğrulama

**Tarih:** 5 Ağustos 2026 · **Branch:** `faz4-form-engine`  
**DB wipe yok · push yok**

---

## 1) DataScope group ara durumu — karar **(B)**

| Seçenek | Sonuç |
|---------|--------|
| (A) CHECK’e `group` + rol `data_scope=group` | Red |
| **(B) `DataScopeLevel::Group` koddan çıkar** | **Uygulandı** |

**Gerekçe (yazılı: `docs/FAZ_G2_KARAR.md` §c):** İki paralel yetki yolu (DataScope + `reports.scope.group`) Faz 6’da belirsizlik doğurur. CHECK’te olmayan Group enum’u config default ile sahte yeşil üretiyordu.

**Kod:**
- `DataScopeLevel::Group` silindi
- Policy / DataScopeService / dataset constrain’lerde Group kolları kaldırıldı
- `GroupDataScopeCrudIgnoreTest` yeniden yazıldı: `reports.scope.group` CRUD’u genişletmez; rapor `scope=group` membership kümesini açar

---

## 2) Kesişim kuralı — testli yeşil

**Fixture:** Aynı `organization` içinde A+B (üyelik var) + D (`demo-otel-d-ix`, membership yok).

| Test | Sonuç |
|------|--------|
| `GroupScopeIntersectionTest::test_scope_group_excludes_same_org_company_without_membership` | ✅ D satırı yok |
| `GroupScopeIntersectionTest::test_dashboard_group_kpi_excludes_non_member_org_company` | ✅ KPI `company_ids` ve `total_users` D’siz |

### company_id = 70 (prod `alatax_hr`, anlık)

| Alan | Değer |
|------|--------|
| id | **70** |
| name / slug | Alatax Demo A.S. / `alatax-demo-as` |
| organization_id | **2** (`org-alatax-demo-as`) |
| Aynı org’daki diğer şirket | Yok (org 2’de yalnız 70) |
| `admin@demo.test` membership | **69, 71, 72** — **70 yok** |

**Grup toplamına girer mi?** Aktif bağlam 69/71/72 iken `scope=group` kümesi = o şirketin org ∩ membership. Kapanış anında 69/71/72 **ayrı organization**’lardaydı (1 / 3 / 4) — holding kesişimi tek şirkete düşüyordu. 70, admin üye olmadığı için hiçbir `admin@demo.test` grup kümesine **girmez**. (Kesişim regresyonu feature testte A∪B∖D ile sabitlendi.)

**Seed dağınıklığı kapatıldı (2026-08-05):** `DemoOrganizationAligner` + `demo:align-organizations`; 69/71/72 → tek `org-demo-holding`. Test: `DemoHoldingOrganizationTest`. Orphan org 1/3/4 silinmedi (manuel). Detay: `docs/DEMO_ORG_DUZELTME.md`.

---

## 3) İndeks kanıtı — EXPLAIN ANALYZE

### Prod `attendance_records`

| Metrik | Değer |
|--------|--------|
| Satır sayısı | **306** |
| Tarih aralığı | 2026-05-01 … 2026-07-31 |
| Mevcut indeks | `attendance_records_company_date_idx (company_id, date)` ✓ migration mevcut |

**Rapor şekilli sorgu (SELECT id değil):**

```sql
SELECT attendance_records.id, company_id, user_id, date, status, total_hours
FROM attendance_records
WHERE company_id IN (69, 71)
  AND date BETWEEN DATE '2025-01-01' AND DATE '2026-12-31'
ORDER BY date ASC
LIMIT 1000;
```

**EXPLAIN ANALYZE (prod, 306 satır):** `Seq Scan` — tüm satırlar filtreye uyuyor / tablo küçük; indeks seçimi **anlamsız** (Execution ~0.3 ms).

Önceki G2 raporundaki `company_id_source` Index Scan + `Filter: date` kanıtı, `SELECT id` + geniş/boş sonuç seti ile planlayıcı yanılgısıydı; **gerçek rapor sütunları + ANALYZE** ile değiştirildi.

### Sentetik (~1.5M) — `alatax_hr_testing.attendance_g2_bench`

Prod’a dokunulmadı. Yapısal klon + indeksler:

- `attendance_g2_bench_company_date_idx (company_id, date)`
- `…_company_source_idx (company_id, source)`
- `…_company_status_date_idx`

| Metrik | Değer |
|--------|--------|
| Satır | **1_500_000** |
| Tarih | 2024-01-01 … 2024-10-27 |

**Dar tarih (1 ay, rapor tipi) — seçilen plan:**

```text
Bitmap Index Scan on attendance_g2_bench_company_date_idx
  Index Cond: (company_id = ANY ('{69,71}') AND date >= … AND date <= …)
→ Parallel Bitmap Heap Scan
Execution Time: ~17–30 ms (LIMIT 1000)
```

**Geniş tarih (tüm 2024, ~1M satır eşleşir):** Parallel Seq Scan — seçicilik düşük; planlayıcı indeks yerine seq tercih eder (beklenen).

### Neden eski kanıtta `(company_id, date)` seçilmedi?

1. **Az satır (306):** Seq Scan maliyeti indeksten düşük.
2. **Önceki sorgu şekli:** `SELECT id` + `company_id_source` indeksi company_id ANY için yeterli göründü; date **Filter** kaldı.
3. **Kolon sırası / tip:** `(company_id, date)` btree doğru; date `date` tipi. Sorun indeks tanımında değil — **seçicilik + küçük tablo**.
4. **1.5M + dar tarih:** `(company_id, date)` **Bitmap Index Scan** ile seçiliyor → indeks geçerli.

Bench tablo kapanış sonrası bırakılabilir veya `DROP TABLE attendance_g2_bench` ile temizlenebilir (prod değil).

---

## Suite / commit

| | |
|--|--|
| Suite | **713 passed** (Intersection + CRUD rewrite; Docker) |
| Commit | push yok |

Detay karar: `FAZ_G2_KARAR.md` · önceki dalga: `FAZ_G2_RAPOR.md`
