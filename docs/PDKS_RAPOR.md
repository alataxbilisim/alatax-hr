# PDKS Raporu

## PDKS-1 — QR ile giriş-çıkış (15 Temmuz 2026)

**Branch:** `faz4-form-engine` · **Suite:** 416 passed / 0 fail · company+portal `tsc` 0 · Select sentinel OK · PUSH yok

### Kiosk (Company)

| Öğe | Detay |
|-----|--------|
| Sayfa | `/attendance/kiosk` — MainLayout dışı tam ekran |
| Menü | Puantaj → `nav.timesheetKiosk` (`timesheet.kiosk.view`) |
| Token | `POST /api/v1/attendance/kiosk/token` — 30 sn TTL, ~25 sn’de sessiz yenileme |
| Şube | Seçilebilir `branch_id` → kayıtta tutulur |
| Offline | Bağlantı kopunca görünür uyarı |

### Portal okuyucu

| Öğe | Detay |
|-----|--------|
| Sayfa | `/timesheet/qr` — Puantajım’dan link |
| Kamera | `getUserMedia` + **jsQR** (MIT) |
| API | `POST /api/v1/portal/timesheet/qr-scan` |
| Mantık | `AttendanceClockService::punch` — yoksa giriş, varsa çıkış |
| Fallback | Kamera reddi / destek yok → `t('pdks.cameraDenied')` |

### Güvenlik (pazarlıksız)

| Kural | Uygulama |
|-------|----------|
| Kısa ömür | `expires_at` = now+30s; süresi geçmiş → 422 |
| İmza | `AXPDKS1.{payload_b64}.{hmac}` — `APP_KEY` |
| Tek kullanımlık | `attendance_kiosk_tokens.used_at` + `lockForUpdate` |
| Tenant | Çalışan `company_id` ≠ token `cid` → 422, kayıt yok, token tüketilmez |
| Yetki | Kiosk token: `timesheet.kiosk.view` |

### Kayıt zenginleştirme

`attendance_records`: `source` (qr/portal/manual), `branch_id`, `device_info` (UA kısaltılmış).

### Seed

- `ModuleSeeder`: `timesheet` + `expense-management` (menü/lisans boşluğu kapatıldı)
- `PermissionSeeder`: `timesheet.kiosk.view` (+ admin’e atandı)

### Test

`PdksQrAttendanceTest` (6): geçerli in/out, expired, used, cross-tenant, permission, tampered sig.

### DB / push

Ekleyici migration uygulandı (fresh yok). Lokal commit; push yok.
