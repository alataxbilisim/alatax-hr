# PDKS Raporu

## PDKS-2 — Vardiya + hesap + düzeltme + raporlar (16 Temmuz 2026)

**Branch:** `faz4-form-engine` · **Suite:** 435 passed / 0 fail · company+portal `tsc` 0 · Select sentinel OK · PUSH yok · DB wipe yok

### Zincir tablosu

| Zincir | Commit | Özet | Test |
|--------|--------|------|------|
| Z1 Vardiya tanım+ata | `a2d9a75` | Shift CRUD, employee-shifts tekil/toplu, FE Vardiyalar/Atama, portal Vardiyalarım, DataScope 403 | `PdksShiftAssignmentTest` (7) |
| Z2 Hesap motoru | `c2aaaba` | `late/early/missing_minutes` migration, `AttendanceCalcService`, clock-out tetik, gece `timesheet:mark-incomplete`, firma varsayılanı | `PdksAttendanceCalcTest` (7) |
| Z3 Düzeltme+rapor | `a614122` | Manuel create/edit UI + zorunlu `reason` + ActivityLog + recalc; raporlar+Excel; manager create/edit seed | `PdksAttendanceCorrectionReportTest` (5) |

### Yetki (DataScope — yeni model yok)

| Rol | Kapsam | Vardiya / düzeltme |
|-----|--------|-------------------|
| admin / hr_manager | company | tümü (+ shifts.*) |
| hr_specialist | department | kendi departmanı |
| manager | team | kendi ekibi; dışı → 403 |
| branch_manager | branch | create/edit + shifts view/create/edit |

### API (yeni)

- `GET/POST/PUT/DELETE /api/v1/shifts`
- `GET/POST /api/v1/employee-shifts`, `POST .../bulk`, `DELETE .../{id}`
- `GET /api/v1/attendance/reports`, `GET .../reports/export`
- create/update attendance: `reason` zorunlu → ActivityLog eski→yeni

### FE

- Company: `/attendance/shifts`, `/attendance/shift-assignments`, `/attendance/reports`
- AttendancePage: ekle/düzenle modalı
- Portal Puantajım: **Vardiyalarım** sekmesi (`GET /portal/timesheet/shifts`)
- Firma Ayarları: `default_work_start/end`, `late_tolerance_minutes`

### Hesap kuralları

- Atanmış vardiya varsa ona göre; yoksa firma varsayılanı
- Tolerans içi gecikme → `present`, late_minutes=0
- Clock-out → calc; kaynak fark etmez (portal/QR/manuel)
- Gece 01:30: dün clock-out’suz → `absent` + missing (idempotent)

### DB / push

Ekleyici migration uygulandı (`late_minutes`, `early_leave_minutes`, `missing_minutes`). Fresh/wipe yok. Lokal 3 commit; **push yok**.

---

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
