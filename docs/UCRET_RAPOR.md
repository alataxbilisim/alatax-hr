# UCRET_RAPOR (B11)

**Branch:** `faz4-form-engine`  
**Tarih:** 2026-07-16  
**Kapsam:** Ücret yönetimi (bordro DEĞİL) — kayıt/geçmiş, bant, zam dönemi

## Zincir tablosu

| Zincir | İçerik | Commit |
|--------|--------|--------|
| Z1 | `salary_records`, employees senkron, backfill, Employee Ücret sekmesi, Portal Ücretim, maskeli audit | `673448b` |
| Z2 | `salary_bands` CRUD + bant göstergesi | `0160494` |
| Z3 | Zam dönemi + onay motoru (`salary_review`) + atomik uygulama + `ApprovalStep` user-entity fix | 7b137cb |

## Doğrulama

| Kontrol | Sonuç |
|---------|--------|
| Suite | **457 passed / 0 fail** (448 + 9 salary) |
| company + portal `tsc` | **0** |
| Select sentinel | **PASSED** |
| DB wipe | **yok** (ekleyici migration) |
| PUSH | **yok** |

## İzinler (mevcut — gevşetilmedi)

- `employees.salary.view` / `employees.salary.edit`
- Resource strip: `EmployeeSensitiveFieldService::SALARY_FIELDS` aynen

## Lookup'lar

| Tip | Değerler |
|-----|----------|
| `salary_change_reason` | initial, annual_raise, promotion, role_change, market_adjustment |
| `salary_review_status` | draft, pending_approval, approved, rejected, cancelled |

## API

- `GET/POST /api/v1/employees/{id}/salary`
- `GET /api/v1/portal/salary` (+ `/{id}` → yabancı id 403)
- CRUD `/api/v1/salary-bands`
- `/api/v1/salary-reviews` (+ items, submit, approve, reject)

## Kurallar

- Bordro/vergi/SGK hesabı **yok**
- Audit: tutar maskeli (`*** güncellendi` / "değişti")
- Zam dönemi atomik: onay → hepsi; red → hiçbiri
- Menü: Personel altında; salary izni yoksa görünmez
- `ApprovalStep`: belirli kullanıcı/rol onayında talep sahibi `user` ilişkisi zorunlu değil (`salary_review` uyumu)
