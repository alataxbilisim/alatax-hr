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

**Karar (uygulanan):** mevcut invite kolonları + Mailable’lar genişletildi; yeni tablo yok. Token → sha256 + 7g + tek kullanımlık. `must_change_password`. Public accept + company/portal `/invite/:token`.

### Uygulama özeti

| Yol | Davranış |
|-----|----------|
| Davet | `POST /users/invite` veya personel `portal_access_mode=invite` → mail → `POST /auth/accept-invitation` → aktif |
| Anlık şifre | `POST /users` veya `portal_access_mode=set_password` → `must_change_password=true` → `/force-password-change` |
| Erişim | Panel: davette rol seçimi (A0); Portal: `employee` rolü (panel yok) |

### Testler

| Suite | Sonuç |
|-------|--------|
| `InviteAndPasswordOnboardingTest` | davet+accept, tek kullanımlık/expiry, anlık şifre, portal iki mod, permission+tenant, forgot-password |
| Auth / RouteAuthorization / Totp / A1 seed | regresyon yeşil (lokal) |
| CI (Backend + Frontend) | ✅ yeşil — [run 29288579899](https://github.com/alataxbilisim/alatax-hr/actions/runs/29288579899) (`3335646`) |

### Dosyalar

- `InvitationService`, `AuthController::{show,accept}Invitation`
- Migration `must_change_password`
- `ForcedPasswordChangePage` / `InviteAcceptPage` (shared)
- Employee portal `access_mode` + FE formları

**DURUM:** A2 tamam · CI yeşil · ubuntu görsel kontrol sonra.

---

## A3 — Grup/Şirket/Şube yetki hiyerarşisi — Mimari teşhis

**Tarih:** 14 Temmuz 2026 · **Kod yazılmadı** · **Karar bekleniyor**

### Vizyon (hedef)

```
GRUP (holding) → ŞİRKET → ŞUBE → departman → kişi
```

| Rol | Görmesi gereken |
|-----|-----------------|
| Grup İK direktörü | Tüm şirketler + şubeler |
| Şirket müdürü | Kendi şirketi (alt şubeler dahil) |
| Şube yöneticisi | Kendi şubesi |
| Departman yöneticisi | Kendi departmanı (**mevcut** `department`) |
| Çalışan | Kendisi (**mevcut** `own`) |

---

### a) Mevcut tenant modeli

**Sonuç: `companies` = tenant sınırı. 1 auth kullanıcı = 1 şirket. Holding/grup entity yok.**

| Kanıt | Detay |
|-------|--------|
| `BelongsToCompany` | Global scope: `WHERE company_id = auth.user.company_id` (SuperAdmin hariç) |
| `users.company_id` | Tek FK; company switcher yok |
| `Company` model | `parent_*` / `group_*` / `organization_*` yok |
| Spatie | `teams => false` — rol takımları company bazlı değil |
| Auth/FE | `formatUser` tek `company` objesi |

Bir tenant altında birden fazla şirket **tutulamaz** — her `Company` kaydı bağımsız SaaS kiracısıdır. SuperAdmin platform geneli görür; bu “grup İK” değildir.

---

### b) Mevcut DataScope

**Config** (`config/data-scope.php`): admin/hr_manager→`company`, hr_specialist→`department`, manager→`team`, employee→`own`.

**Enum genişlik:** `own < team < department < branch < company` (`DataScopeLevel`).

**Servis** (`DataScopeService`):

| Seviye | Durum |
|--------|--------|
| `company` | Ek filtre yok (BelongsToCompany yeter) |
| `own` / `team` / `department` | Çalışıyor (`user_id` / `manager_id` / `department_id`) |
| `branch` | **Stub:** `whereRaw('0 = 1')` — boş küme. Yorum: *Employee.branch_id yok* |

`branch` enum’da var, rol atanabilir, ama **kullanılamaz**. Policy’lerde branch referansı yok.

---

### c) Employee ilişki zinciri

```
Company ← Employee.company_id
       ← Department.company_id ← Employee.department_id
       ← Branch.company_id     ✗ Employee.branch_id YOK
Employee.manager_id → Employee (team)
Employee.user_id → User
```

Şube kaydı (`branches`) şirket altında var; personel–şube bağı **şemada yok**. `Branch::employees()` / `BranchController` workaround kırık/ölü.

---

### d) İki mimari seçenek

#### SEÇENEK 1 — “Tek tenant, çok şirket”

Holding = tenant bağlamı; içinde birden fazla `companies` satırı. Employee bir `company_id`’ye bağlı. Grup kapsamı = organization altındaki tüm company’ler. `parent_group_id` / `organizations` + DataScope `group`.

| Artı | Eksi / etki |
|------|-------------|
| Grup İK doğal: `company_id IN (org şirketleri)` | Bugünkü varsayım **kırılır**: “1 company = 1 tenant” |
| Lisans/modül org veya şirket düzeyinde modellenebilir | `BelongsToCompany` global scope → “erişilebilir company seti” olmalı |
| Tek DB, join’ler basit | `getCompanyId()` (~50 controller), `company.active` MW, register=1 company, FE tek company — hepsi context/switcher ister |
| | Unique `(company_id, code)` korunur ama authz testleri yeniden yazılır |

**Risk:** Yanlış genişletilmiş scope = **cross-company veri sızıntısı** (Faz 2 güvenlik kırığı).

#### SEÇENEK 2 — “Grup = üst katman; her şirket ayrı tenant”

Her şirket bugünkü gibi bağımsız tenant. Grup membership tablosu; grup İK için **cross-tenant** sorgu.

| Artı | Eksi / etki |
|------|-------------|
| Mevcut tenant izolasyonu satır satır korunur | Grup İK = özel bypass yolu; iki güvenlik modeli |
| Küçük şirket SaaS senaryosu değişmez | N+1 tenant bağlamı, rapor/join zor |
| | Audit/lisans/modül “hangi company?” belirsizliği |
| | SüperAdmin’e benzer güç — Policy/test karmaşıklığı yüksek |

**Risk:** Cross-tenant helper’ın her endpoint’e sızması; unutulan bir `where(company_id)` = sızıntı veya eksik veri.

---

### e) Öneri (karar noktası — onayınız şart)

**Aşamalı yaklaşım önerilir; tek seferde holding kodlanmasın.**

1. **Önce şirket-içi (düşük risk, vizyonun şube katmanı):**  
   `employees.branch_id` + DataScope `branch` gerçek implementasyon + policy/test.  
   → Şube yöneticisi / şirket müdürü (company) / dept / own **mevcut tenant modelinde** tamamlanır. Tenant varsayımı bozulmaz.

2. **Grup (holding) için tercih: SEÇENEK 1’e yakın “organization + çok şirket”.**  
   Gerekçe: Filtre hâlâ `company_id IN (...)` — SEÇENEK 2’deki “tenant sınırını aş” modelinden daha tek-kapılı; Faz 2 DataScope genişletmesiyle uyumlu (`group` > `company`). SEÇENEK 2 çift güvenlik modeli ve cross-tenant kaçış kapısı yaratır.

3. **SEÇENEK 2 önerilmez** (ilk yol olarak): izolasyon “görünürde” sağlam ama grup özelliği her yerde istisna ister; solo bakım + sızıntı riski daha yüksek.

**Net öneri metni:**  
> Kısa vade: company=tenant kalsın + branch DataScope.  
> Orta vade grup: SEÇENEK 1 (`organizations` / `company_groups` + DataScope `group` + kontrollü multi-company scope).  
> SEÇENEK 2 ancak “tamamen ayrı hukuki kiracılar asla ortak DB görünümü istemez” ürün kararıysa.

---

### f) Seçilen yolda etki (SEÇENEK 1 + önce branch varsayımı)

| Alan | Değişiklik |
|------|------------|
| Tablolar (şube önce) | `employees.branch_id` FK + indeks `(company_id, branch_id)` |
| Tablolar (grup sonra) | `organizations` (veya `company_groups`); `companies.organization_id`; opsiyonel `organization_user` / membership |
| DataScope | `branch` doldurulur; sonra `group` seviyesi (`width > company`); `resolve` + `scopeFor*` |
| BelongsToCompany | Group-kapsamlı kullanıcıda `whereIn(company_id, …)` — **en kritik değişiklik**; SuperAdmin deseninden ayrı tutulmalı |
| Permission/Policy | Faz 2 testleri genişletilir (branch + group izolasyon); mevcut own/team/dept/company testleri yeşil kalmalı |
| Migration riski | Branch: düşük. Group: **yüksek** — auth context, FE switcher, register, lisans, tüm controller `getCompanyId` audit |
| FE | Şube: personel formuna branch. Grup: şirket seçici + org bağlamı |

**Faz 2 korunumu:** Branch adımı mevcut testleri bozmadan eklenebilir. Group adımı bilinçli breaking; ayrı faz + güvenlik test paketi şart.

---

### Karar beklenen sorular (size)

1. Holding gerçekten **çok hukuki şirket / tek İK görünümü** mü, yoksa çoğu müşteri **tek şirket + çok şube** mi? (İkincisi → şimdilik yalnız branch yeter.)
2. Grup seçilirse **SEÇENEK 1** onaylıyor musunuz, yoksa SEÇENEK 2’de ısrar mı?
3. Kodlama sırası: önce `branch_id` (ayrı prompt) → sonra group — uygun mu?

**DURUM:** A3 teşhis tamam · **KOD YOK** · push yok · kararınızdan sonra kodlama promptu.

---

## Sonraki

- A3 karar → kodlama (ayrı prompt)
- A1 borçları ROADMAP’te (Zincir 2/3)
