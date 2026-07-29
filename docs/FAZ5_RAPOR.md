# FAZ 5 — Rapor & Analitik Motoru

Branch: `faz4-form-engine`

---

## D1a — Semantic layer + güvenli query builder (BACKEND)

**Tarih:** 2026-07-29  
**Commit:** `feat(faz5): D1a rapor motoru — dataset registry + güvenli query builder`

### ADIM 0 — Teşhis

| Alan | Bulgu |
|------|--------|
| `saved_reports` | Var (personel config jsonb); dataset_key yoktu → eklendi |
| `/analytics` | Sabit `HrAnalyticsController` sorguları; DataScope yok |
| `employee_dashboards` | Widget JSONB; yalnız Employee aggregation |
| Export | BE CSV/PhpSpreadsheet; FE jsPDF; DomPDF yok |
| Semantic layer | **Yoktu** → D1a ile kuruldu |

### Dataset registry (5)

| Key | Alan (yaklaşık) | DataScope | Custom fields |
|-----|-----------------|-----------|---------------|
| `employees` | ~17 + custom | employee | `custom_fields` |
| `leave_requests` | ~9 + custom | user | `custom_fields` |
| `leave_balances` | 8 | user | — |
| `expense_claims` | ~11 + custom | user | `custom_fields` |
| `job_applications` | ~11 + custom | assigned_to | `form_data` |

### Güvenlik katmanları (bypass yok)

1. Dataset / alan / join / operatör / aggregation **whitelist**
2. `BelongsToCompany` global scope
3. `DataScopeService` (viewer kapsamı — paylaşımda sahip miras alınmaz)
4. Alan izni (`employees.salary.view`) — yoksa alan düşer; sum da 422
5. Değerler yalnızca bound parametre

### API

| Method | Path | Permission |
|--------|------|------------|
| GET | `/api/v1/reports/datasets` | `reports.definitions.view` |
| GET/POST | `/api/v1/reports` | view / create |
| GET/PUT/DELETE | `/api/v1/reports/{id}` | view / edit / delete |
| POST | `/api/v1/reports/{id}/run` | `reports.definitions.run` |
| POST | `/api/v1/reports/preview` | `reports.definitions.run` |

### Test

| Suite | Sonuç |
|-------|--------|
| `ReportEngineSecurityTest` | **8 passed** |
| `ReportEngineApiTest` | **5 passed** |
| Tam suite | **501 passed / 0 fail** |
| 3 SPA `tsc` + lint + sentinel | **0 / PASSED** |
| UI | **yok** (D1b) |
| DB wipe | **yok** |

### DUR / sonraki

- D1b: Company UI (dataset seçici + pivot/chart)
- Kalan dataset’ler (puantaj, eğitim, zimmet, anket)
- `/analytics` sabit sorgularını motor üzerine taşıma
