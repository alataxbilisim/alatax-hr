# FAZ G2 — Rapor (grup rapor/pano kapsamı)

**Tarih:** 5 Ağustos 2026 · **Branch:** `faz4-form-engine`  
**Dalga:** G2 — rapor/pano `scope=group`  
**Kararlar:** [`FAZ_G2_KARAR.md`](FAZ_G2_KARAR.md)  
**Push yok.**

---

## Özet

Tek-şirket varsayılanı korundu. Rapor motoru ve klasik dashboard, isteğe bağlı `scope=group` ile **aktif şirketin organization ∩ kullanıcının membership** kümesini kullanır; izin `reports.scope.group` zorunlu (yoksa 403). Operasyonel CRUD ve KVKK yolları group uygulamaz.

---

## Kararlar (kısa)

| (a) Küme | organization ∩ membership + `reports.scope.group` |
| (b) KVKK | Hiçbir koşulda group yok |

---

## Kod

| Parça | Dosya / not |
|-------|-------------|
| İzin | `PermissionSeeder` → `reports.scope.group` |
| Küme | `GroupScopeService::reportableCompanyIds` |
| DataScope | `DataScopeLevel::Group` (CRUD = company gibi; DB CHECK’e yazılmaz — config default ile test) |
| Motor | `ReportQueryBuilder` `scope=company\|group`; survey/training `whereIn` |
| Cache | `ReportScopeSignature`: `report_scope` + sıralı `company_ids` |
| Export/job | şirket kolonu; `ProcessHeavyReportExportJob` `(companyId, reportScope, companyIds)`; `companyId<1` → exception |
| Dashboard | `GET /dashboard?scope=group`; FE toggle (`reports.scope.group`) |
| Pano v2 | batch `scope` → widget config |

---

## Testler

| Paket | Sonuç |
|-------|--------|
| `GroupIsolationTest` 1–20 | ✅ |
| `DatasetRegistryIsolationTest` company + group × 11 (+ izin 403) | ✅ |
| `GroupDataScopeCrudIgnoreTest` | ✅ |
| `KvkkGroupScopeIgnoredTest` | ✅ |
| `ReportScheduleAndCacheTest` group cache | ✅ |

Full suite: **711 passed** (Docker `alatax-hr-app`, 5 Ağustos 2026).

---

## İndeks / EXPLAIN

Migration `2026_07_31_150000_add_attendance_records_company_date_indexes.php` zaten mevcut:

- `attendance_records_company_date_idx` `(company_id, date)`
- `attendance_records_company_status_date_idx`

Örnek sorgu (prod DB, 5 Ağustos 2026):

```text
EXPLAIN SELECT id FROM attendance_records
WHERE company_id IN (1,2)
  AND date BETWEEN '2026-01-01' AND '2026-12-31';

Index Scan using attendance_records_company_id_source_index
  Index Cond: (company_id = ANY ('{1,2}'::bigint[]))
  Filter: (date BETWEEN ...)
```

Yeni migration gerekmedi (indeks mevcut + Index Scan).

---

## Belge

- `SISTEM_ISLEYIS.md` — portal tek-personel; G2 kuralları
- `FAZ_G_RAPOR.md` — G2 işaretlendi
- `GUNCEL_DURUM_RAPORU.md` — G2 ✅
