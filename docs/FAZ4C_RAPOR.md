# FAZ 4C — Bildirim Merkezi

Branch: `faz4-form-engine` · Push yok (lokal commit)

---

## 4C-1 — Bildirim Merkezi çekirdeği

### Adım 0 — Teşhis

| Madde | Bulgu |
|-------|--------|
| Stub | `ApprovalRequestedNotification` → sadece database; mesaj hardcode |
| FE zil | Company/Portal header’da sahte badge; API bağlı değildi |
| Event | `ApprovalRequested(record, approver, approvable, step)` |
| Onaylandı/red/iade event | Yok (model hook + ActivityLog); `approval.returned` tetikleyici henüz yok |
| Tercihler | theme/locale/density; kanal tercihi yoktu |

### Olay kataloğu (9)

| Key | Grup | Panel |
|-----|------|-------|
| `approval.requested` | approvals | company |
| `approval.approved` | approvals | portal |
| `approval.rejected` | approvals | portal |
| `approval.returned` | approvals | portal (katalogda; tetikleyici yok) |
| `leave.approved` / `leave.rejected` | requests | portal |
| `expense.approved` / `expense.rejected` | requests | portal |
| `onboarding.task_assigned` | tasks | company |

### Servis / TODO

| Parça | Durum |
|-------|--------|
| `NotificationService::notify` | ✅ tek giriş |
| `NotificationMail` (queued) | ✅ tek generic Mailable |
| `TenantDatabaseChannel` + `company_id` | ✅ |
| Workflow `notifyApprover` | ✅ event → listener → NotificationService |
| `ApprovalRecord` reject/complete | ✅ sahibe `leave.*` / `expense.*` / `approval.*` |
| Vekalet | ✅ vekil + asıl onaycı |
| Eski stub | deprecated; listener servisi kullanır |

### Tercihler

`users.preferences.notifications.email.{approvals,requests,tasks}` — varsayılan açık.  
in-app her zaman; email kapalıysa atlanır. Company Ayarlar + Portal Profil.

### FE

`NotificationBell` (@shared) — company + portal header; okunmamış sayı, liste, tümünü okundu, tıkla → `link`.

### Doğrulama

| Metrik | Sonuç |
|--------|--------|
| `NotificationCenterTest` | **9 passed** |
| Tam suite | **402 passed / 0 fail** (1566 assertions) |
| company + portal `tsc` | **0** |
| Sentinel `admin@demo.test` @ `alatax_hr` | **yes** |
| Wipe / push | **yok** |

### Kapsam dışı (4C-2+)

SMS, push, şablon editörü, FormEngine yüzeyi, `approval.returned` iş akışı tetikleyicisi.

---

## Scheduler — İlk zamanlanmış işler

### İş listesi

| Komut | Zaman | Ne yapar |
|-------|--------|----------|
| `leaves:process-monthly-accruals` | Her ayın 1'i 01:15 | Tüm aktif firmalar → `LeaveCalculationService::processMonthlyAccruals` (firma hata izolasyonu) |
| `leaves:process-year-carryover` | 1 Ocak 02:00 | Yıl sonu devir → `processYearEndCarryover` (geçen yıl → yeni yıl) |
| `documents:process-expiry-alerts` | Her gün 06:30 | `expiry_date` = bugün+30 / +7 → İK + personel `document.expiring` (eşik başına 1 kez) |

Overlap: `withoutOverlapping` + `onOneServer` (üçü de).

### Mimari

| Parça | Not |
|-------|-----|
| Manuel API | Controller → `LeaveCalculationService` (değişmedi) |
| Scheduler | Command → `LeaveAccrualBatchService` → aynı `LeaveCalculationService` |
| Evrak | `DocumentExpiryAlertService` + `document_expiry_alerts` unique(doc, threshold) |
| Katalog | `document.expiring` eklendi |

### schedule:list

```
15 1 1 * *  php artisan leaves:process-monthly-accruals
0  2 1 1 *  php artisan leaves:process-year-carryover
30 6 * * *  php artisan documents:process-expiry-alerts
```

### Doğrulama

| Metrik | Sonuç |
|--------|--------|
| `SchedulerJobsTest` | **2 passed** |
| Tam suite | **404 passed / 0 fail** (1582 assertions) |
| company `tsc` | **0** |
| Sentinel `admin@demo.test` @ `alatax_hr` | **yes** |
| Wipe / push | **yok** (ekleyici migration `document_expiry_alerts`) |
