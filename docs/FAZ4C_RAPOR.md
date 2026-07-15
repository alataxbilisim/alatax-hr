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
