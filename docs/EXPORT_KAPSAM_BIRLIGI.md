# Export Kapsam Birliği

**Tarih:** 6 Ağustos 2026 · **Branch:** `faz4-form-engine` · **Push:** yok  
**Commit:** bu tur

---

## Ne yapıldı

### Yapısal düzeltme (yama değil)

Her export ucu, ilgili listenin **`baseScopedQuery()`** metodunu kullanır:

| Uç | Controller | Ortak sorgu | Export yazımı |
|----|------------|-------------|----------------|
| Personel CSV | `EmployeeController` | `baseScopedQuery` = company + `scopeForEmployee` + filtreler | `chunkById(500)` + stream |
| Kullanıcı CSV | `UserController` | `baseScopedQuery` = home company + `PanelAccess` + `scopeForUser(id)` + filtreler | `chunkById(500)` + stream |
| Aktivite log CSV | `ActivityLogController` | `baseScopedQuery` = company + `scopeForUser(user_id)` + filtreler | `chunkById(500)` + stream |
| Legacy rapor Excel | `EmployeeReportController` | `baseScopedQuery` → `aggregateData` | stream (aggregate) |

Index sayfalar; export aynı sorguyu chunk’lar. Index’e eklenen kısıt otomatik export’a geçer.

### Alan politikası (User CSV)

`UserController::exportColumnsForUser()` — Employee deseni: `{key, header, value, permission?}`.

### Kalıcı koruma

- `App\Services\Export\ExportEndpointRegistry` — kayıtlı export uçları.
- `ExportEndpointRegistryTest` — provider keys ≡ registry keys; her uçta kapsam dışı satır yok.
- Yeni export → registry + provider; aksi halde fail.

### Testler

- `ExportDataScopeUnificationTest` — index `meta.total` == export CSV satır; OUT marker yok.
- `ExportEndpointRegistryTest` — 4 uç data-provider.

---

## Belgeler (çerçeveleme)

Bu commit’e alındı: `YENIDEN_CERCEVELEME.md`, `ROADMAP` §0, `GUNCEL_DURUM_RAPORU`, `BURADAN_BASLA`, `MODUL_SPEC`, `.cursorrules`, `SISTEM_ISLEYIS`, `ENTEGRASYON_SPEC` (önceki unstaged çerçeve).

---

## Suite

- Odak: `ExportDataScopeUnificationTest` + `ExportEndpointRegistryTest` + `PermissionEnforcementWave1Test` yeşil.
- Tam suite: **771 passed** (3316 assertions).
- Not: ActivityLog’da `user_id=null` sistem logları yalnız company-wide kapsamda görünür (Own/dept filtreler).
