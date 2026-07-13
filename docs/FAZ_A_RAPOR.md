# FAZ A — Rapor

**Branch:** `faz4-form-engine`  
**Tarih:** 14 Temmuz 2026  
**Kapsam:** A1 — TR iş hukuku default seed (1B / 2B / 3B)

---

## A1 — Default seed

### Kararlar (uygulanan)

| # | Karar | Uygulama |
|---|--------|----------|
| 1B | leave_types firma kopyası | `system_code` + `is_system` + `deducts_from_annual`; Lookup’a taşınmadı |
| 2B | Hakediş mevcut policy | `accrual_policies.tenure_rules` jsonb (bands + age_overrides); yeni tablo yok |
| 3B | Tatiller mevcut Holiday | Ulusal + Ramazan/Kurban 2026–2028 sabit tablo; `company_id=null` |

### Ne seed’lendi

**İzin türleri (10, firma satırı, `is_system=true`):**  
`annual`, `marriage`, `bereavement`, `maternity`, `paternity`, `nursing`, `sick`, `unpaid`, `adoption`, `travel`

**Hakediş (yıllık türe bağlı):**
- Kıdem: 1y→14, 5y→20, 15y→26
- Yaş: ≤17 veya ≥50 → min 20 gün
- 1 yıl bekleme; `min_split_days=10`; `holidays_excluded_from_leave=true`

**Tatiller:** Yılbaşı, 23 Nisan, 1 Mayıs, 19 Mayıs, 15 Temmuz, 30 Ağustos, 28 Ekim (yarım), 29 Ekim + Ramazan/Kurban 2026–2028

**Lookup ek:** `overtime_type` (normal, fazla, gece, hafta tatili, resmi tatil çalışması) — mevcut stage/work_type dokunulmadı

### Bağlantılar

- `AuthController::register` + SuperAdmin `CompanyController::store` → `DefaultCompanyHrSeedService::ensureForCompany` + tatil seed
- `php artisan alatax:seed-defaults` — idempotent backfill (HR defaults + leave workflow)
- `LeaveTypeSeeder` → aynı servis

### Hakediş mantığı

`AccrualPolicy::calculateAnnualEntitlement($years, $age)`:
1. `years < waiting_period_years` → 0  
2. Kıdem bandı (`getDaysForTenure`)  
3. Yaş override → `max(days, min_days)`

### Testler

| Suite | Sonuç |
|-------|--------|
| `AccrualPolicyEntitlementTest` | 3→14, 8→20, 16→26, 52y/3→20, 17y/1→20 |
| `DefaultCompanyHrSeedTest` | register 10 tür + policy + tatil; K-A etiket; idempotent; tenant; overtime lookup |
| Wave2 / Lookup | korundu (CI) |
| CI (Backend + Frontend) | ✅ yeşil — [run 29286408860](https://github.com/alataxbilisim/alatax-hr/actions/runs/29286408860) (`0786241`) |

### Dosyalar

- `database/migrations/2026_07_14_000001_add_system_fields_to_leave_types_table.php`
- `app/Services/DefaultCompanyHrSeedService.php`
- `app/Console/Commands/SeedCompanyDefaultsCommand.php`
- `Holiday::seedTurkishHolidays*` genişletmesi
- `AccrualPolicy::calculateAnnualEntitlement`

**DURUM:** A1 tamam · CI yeşil · ubuntu görsel kontrol sonra.

---

## A2 — Davet & şifre ile ekleme

### Adım 0 — Teşhis (kod öncesi)

| Konu | Mevcut | Boşluk |
|------|--------|--------|
| Panel kullanıcı oluştur | `UserController::store` — şifre zorunlu, aktif | `must_change_password` yok |
| Panel davet | `POST /users/invite` + `UserInvitation` mail → `/invite/{token}` | Kabul API + SPA **yok**; token bcrypt hash (lookup zor) |
| Personel portal | `create_portal_access` → random şifre + mail + API’de `temporary_password` | Davet/anlık seçenek yok; `/invite` ölü link |
| Forgot-password | `password_reset_tokens` + SPA reset | Çalışıyor — bozulmamalı |
| Mail | Queued Mailable’lar; firma adı metin; logo yok | Bildirim Merkezi yok → mevcut şablonu genişlet |
| A0 panel rol | `grantPanelAccess` / Users UI | Davet/şifre ile birlikte seçim yok |

**Karar (uygulama):** mevcut invite kolonlarını + Mailable’ları genişlet; yeni tablo yok. Token → sha256 saklama + 7g expiry + tek kullanımlık. `must_change_password` bayrağı. Public `accept-invitation` + company/portal `/invite/:token`.

**DURUM:** A2 uygulama devam ediyor · push yok (faz sonu tek push).

---

## Sonraki (henüz yok)

- A3+ (B0 merge sonrası)
