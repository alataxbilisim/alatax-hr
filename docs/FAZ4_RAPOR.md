# FAZ 4 — Teşhis Raporu

**Branch:** `faz4-form-engine` (temel: `faz3-tasarim` / `b83da8b`; Faz 3 kodu üstünde)  
**Tarih:** 12 Temmuz 2026  
**Kapsam:** Lookup Engine + Select yayılım + otonom ADIM 0–6 (gece çalışması).

---

## Test Turu Kritik Bulgu Düzeltmeleri (13 Temmuz 2026)

**Durum:** Uygulandı · güvenlik açığı yoktu (BE zaten 403) · UX/erişim/CSS.

| # | Düzeltme | Durum | Kanıt |
|---|----------|-------|-------|
| 1 | Scroll `.page-content` → `overflow-y: auto` | ✅ | `company.css`; portal/superadmin’de aynı kırık yok |
| 2 | Panel erişimi (izin tabanlı) login | ✅ | `PanelAccess` + Auth login 403 `panel_access_denied` + FE yönlendirme |
| 3 | FE `PermissionProtectedRoute` employees/users/roles | ✅ | `App.tsx` `withPermission`; EmployeesPage buton `usePermission` |
| 4 | `/users` yalnızca panel erişimli | ✅ | `PanelAccess::constrainUsersQuery` |

**Kavram:** Panel = portal-self dışı izin / admin / company_admin. Portal-only `employee` rol seti panele giremez, `/users`’ta görünmez. İK (hr_manager) çift erişimli — panel + portal.

### Scroll — kalan kırıklar (tur 2)

**Kök:** `.page-content { display:flex }` çocukları shrink edip scrollbar üretmiyordu.

| Sayfa / alan | Düzeltme |
|--------------|----------|
| Ayarlar, /account, listeler, formlar, personel detay | Block scroll (varsayılan `.page-content`) |
| Kanban (`/recruitment/applications`) | `.page-fill` + sütun içi scroll |
| BI rapor (`/employees/reports`) | `.page-fill`; `100vh` kaldırıldı |
| Lookups sidebar | sticky + iç scroll (bilinçli) |

**Ortak kural:** Scroll = `.page-content`. Tam yükseklik UI = `.page-fill`.

**Görsel test listesi (1366×768):** dashboard, personel liste/detay, izin, `/settings`, `/account/*`, kanban, uzun formlar, lookups.

### Personele panel rolü (tur 2)

| Parça | Detay |
|-------|--------|
| BE | `GET /users/portal-candidates`, `POST|DELETE /users/{id}/panel-access` (`management.users.view|edit`) |
| FE | `/users` → “Personele Panel Erişimi Ver” + satırda kaldır; “Panel” rozeti |
| Test | `PanelAccessControlTest` +4 → toplam 9 |

**Test:** PanelAccess 9 + Wave2/Policy yeşil. FE lint+build 3 SPA ✅.

**Kullanıcı görsel kontrol:**
1. Tüm ana sayfalarda scroll (yukarı liste)
2. Sadece-portal → Company panele giremez
3. Yetkisiz `/employees/new` → Erişim Engeli
4. `/users` portal-only yok
5. Personele `hr_specialist` ata → panel+portal; kaldır → panel yok

**DURUM:** Kod hazır · görsel kontrol sizde.

---

## Test Turu Kritik Bulgu Teşhisi (13 Temmuz 2026)

**Kaynak:** Kullanıcı Ubuntu/local test turu · **Branch:** `faz4-form-engine` · **Düzeltme:** yok (yalnızca kök neden).

| # | Bulgu | Sınıf | Backend koruyor mu? |
|---|--------|-------|---------------------|
| 1 | Portal personeli Company panel + Kullanıcılar listesinde | **UX / yönlendirme + ürün tasarım belirsizliği** (admin işlem = güvenlik açığı DEĞİL) | Evet — admin endpoint’ler **403** |
| 2 | Scroll hiçbir sayfada yok | **Global CSS layout** | N/A |
| 3 | Yetkisiz sayfa açılıyor, API 403 | **FE route/aksiyon guard eksik** (Bulgu 1 ile aynı kök) | Evet — **403** |

---

### BULGU 1 — Personel hem panel hem portal + Kullanıcılar’da

#### 1) Portal erişimi verilince ne oluyor?

| Alan | Değer | Kanıt |
|------|-------|-------|
| User.type | **`user`** (`UserType::User`) — `company_admin` **değil** | `EmployeeController` store/portal-access: `type => 'user'` |
| Spatie rol | **`employee`** | `assignRole('employee')` |
| Bağlantı | `Employee.user_id` set | aynı |
| Enum | `super_admin` / `company_admin` / `user` | `backend/app/Enums/UserType.php` |

Portal davet maili `:3003` portal URL’sine gider (`EmployeeInvitation`). Panel daveti (`UserController@invite`) ayrı akış; yine `type=user` ama rol davette seçilir.

`employee` rol izinleri (özet): `employees.list.view`, izin create/view, doküman/eğitim/performans view — **create employee / users / roles / departments yok** (`PermissionSeeder`).

#### 2) Kullanıcılar (`/users`) kimi listeler?

- **API:** `User::where('company_id', …)` — **type/rol/portal filtresi yok** → portal personeli de gelir (`UserController@index`).
- **FE:** ekstra filtre yok (`UsersPage`).
- **Tasarım ne olmalı?** Kodda “yalnızca panel kullanıcıları” kuralı **yok**. Ürün olarak ayrım isteniyorsa karar gerekir.

**KARAR BEKLENİYOR:** `/users` listesi (a) tüm `users` (mevcut), (b) yalnızca panel rolleri (`admin`/`hr_*`/`manager`…), (c) portal personeli ayrı sekme/badge ile mi gösterilsin?

#### 3) Neden Company paneline (:3002) girebiliyor?

| Katman | Davranış |
|--------|----------|
| Backend login | `portal_login` yoksa normal `auth-token` verir; portal personelini **engellemez** (`AuthController@login`) |
| Company LoginPage | `type` ∈ `{company_admin, user}` → dashboard’a alır (`LoginPage.tsx` ~53–66) |
| `ProtectedRoute` | Sadece auth + `super_admin` dışarı; **portal personeli engeli yok** |
| `CompanyAdminOnly` | `UserType::User` soft-pass; asıl kapı `permission:` middleware (`CompanyAdminOnly.php` yorum + L38–40) |
| `portal.access` | Yalnızca `/api/v1/portal/*` — panel API’sine uygulanmaz |

**Panele girince ne yapabilir?** Sidebar, Spatie izinlerine göre kısmen görünür (`employees.list.view` → Personel modülü rail’de belirebilir). Yönetim/users menüsü (`management.users`) rolünde yok → sidebar’da gizlenir. URL ile `/users` açıksa sayfa render olur, API **403**.

#### 4) GÜVENLİK AÇIĞI mı, UX sorunu mu? — NET CEVAP

**UX / yönlendirme sorunu (panel ayrımı eksik).** Admin / yetkisiz işlemler için **güvenlik açığı değil** — Faz 2 backend enforcement çalışıyor.

**Kanıt (çalıştırıldı, 13 Tem 2026):**

| Endpoint | Portal `employee` token | Sonuç |
|----------|-------------------------|--------|
| `GET /api/v1/users` | Sanctum actingAs | **403** |
| `GET /api/v1/roles` | | **403** |
| `GET /api/v1/custom-fields?entity_type=employee` | | **403** |
| `POST /api/v1/employees` | | **403** |
| `GET /api/v1/employees/departments` | | **403** |
| `GET /api/v1/employees` | rolde `employees.list.view` var | **200** (DataScope **own** — başkasını görmez) |

Kalıcı suite: `RouteAuthorizationTest::test_employee_authorization` → users/roles/company **403**; `PolicyDataScopeWave2Test::test_employee_own_sees_self_not_others` → own scope.

**Nüans:** Personel panelde oturum açıp **kendi izin setindeki** endpoint’leri (ör. kendi personel kaydı liste/view, kendi izin talebi) çağırabilir — bu rol tasarımı. Asıl risk: yanlış panele girmek + boş/403’lü ekranlar (kötü UX), yanlışlıkla “yöneticiyim” algısı.

#### Düzeltme önerisi (uygulanmadı)

1. Company login: `Employee` kaydı olan + yalnızca `employee` rolü → portal URL’ye yönlendir / giriş reddi (veya `portal_only` bayrağı).
2. Backend opsiyonel: company panel login’de `portal_login=false` iken “yalnızca employee rolü + employee kaydı” → 403.
3. `/users` için KARAR sonrası filtre (portal personeli ayır veya badge).
4. FE: Bulgu 3 ile birlikte route `PermissionProtectedRoute` yayılımı.

---

### BULGU 2 — Scroll hiçbir sayfada çalışmıyor

#### Kök neden

Company shell **viewport-locked** ama scroll container unutulmuş:

```
.main-content  → height/max-height: 100vh; overflow: hidden   (company.css ~490–499)
.page-content  → flex:1; min-height:0; overflow: hidden       (company.css ~605–612)  ← asıl kırık
```

İçerik taşar; dikey scroll üretilecek yer yok. Portal/SuperAdmin body-scroll kullanıyor; company farklı.

#### Ne zaman başladı?

**Faz 0** (`dab902b`, 2026-07-10) — `.page-content { overflow: hidden }` ilk kez.  
Faz 3 (dual sidebar / density) ve Faz 4 (Ayarlar Stüdyosu) overflow zincirini **değiştirmedi**; uzun stüdyo/lookup sayfaları sorunu daha görünür kıldı.

Mobil `≤480px` istisnasında `overflow: auto` var; desktop’ta yok.

#### Düzeltme önerisi (uygulanmadı)

```css
.page-content {
  overflow-y: auto;   /* hidden → auto */
  overflow-x: hidden;
}
```

`.main-content` 100vh + overflow hidden kalabilir (sticky header shell). Dashboard/kanban gibi iç scroll’lu sayfalar ayrı smoke.

---

### BULGU 3 — Sayfa açılıyor, API 403

#### Kök neden

**FE route RBAC eksik; BE doğru.**

| Katman | Employees | Lookups (iyi örnek) |
|--------|-----------|---------------------|
| Sidebar filtre | Var (`moduleNav`) | Var |
| Route guard | Yalnızca `ProtectedRoute` (auth) | `PermissionProtectedRoute` |
| Sayfa butonu | “Yeni Personel” guard’sız | `usePermission` |
| Backend | 403 | 403 |

Kanıt: `App.tsx` employees bloğu “No module restriction” + sadece `ProtectedRoute` (~218–250). `/employees/new` sidebar’da yok; `EmployeesPage` butonu `usePermission` kullanmıyor. `EmployeeForm` mount’ta `departments` + `custom-fields` çağırır → konsol 403.

`ModuleProtectedRoute` = **lisans** kontrolü, permission değil. `PermissionProtectedRoute` company’de neredeyse sadece lookups + settings/custom-fields.

#### Bulgu 1 ile bağlantı

**Aynı kök:** authenticated ≠ authorized. Portal personeli panele girer (1) → URL/butonla yetkisiz sayfa açar (3) → BE 403.

#### Düzeltme önerisi (uygulanmadı)

1. Employees (ve diğer çekirdek) route’larını `PermissionProtectedRoute` ile sar.
2. Liste aksiyon butonlarında `usePermission` (`create`/`edit`/`delete`).
3. Yetkisiz route → `/dashboard` veya “Yetkiniz yok” sayfası (403 toast değil sessiz boş form).
4. Opsiyonel: `EmployeeForm` custom-fields için `employees.custom_fields` vs `management.custom_fields` endpoint tutarlılığı (ayrı teknik borç).

---

### Öncelik sırası (düzeltme turu için öneri)

1. **Bulgu 2** — tek CSS satırı, tüm sayfalar (en hızlı win).
2. **Bulgu 1 login yönlendirme** — portal personeli :3002’ye girmesin.
3. **Bulgu 3 route/button guards** — employees + management rotaları.
4. **KARAR:** `/users` listesinde portal personeli politikası.

---

## GECE ÖZETİ — Faz 4B B0→B3 (13 Temmuz 2026)

| Adım | Durum | Commit |
|------|-------|--------|
| A / B0 Motor bağla (izin) | ✅ | `f60623d` |
| B / B1 Sıralı çok adım + resubmit | ✅ | `417e4c0` |
| C / B2 Dinamik + hr fallback + vekalet | ✅ | `ac04eb1` |
| D / B3 Koşullu adım | ✅ | `3f9de4c` |
| E Derin analiz (snapshot/TEST_TURU/ROADMAP) | ✅ | (bu commit) |

**Motor testleri:** 15 passed (`ApprovalWorkflowMotorB0–B3`).  
**Faz 2 Policy:** `LeaveRequestPolicyDataScopeTest` yeşil — zayıflatılmadı.  
**CI:** ✅ yeşil — https://github.com/alataxbilisim/alatax-hr/actions/runs/29217151159 (`ca550dc`)

### KARAR BEKLENENLER

Yok (kararlar 1–7 uygulandı). B4 paralel runtime / B5 Stüdyo UI bilinçli ertelendi.

---

## Faz 4B-B4 — Paralel onay grupları + eskalasyon (15 Temmuz 2026)

**Branch:** `faz4-form-engine` · **Suite:** 410 passed / 0 fail · company `tsc` 0 · Select sentinel OK · PUSH yok

### Teşhis (kısa)

| Konu | Durum |
|------|--------|
| Şema | `parallel_group` (nullable int), `completion_policy` (`all`/`any`, default `all`) — runtime yoktu |
| İlerletme | `ApprovalRecord::moveToNextStep` tek pending açıyordu → `ApprovalFlowEngine` |
| Güvenlik | Policy/controller `first()` paralelde yanlış kayda bakabilirdi → `findPendingRecordForActor` (yetki genişlemez) |

### Delegasyon (paralel)

| Durum | Davranış |
|-------|----------|
| Vekil | `findApprover` vekili yazar; `canApprove` vekili kabul eder |
| Asıl | 4C-1 `notifyApprovalRequested` çift hedef |
| Eskalasyon | Yetki **devretmez**; yalnız bildirim |

### completion_policy

| Policy | Açılış | İlerleme | Red |
|--------|--------|----------|-----|
| `all` | Grup adımları aynı anda pending | Hepsi onaylanınca sonraki dalga | Herhangi red → rejected + kalan skip |
| `any` | Aynı | Biri onay → kardeşler auto-skip (audit) + ilerler; yarışta tek ilerleme (`lockForUpdate`) | Aynı |
| `parallel_group` NULL | Tek adım | B0–B3 sıralı davranış | Değişmedi |

### Eskalasyon

| Kural | Detay |
|-------|--------|
| Kaynak | `approval_steps.escalation_days` veya workflow `escalation_days` (nullable = kapalı) |
| Eşik | pending gün ≥ N → `approval.reminder` (onaycıya) |
| Eşik+2 | → `approval.escalated` (üst yönetici; yoksa company admin/İK) |
| İdempotent | `approval_escalation_alerts` unique `(record, alert_level)` |
| Schedule | `approvals:process-escalations` günlük 07:00 |

### Katalog

- `approval.reminder` / `approval.escalated` (+ `messages.notifications.*`)

### Görünürlük

- Leave `show` → `approval_records` + step `parallel_group`
- Company LeavesPage detay: paralel grup etiketi (`t()`)

### Test

| Suite | Sonuç |
|-------|--------|
| `ApprovalWorkflowMotorB4Test` (6) | ✅ |
| B0–B3 + `LeaveRequestPolicyDataScopeTest` | ✅ |
| Tam suite | **410 passed** |

### KARAR BEKLENENLER

Yok.

---

### Yarın önce bak (kullanıcı testi — 5 madde)

1. Demo firmada `php artisan approvals:seed-default-leave-workflows` çalıştır; yeni izin talebi oluştur → yöneticide bildirim/kuyruk.
2. Yönetici leave approve → bakiye düşüşünü kontrol et.
3. İki adımlı test flow (Stüdyo yok: DB/seeder ile) — adım2 erken onay 403.
4. Reddet → `resubmit` → ikinci `approval_instances` satırı.
5. 3 günlük vs 15 günlük izin — GM koşul adımı (B3).

---

## Faz 4B — Gece Otonom İlerleme (13 Temmuz 2026)

| Adım | Durum | Not |
|------|-------|-----|
| **A / B0** Motoru bağla (pilot: İzin) | ✅ | findApprover Employee.manager; `approval_instances`; default seed; leave köprü; event stub |
| **B / B1** Sıralı çok adım | ✅ | 2+ adım; red→resubmit yeni instance; adım2 erken onay 403 |
| **C / B2** Dinamik onaycılar + vekalet | ✅ | 3 kademe; unresolved→hr_manager (atlanmaz); vekalet motor bağlı |
| **D / B3** Koşullu adım | ✅ | whitelist evaluator; 3g→GM atla; 15g→GM zorunlu |
| **E** Derin analiz (snapshot/TEST_TURU/ROADMAP) | ✅ | `docs/PROJECT_SNAPSHOT.md` + TEST_TURU 4B + ROADMAP B0–B3 |

### B3 özeti

- `ApprovalStepConditionEvaluator`: field+op whitelist, `eval` yok.
- Koşul tutmayan adım → `STATUS_SKIPPED` audit kaydı (atlanır, sonraki adıma değil “geçiş”).
- Test: `ApprovalWorkflowMotorB3Test` (3).

### B2 özeti

- `dynamic_manager` / `dynamic_skip_manager` / `role` / `user` (+ legacy).
- Yönetici yok: adım atlanmaz; `approval.approver.unresolved_hr_fallback` + `hr_manager` atanır.
- Vekalet: `findApprover` + `canApprove` — motor uçtan uca test yeşil.
- Test: `ApprovalWorkflowMotorB2Test` (3).

### B1 özeti

- Sıralı adımlar: `moveToNextStep` → sonraki onaycı çözülür + `ApprovalRequested`.
- Red: herhangi adımda → instance rejected + entity `onWorkflowRejected`.
- Yeniden gönder: `POST /leaves/requests/{id}/resubmit` → `prepareForResubmit` + `resubmitWorkflow` (yeni instance, eski geçmiş korunur).
- Test: `ApprovalWorkflowMotorB1Test` (3) + Policy suite yeşil.

### B0 özeti

- **findApprover:** `Employee.manager_id` → `manager.user`; `dynamic_manager` / `dynamic_skip_manager` / `role` / `user` (+ legacy alias).
- **Şema:** `approval_steps.condition` jsonb, `parallel_group`, `completion_policy`; `approval_instances` (polymorphic+tenant); `approval_records.approval_instance_id`.
- **Seed:** `DefaultLeaveApprovalWorkflowService` + `approvals:seed-default-leave-workflows`; register’da otomatik.
- **Köprü:** `LeaveRequestController` store→`startWorkflow`; approve/reject→motor (`processAuthorizedApproval`) veya legacy + warning log. Policy kapısı korunur (motor bypass yok).
- **Event:** `ApprovalRequested` → `SendApprovalRequestedNotification` (database stub).
- **Legacy:** akış yoksa otomatik onay **KAPALI**; pending + `Log::warning`.

### B0 testler

| Suite | Sonuç |
|-------|--------|
| `ApprovalWorkflowMotorB0Test` (6) | ✅ |
| `LeaveRequestPolicyDataScopeTest` (7) — Faz 2 | ✅ yeşil (zayıflatılmadı) |
| `PolicyDataScopeWave3Test` approvals | ✅ |
| `PermissionEnforcementWave2Test` leaves | ✅ |

### KARAR BEKLENENLER (B0)

Yok — kararlar 1–7 uygulandı.

---

## Gece İşi Doğrulama (12 Temmuz 2026 — akşam)

**Amaç:** Gece eklenen 6 özelliğin CI derlemesi değil, **mantık** kanıtı. Yeni özellik yok; test derinleştirme + bulunan kırık düzeltme.  
**KARAR BEKLENENLER’e dokunulmadı:** başvuru kaynağı FE↔BE, hired→onboarding otomasyonu, expense/request CRUD.

### Suite özeti

| Kapsam | Sonuç |
|--------|--------|
| Doğrulama filtresi (Kanban+Lookup+CF+2FA) — host sqlite | **43 passed** (237 assertions) |
| Select empty contract (`assert-select-empty-contract.mjs`) | **PASSED** |
| Tam suite Docker Postgres (CI eşdeğeri) | **221 passed**, 1 risky (875 assertions) — ~7 dk |
| Host sqlite tam suite | Timesheet fail (date `where` sqlite) — CI Postgres’te geçiyor; bu turda dokunulmadı |

### Commit

`test(faz4): gece işi doğrulama — kanban/2fa/customfield/lookup/select testleri` (`ba772a5`)  
+ `fix(faz4): CI engelleri — pint style + portal 2FA login lint` (`255f110`)  
+ SurveyTest LookupSeeder (Grup 3 regressiyon)

### CI

| Run | SHA | Süre | Sonuç |
|-----|-----|------|--------|
| [#58](https://github.com/alataxbilisim/alatax-hr/actions/runs/29206290752) | `ba772a5` | ~46s | ❌ Pint + Portal lint |
| [#59](https://github.com/alataxbilisim/alatax-hr/actions/runs/29206447447) | `255f110` | **2m 50s** | FE ✅ / BE ❌ `SurveyTest` (lookup seed yok → type 422) |
| [#60](https://github.com/alataxbilisim/alatax-hr/actions/runs/29207293046) | `0f4e70b` | **~3m 6s** (20:12:53→20:15:59Z) | ✅ **yeşil** — Backend Pint+PHPUnit + Frontend lint+build |

**Ek kırık:** Grup 3 sonrası `SurveyTest` LookupSeeder’sız `satisfaction` gönderiyordu. Fix: setUp’a `LookupSeeder` (test zayıflatılmadı).

https://github.com/alataxbilisim/alatax-hr/actions?query=branch%3Afaz4-form-engine

### 1. Kanban hibrit (`application_stage`) — kritik

| Soru | Öncesi | Sonrası |
|------|--------|---------|
| Gerçek mantık testi var mıydı? | Kısmen: `LookupTest::application_stage_hybrid_matches_job_application_status_enum` (seed/enum eşleşmesi). **K-A kanban rename / geçiş / pasif / silme kilidi yoktu.** | **YENİ** `ApplicationStageKanbanTest` (4 test) |

**EKLENEN testler**

- `ka_label_color_change_does_not_mutate_application_status_code` — label+renk değişir, başvuru `status`/`system_code` = `new` kalır (K-A).
- `kb_deactivate_stage_keeps_existing_applications_readable` — pasif aşamadaki başvuru okunur; o aşamaya **yeni** geçiş 422.
- `status_transition_updates_system_code_and_rejects_legacy_enum_mismatch` — `new`→`shortlisted` + status log; legacy `interview`/`pool`/`accepted` **red**; `interview_scheduled` kabul (enum uyumsuzluğu kapandı).
- `hybrid_application_stage_cannot_add_or_hard_delete_system_code` — sistem kodu silme/ekleme engelli.

**BULUNAN kırık + düzeltme**

- `ApplicationController::updateStatus` `application_status_logs`’a **yanlış kolonlar** yazıyordu (`company_id`, `status`, `notes` → şema: `from_status`, `to_status`, `note`, `changed_by`). Geçiş “çalışıyor” gibi görünürken log kırılıyordu.
- `JobApplication::changeStatus` enum’u string’e cast etmiyordu → `from_status` bozulabilirdi.
- **Düzeltildi:** controller log + show mapping; model cast.

### 2. 2FA challenge UI

| Soru | Öncesi | Sonrası |
|------|--------|---------|
| Backend challenge/verify testi? | **VAR** — `Totp2faTest` (11 test): 2FA’sız token; challenge; yanlış kod 401; doğru TOTP; recovery single-use; throttle; challenge token API’ye giremez | Aynı suite **yeşil** — ek test gerekmedi |
| FE E2E? | Yok (Playwright/Cypress yok) | Eklenmedi; BE kanıtı yeterli kabul |

2FA’sız login akışı değişmedi (`login_without_2fa_returns_token`).

### 3. Custom field

| Soru | Öncesi | Sonrası |
|------|--------|---------|
| Zorunlu boş → 422? | **VAR** — store/update 422 testleri (`2598300`) | Yeşil |
| Kaydet + detayda görünür? | Detay FE vardı; API show assertion zayıftı | **EKLENDİ:** geçerli select değeri + `GET employees/{id}` → `custom_fields` |
| `field_options` sözleşmesi? | Controller `{value,label}` bekliyor; FE legacy `options` riski | **EKLENDİ:** POST `field_options` kabul; legacy `options: string[]` → boş `field_options` |

### 4. Lookup yönetim UI (`/lookups`)

| Soru | Öncesi | Sonrası |
|------|--------|---------|
| CRUD / sistem 403 / hibrit / K-B / permission? | **VAR** — `LookupTest` 21 test (CRUD permission, system 403, K-A/K-B, hibrit leave/application_stage, tenant) | Yeşil; ek test gerekmedi (UI E2E yok, API kanıtı) |

### 5. Radix Select sentinel (ADIM 0)

| Soru | Öncesi | Sonrası |
|------|--------|---------|
| Boş submit ≠ ilk öğe? | Kodda sentinel vardı; otomatik sözleşme testi yoktu | **YENİ** `frontend/packages/shared/scripts/assert-select-empty-contract.mjs` — boş/sanitize, allowEmpty→sentinel, zorunlu boş→undefined, filtre “Tümü”→boş |

---

## Faz 4B Onay Zinciri Motoru Tasarımı (13 Temmuz 2026)

**Durum:** Teşhis + mimari tasarım — **KOD YOK**. ROADMAP §4B vizyonu referans.  
**Kaynaklar:** `ApprovalWorkflow` / `ApprovalStep` / `ApprovalRecord` / `ApprovalDelegation`, `WorkflowService`, `LeaveRequestPolicy` / `ExpenseClaimPolicy`, `2024_12_24_000001_create_approval_workflows_table.php`, `docs/ROADMAP.md` §4B, `docs/AKIS_SPEC.md` §0.

### ADIM 1 — Mevcut temel (Faz 2) — koddan

#### 1.1 Şema (zaten var)

| Tablo | Rol | Kritik alanlar |
|-------|-----|----------------|
| `approval_workflows` | Firma akış tanımı | `company_id`, `entity_type`, `name`, `is_active`, `is_default`, `conditions` (jsonb) |
| `approval_steps` | Sıralı adımlar | `step_order`, `approver_type` (direct_manager / department_head / specific_user / specific_role / hr / cfo / ceo), `specific_user_id`, `specific_role`, `timeout_hours`, `timeout_action` (escalate/auto_approve/auto_reject), `can_skip` |
| `approval_records` | Çalışan onay satırı | morph `approvable_*`, `approval_workflow_id`, `approval_step_id`, `approver_id`, `status` (pending/approved/rejected/skipped/escalated), `step_order`, `is_current`, `escalated_at`/`escalated_to`, `comment` |
| `approval_delegations` | Vekalet | `delegator_id`, `delegate_id`, tarih aralığı, `entity_type` nullable |

**Çok adım:** Şema **destekliyor** (`step_order` + `moveToNextStep` → sonraki `ApprovalRecord`).  
**Paralel adım / koşul-adım / skip-level “CEO’ya kadar”:** şemada **yok** (veya yetersiz).  
**Eskalasyon:** kolonlar var; **scheduler/job yok**.

#### 1.2 Motor vs üretim (iki dünya)

| Dünya | Ne | Durum |
|-------|-----|--------|
| A — Workflow API | `WorkflowService::startWorkflow` → `/api/v1/approvals/{id}/approve` | Tablolar + CRUD + Policy hazır; **`startWorkflow` hiçbir modülde çağrılmıyor** (ölü motor) |
| B — Modül route | `POST /leaves/requests/{id}/approve`, expense approve… | **Üretim yolu**; Policy hibrit (aktif record varsa `canApprove`, yoksa DataScope team/department/company) |

**canApprove:** `pending`+`is_current` → Spatie `admin` / `approver_id` / aktif vekil.  
**findApprover bug:** `User->manager` / `Department->head` kullanılıyor; gerçek hiyerarşi `Employee.manager_id` (employee→employee) + `Department.manager_id` (→user). **4B öncesi düzeltilmeli.**

#### 1.3 Modül kullanımı

| Modül | Bugün | Workflow kolonları |
|-------|-------|-------------------|
| İzin | Controller doğrudan `LeaveRequest::approve` — `startWorkflow` yok | `approval_workflow_id`, `current_step`, `workflow_status` var |
| Masraf | Doğrudan status; Policy hazır | morph var, workflow FK yok |
| Doküman / performans / puantaj / işe alım | Kendi status/flag | Workflow bağlı değil |
| Frontend | Workflow UI / api client **yok**; `management.workflows.*` seed’de var |

**Verdict:** Tek-seviyeli **DataScope onay** + iskelet çok-adımlı motor (bağlanmamış). Vizyon = motoru **canlandır + genişlet + Stüdyo UI**.

---

### ADIM 2 — Veri modeli önerisi (yazılmadı; öneri)

**Strateji (önerilen): GENİŞLET, yeni paralel evren kurma.** Mevcut `approval_workflows` / `_steps` / `_records` / `_delegations` korunur; eksik alanlar migration ile eklenir. İsimler kullanıcı vizyonundaki `approval_flows` ile eşdeğer — rename zorunlu değil (breaking); Stüdyo’da “Akış” etiketi kullanılır.

| Vizyon adı | Mevcut / öneri | Değişiklik |
|------------|----------------|------------|
| `approval_flows` | = `approval_workflows` | `version`, `priority` (koşullu seçim sırası); `conditions` jsonb net şema |
| `approval_flow_steps` | = `approval_steps` | `condition` jsonb (adım-seviye), `parallel_group` (aynı order = paralel), `approver_type` genişlet (`skip_level_n`, `dynamic_manager_chain`), `escalation_target` |
| `approval_instances` | Kısmen leave `workflow_status` + records | Opsiyonel yeni tablo **veya** entity kolonları standart trait: `workflow_status`, `approval_workflow_id`, `current_step` — **KARAR** |
| `approval_step_actions` | ≈ `approval_records` satır geçmişi | Record zaten aksiyon; gerekirse `action` enum ayrımı + audit zenginleştir |
| Vekalet | `approval_delegations` | Koru; motor her çözümlemede `findActiveDelegate` |

**Faz 1 kuralları:** string + PHP enum + CHECK; koşullar/config **jsonb**; her tabloda `company_id` + BelongsToCompany; softDeletes workflow tanımında kalsın.

**Köprü önerisi:** Leave/Expense `approve` endpoint’leri → içeride `WorkflowService` (record varsa) veya `startWorkflow` yoksa hata / otomatik tek-adım default flow seed. İki dünyayı birleştir.

---

### ADIM 3 — Motor mantığı (pseudo-flow)

```
TALEP OLUŞTU (örn. LeaveRequest::store)
  → context = { total_days, department_id, requester_id, … }
  → flow = findForRequest(company, entity_type, context)  // conditions + is_default
  → yoksa: seed default 1-adım (direct_manager) VEYA onWorkflowCompleted (politika KARAR)
  → instance: entity.workflow_status = in_progress; current_step = 1
  → step = first (veya parallel_group)
  → her onaycı = resolve(approver_type) → applyDelegation → ApprovalRecord(pending, is_current)
  → notify(approver)  // 4C veya geçici in-app

ONAY
  → canApprove? → record.approved
  → parallel_group içinde tüm required pending bitti mi?
      hayır → bekle
      evet → next step / next group
          → yok → onWorkflowCompleted() (bakiye düş vb.) + notify(requester)
          → var → resolve + records + notify

RED
  → record.rejected; entity.workflow_status = rejected; onWorkflowRejected()
  → notify(requester + reason); yeniden gönder → yeni instance veya reset (KARAR)

ESKALASYON (scheduler)
  → pending + timeout_hours aşıldı → timeout_action
      escalate → üst yönetici / escalation_target; escalated_at
      auto_approve / auto_reject

DİNAMİK ÇÖZÜM
  direct_manager    = requester.employee.manager.user_id
  department_head   = requester.employee.department.manager_id (User)
  skip_level_n      = manager zincirinde N adım
  specific_role     = Spatie role holders ∩ DataScope (opsiyonel dept filtresi)
```

---

### ADIM 4 — Kapsam + sıra (parçalama)

| Dalga | İçerik | Çıktı |
|-------|--------|--------|
| **B0** | `findApprover` düzelt; entity mapping (`ExpenseClaim`); Leave `store` → `startWorkflow`; leave approve → `/approvals` veya service köprüsü | İzin gerçekten workflow’dan geçer |
| **B1** | Sıralı çok-adım (tek onaycı/adım) + seed default flow + feature test | 2–3 adımlı izin zinciri |
| **B2** | Dinamik onaycı (manager / dept head / skip-level) | Hiyerarşi doğru |
| **B3** | Adım/flow koşulları (days>10 → ekstra adım) | Vizyon “GM eşiği” |
| **B4** | Paralel grup + eskalasyon job + vekalet polish | SLA + hibrit sıra |
| **B5** | Ayarlar Stüdyosu → Modül Ayarları görsel tasarımcı | Firma elle kurar |
| **B6+** | Expense / document / … bağlama | Yayılım |

**Pilot modül:** **İzin** (`leave_request`) — en somut, kolonlar hazır, bakiye sonucu net.

**Bildirim (4C bağımlılığı):**
- B0–B1: mevcut `ActivityLog` + basit in-app/mail stub (TODO kapat, 4C beklenmez).
- 4C gelince: olay kataloğu `approval.requested|approved|rejected|escalated` → şablon/kanal.

**Departman erişimi:** DataScope `department` onay listesinde kalır; “yönetici panel erişimi” **Faz 6** (ROADMAP).

---

### KARAR NOKTALARI (onay beklenir)

1. **Şema:** Mevcut tabloları genişlet (öneri) mi, `approval_flows` diye rename/yeni mi?
2. **Instance:** Ayrı `approval_instances` tablosu mu, entity trait kolonları yeter mi?
3. **Workflow yokken:** Talep oluşturunca otomatik 1-adım default seed mi, yoksa “yapılandırılmamış → reddet/engelle” mi?
4. **Köprü:** Modül `/approve` kalsın (içeride WorkflowService) mi, UI tamamen `/approvals/{id}` mi?
5. **Paralel:** `parallel_group` int yeterli mi, yoksa ayrı junction?
6. **Bildirim:** B0’da stub OK mi, yoksa 4C’yi önce mi?
7. **Pilot sırası:** B0→B1→B2→B3→B4→B5 onayı?

Onay örneği: `1=genişlet, 2=trait kolonları, 3=default seed, 4=modül endpoint köprü, 5=parallel_group, 6=stub, 7=B0-B5 sıra OK`.

**Sonraki:** Onay sonrası taze oturumda B0 kodu (bağlantı + findApprover fix) — bu turda uygulama yok.

---

## Ayarlar Stüdyosu Bilgi Mimarisi (12 Temmuz 2026)

**Durum:** Teşhis + IA kararları uygulandı — **iskelet kuruldu** (12 Temmuz 2026 gecesi).  
**Kaynaklar:** `moduleNav.ts`, `App.tsx`, `SettingsPage.tsx`, Portal `ProfilePage`, `themeSlice`, `PermissionSeeder`, `docs/ROADMAP.md` §Faz 4.

### İskelet kurulumu (shipped)

**Kararlar uygulandı:**
1. İsimler: **Ayarlar** (kişisel) + **Yönetim** (stüdyo)
2. ModuleRail: her iki pin **altta**, operasyonel modüllerden görsel ayırıcı ile
3. Modül Ayarları: placeholder deep-link’ler (izin türleri / kategoriler / modül CF)
4. CF: modül sayfaları birincil; `/settings/custom-fields` = index + deep-link
5. 2FA: self (`/auth/2fa/*`) + admin (`/users/{id}/2fa/*`)
6. Kişisel: yalnızca profil / güvenlik / tercihler (bildirim stub yok)

**Backend:** `AuthController` self-2FA (enable/confirm/disable/status/recovery); `preferences.density` validation; feature test `AccountSelfTwoFactorTest`.

**Frontend:** `operationalModuleGroups` + `pinnedModuleGroups`; ContextSidebar grup başlıkları; `/account/*` sayfaları; header → `/account/profile`; i18n `nav` / `account` / `studio`.

**Doğrulama:** `AccountSelfTwoFactorTest` + `Totp2faTest` = 16 passed (Docker pgsql); 3 SPA lint OK; `@alatax/company` tsc+build OK.

**DUR — görsel kontrol:** Rail altı Ayarlar+Yönetim ayracı; Yönetim sidebar grupları (Firma / Kullanıcı & Yetki / Özelleştirme / Modül Ayarları); `/account/security` self-2FA akışı; `/settings/custom-fields` index kartları; kısıtlı rolle Yönetim/grup görünürlüğü.

### ADIM 1 — Teşhis (mevcut — arşiv)

#### 1.1 ModuleRail — tek “Yönetim” grubu

Kaynak: `frontend/apps/company/src/components/layout/moduleNav.ts` → `id: 'management'`.

| Menü etiketi | Route | Nav permission | Route guard (App.tsx) | Sayfa |
|--------------|-------|----------------|----------------------|-------|
| Kullanıcılar | `/users` | `management.users.*` | yalnızca `ProtectedRoute` | `UsersPage` (+ `/users/:id`) |
| Roller | `/roles` | `management.roles.*` | ProtectedRoute | `RolesPage` (+ `/roles/:id`) |
| Şubeler | `/branches` | `management.branches.*` | ProtectedRoute | `BranchesPage` (+ `/branches/:id`) |
| Log & Denetim | `/audit-logs` | `management.audit_logs.*` | + `ModuleProtectedRoute(audit-logs)` | `ActivityLogsPage` |
| **Ayarlar** | `/settings` | `management.settings.*` | ProtectedRoute | `SettingsPage` (**firma** sekmeleri) |
| Listeler | `/lookups` | `management.lookups.*` | + `PermissionProtectedRoute(…lookups.view)` | `LookupsPage` |
| Webhook'lar | `/webhooks` | `management.webhooks.*` | ProtectedRoute | `WebhooksPage` |

**Menüde yok / dağınık:**

| Ne | Nerede | Permission |
|----|--------|------------|
| Özel alanlar (global orphan) | `/settings/custom-fields` — **sidebar linki yok** | `management.custom_fields.*` |
| Özel alanlar (modül) | `/employees|leaves|documents|recruitment|performance|training|assets/custom-fields` | `{module}.custom_fields.*` |
| İzin türleri / tatil / politika | İzinler menüsü (`/leaves/types` vb.) | `leaves.*` |
| Doküman / varlık kategorileri | ilgili modül menüsü | `documents.categories` / `assets.categories` |
| Workflow UI | PermissionSeeder’da `management.workflows.*` var; **Company menü/route yok** | — |
| API Keys | SettingsPage sekmesi; ayrı `management.api_keys.*` | settings sayfası içinde |

Header kullanıcı menüsü “Ayarlar” → **aynı** `/settings` (firma). Kişisel hedefe gitmiyor.

#### 1.2 Kişisel ayarlar — nerede?

| Özellik | Company | Portal | Backend |
|---------|---------|--------|---------|
| Profil (ad/iletişim/foto) | **UI yok** | `/profile` (`ProfilePage`) | Company: `PUT /auth/profile`; Portal: `/portal/profile` (+ avatar) |
| Şifre | **UI yok** | Profil içinde | `PUT /auth/password` / portal password |
| 2FA enable/disable | **Self UI yok**; admin → `UserDetailPage` (`/users/{id}/2fa/*`) | Login challenge var; yönetim UI yok | Admin-scoped 2FA API; self-service endpoint ayrı değil |
| Tema | Header `toggleTheme` | Header toggle | Redux + `localStorage.theme`; `preferences.theme` API’de var ama toggle **yazmıyor** |
| Density | EmployeesPage geçici | Yok | `localStorage.density`; API `preferences.density` **validation yok** |
| Dil | Firma Settings “Genel Ayarlar” (`language`) | Yok | i18n sabit `tr`; kişisel `preferences.locale` API’de kısmen |
| Bildirim tercihi (kişisel) | Yok | Yok | Firma bildirim sekmesi var; kullanıcı olay×kanal (4C) henüz yok |

#### 1.3 Dağınık → nereye taşınmalı? (özet)

| Bugün | Hedef kova |
|-------|------------|
| `/settings` (firma sekmeleri) + Webhooks | **YÖNETİM → FİRMA** |
| Users / Roles / Branches / Audit | **YÖNETİM → KULLANICI & YETKİ** |
| `/lookups`, orphan `/settings/custom-fields`, modül `*/custom-fields` | **YÖNETİM → ÖZELLEŞTİRME** |
| Leaves types/holidays/policies, doc/asset categories, ileride kanban… | **YÖNETİM → MODÜL AYARLARI** (sonraki dalga; route korunabilir) |
| Header “Ayarlar” → `/settings` | **AYARLAR (kişisel)** hub |
| Portal profil/şifre deseni | Company kişisel sayfalar için örnek |

---

### ADIM 2 — Önerilen bilgi mimarisi (tasarım)

#### A) AYARLAR — kişisel (ModuleRail ikonu; herkes)

Self-scope: yalnızca `auth()->id()`. Yeni permission **gerekmez** (veya tek `account.self` — **KARAR**).

| Sayfa | Önerilen path | İçerik | API (mevcut/hedef) |
|-------|---------------|--------|---------------------|
| Profilim | `/account/profile` | ad, e-posta (salt?), telefon, foto | `PUT /auth/profile` (+ avatar gerekirse) |
| Güvenlik | `/account/security` | şifre; 2FA aç/kapat/recovery | `PUT /auth/password`; **self 2FA** (bugün admin API — genişletme veya policy: self) |
| Tercihler | `/account/preferences` | tema, density, dil | `preferences` + localStorage sync |
| Bildirimler | `/account/notifications` | olay×kanal (4C gelince) | şimdilik stub / “yakında” |

Header menü: Profilim / Güvenlik / Tercihler → bu hub; **firma** `/settings` buradan çıkmaz.

#### B) YÖNETİM — Ayarlar Stüdyosu (ModuleRail; rol filtreli)

Mevcut `management` grubunun **yeniden gruplanmış** hali. Route’lar **korunur**; ContextSidebar’da **grup başlıkları**.

```
YÖNETİM
├── FİRMA
│   ├── Genel bilgiler          /settings?tab=general     management.settings.*
│   ├── SMTP / SMS              /settings?tab=smtp|sms    management.settings.*
│   ├── Bildirimler (firma)     /settings?tab=notifications
│   ├── Lisans / Modüller       /settings?tab=license|modules
│   ├── API Keys                /settings?tab=api-keys    management.api_keys.*
│   └── Webhook'lar             /webhooks                 management.webhooks.*
├── KULLANICI & YETKİ
│   ├── Kullanıcılar            /users                    management.users.*
│   ├── Roller                  /roles                    management.roles.*
│   ├── Şubeler                 /branches                 management.branches.*
│   └── Log & Denetim           /audit-logs               management.audit_logs.*
├── ÖZELLEŞTİRME
│   ├── Listeler (Lookup)       /lookups                  management.lookups.*
│   ├── Özel Alanlar            /settings/custom-fields   management.custom_fields.*
│   │                           (+ deep-link: /{mod}/custom-fields)
│   ├── Form düzeni             (sonra — Form Engine)
│   └── İş akışları             (sonra)                   management.workflows.*
└── MODÜL AYARLARI (sonraki dalga — menü iskeleti şimdi, taşıma sonra)
    ├── İzin: türler/tatil/politika   /leaves/types|…
    ├── İşe alım: aşamalar            /lookups?type=application_stage
    ├── Evrak / Varlık kategorileri   /documents|assets/categories
    └── … (lisanslı modül sırasıyla)
```

**Permission:** İskelet için mevcut `management.*` yeterli. Kişisel AYARLAR RBAC şart değil. Modül ayarları kendi `{module}.{page}.*` ile kalır.

---

### ADIM 3 — İskelet planı (henüz kod yok)

#### ModuleRail konumu

| Seçenek | Açıklama |
|---------|----------|
| **A (öneri)** | Rail’de **Dashboard’dan sonra** iki sabit ikon: kişisel + Yönetim; operasyonel modüller altında |
| B | İkisi rail **altına** pin |
| C | Kişisel yalnızca header avatar; rail’de sadece Yönetim yeniden gruplu |

#### Taşıma (route koruyarak)

1. `moduleNav.ts`: Yönetim items → `group` metadata.
2. `ContextSidebar`: grup başlığı (minimal UI).
3. Orphan CF → Özelleştirme menü girişi.
4. Header “Ayarlar” → `/account`.
5. Modül sayfalarını silmeden stüdyoya deep-link.

#### Bu tur vs sonraki

| Bu turda (onay sonrası) | Sonraki dalga |
|-------------------------|---------------|
| ModuleRail: AYARLAR + YÖNETİM | Modül ayarlarını stüdyoya fiziksel taşıma |
| Yönetim 3–4 gruba ayırma | Form Engine / bildirim şablonları / liste görünümleri |
| Orphan CF + Listeler → Özelleştirme | İzin türleri “sadece stüdyo” mı çift menü mü |
| Kişisel: Profil + Güvenlik (+ Tercihler sync) | Kişisel bildirim (4C), density API |
| Header link düzeltmesi | Workflow UI, KVKK/veri |

---

### KARAR NOKTALARI (onay beklenir)

1. **Adlandırma:** Kişisel = «Ayarlar» mı «Hesabım» mı? Stüdyo rail = «Yönetim» mi «Stüdyo» mu?
2. **Rail konumu:** A / B / C?
3. **Modül ayarları çift menü:** İzin Türleri hem İzinler’de hem Stüdyo’da mı (öneri: **evet, aynı route**)?
4. **Özel alanlar:** Tek global `/settings/custom-fields` + entity filtresi mi, yoksa modül sayfaları birincil + stüdyoda indeks mi?
5. **2FA self:** Admin `UserDetail` kalsın + kişisel Güvenlik’te self mi?
6. **Bu tur kapsamı:** İskelet + kişisel üç sayfa yeterli mi; bildirim stub da mı?

Onay örneği: `1=Hesabım+Yönetim, 2=A, 3=çift menü, 4=global CF+deep-link, 5=self+admin, 6=iskelet+kişisel üç sayfa`.

---

## ÖZET TABLO — Gece otonom (12 Temmuz 2026)

| Adım | Durum | Commit / not |
|------|--------|----------------|
| 0 Radix Select boş değer | ✅ | `4995e61` |
| 1 Grup 2 İşe Alım/kanban | ✅ | `279e060` (+ docs `12c1175`) |
| 2 Grup 3 kalan modüller | ✅ | `f306873` (tek commit — ortak LookupService) |
| 3 Lookup Yönetim UI | ✅ | `3cd5792` → `/lookups` |
| 4 Custom Field onarımı | ✅ | `b07f182` (mesaj yanıltıcı: CF+2FA karışık) + `2598300` detay/test |
| 5 2FA challenge UI | ✅ | `b07f182` içinde (Company+SA+Portal) |
| 6 Deploy + Test turu | ✅ | `docs/DEPLOY_UBUNTU.md`, `docs/TEST_TURU.md` (bu commit) |

### Commit listesi (Grup1 opak sonrası)

```
2598300 feat(faz4): custom field detay gösterimi + validation test
b07f182 feat(faz4): Lookup Yönetim UI — Listeler sayfası  ← aslında CF validasyon + 2FA UI
3cd5792 feat(faz4): Lookup Yönetim UI — Listeler sayfası  ← gerçek Listeler sayfası
f306873 feat(faz4): Grup 3 kalan modüller — Lookup+Select yayılımı
12c1175 docs(faz4): ADIM 0-1 özet + Grup 2 rapor
279e060 feat(faz4): Grup 2 İşe Alım — application_stage hibrit kanban + Select
4995e61 fix(faz4): radix select boş değer sentinel + clearable
357120d feat(faz4): Lookup+Select yayılım Grup 1 + dropdown opak menü
```

### CI

- Push: `origin/faz4-form-engine`
- Actions: https://github.com/alataxbilisim/alatax-hr/actions?query=branch%3Afaz4-form-engine  
- Yerel: LookupTest **21 passed** (sqlite); 3 SPA tsc (i18n fix sonrası doğrulanacak)

### KARAR BEKLENENLER

1. **Başvuru kaynağı** FE↔BE etiket seti uyumsuz (`website` vs `job_board`) — dokunulmadı.
2. **hired → onboarding** otomatik tetik kodda yok; yeni otomasyon icat edilmedi.
3. **survey_question_type** SYSTEM yapıldı (düşük risk); genişletme istenirse firma tipine alınabilir.
4. **expense_categories / request_types / shifts Company CRUD** — bilerek ADIM 3 Listeler dışı bırakıldı (zengin tablo UI sonraki).
5. **Commit mesajı `b07f182`:** Listeler değil; CF+2FA içerir — history rewrite yok.

### Yarın önce şunlara bak (kullanıcı)

1. Dropdown menü opak mı + boş değer/Tümü/clearable Radix hatasız mı?
2. Kanban kolonları Listeler’den label/renk değişince güncelleniyor mu (status kodu sabit)?
3. `/lookups` CRUD: sistem kilit / hibrit kısıt / K-B pasif?
4. 2FA’lı hesap ile login challenge → verify uçtan uca?
5. Personel detay özel alan sekmesi + zorunlu custom field 422?

---

## ADIM 0 — Radix Select güvenlik (boş değer)

**Bilinen tuzak:** `SelectItem value=""` runtime hata; kontrollü boş native-like davranış yoksa ilk öğe sızabilir.

**Çözüm (`@shared/components/Select`):**
- İç sentinel: `SELECT_EMPTY_VALUE` (`__ax_empty__`) — dış API `''`
- `allowEmpty` → menüde boş satır (Tümü / Seçiniz); `onChange('')`
- `clearable` → trigger X ile temizle
- `options` içindeki `''` / sentinel value **filtrelenir** (güvenlik ağı)
- `allowEmpty=false` + boş value → Radix `value=undefined` (ilk öğeyi seçmez)

**Grup 1 audit:** native `<option value="">` Select kullanımlarında yok; hepsi `allowEmpty` + sentinel. Filtrelerde `clearable` eklendi (Personel, İzin, Belgeler).

**Manuel test:** opsiyonel alan boş submit → `''`; filtre Tümü + X temizle; console Radix hatası yok.

**Commit:** `4995e61` fix(faz4): radix select boş değer sentinel + clearable

---

## ADIM 1 — Grup 2 İşe Alım + kanban hibrit

| Type | Sınıf | Not |
|------|-------|-----|
| `application_stage` | **HİBRİT** | JobApplicationStatus enum birebir (9 kod); kanban kolonları |
| `experience_level` | firma | pozisyon formu |
| `job_position_status` | firma | draft/active/paused/closed |
| `interview_type` | firma | |
| `interview_status` | **HİBRİT** | workflow |
| `interview_recommendation` | firma | |
| `work_type` | (pilot) | employment_type teyit ✅ |

**BE:** `ApplicationController::updateStatus` eski `in:` listesi → Lookup assertValid (enum hizası). JobPosition + Interview lookup validasyon.

**FE:** ApplicationsPage kanban lookup kolonları; DetailModal/JobPositionForm/InterviewsPage Select.

**K-A:** label/renk override status value değiştirmez. Durum geçişi kod sabit.

**KARAR BEKLENİYOR:** başvuru kaynağı (`website` vs `job_board`) FE↔BE etiket uyumsuzluğu — bu dalgada dokunulmadı.

**Not:** hired→onboarding otomatik tetik mevcut kodda ayrı otomasyon yok; status `hired` kodu korunur (yeni otomasyon icat edilmedi).

---

## Özet karar (en önemli soru)

**Picklist/combobox değerleri için tutarlı, firma-yönetilebilir bir altyapı VAR MI?**

# **HAYIR — dağınık.**

Sistem üç ayrı desende yönetiliyor; ortak `picklists` / `lookup_values` tablosu, generic API veya paylaşılan “seçenek listesi” UI’ı **yok**:

| Desen | Örnek | Firma yönetebilir mi? |
|-------|--------|------------------------|
| PHP `PortableEnum` + migration string + FE hardcoded `<option>` | Personel durumu, kanban aşamaları, sözleşme tipi | Hayır |
| Modül başına DB tablosu + ayrı CRUD | `leave_types`, `departments`, `document_categories`, `asset_categories` | Kısmen (UI varsa) |
| DB var, Company UI yok | `expense_categories`, `request_types` | Hayır (Portal sadece okur) |

**İş boyutu:** Picklist birleştirme + Ayarlar Stüdyosu, Form Engine’in yanında **ayrı büyük iş kalemi**. Özel alan altyapısı ise **kısmi iskelet** (personelde değer saklama var; validasyon/FE-BE sözleşmesi kırık).

Kullanıcı vizyonu `docs/ROADMAP.md` Faz 4 altına kaydedildi (Ayarlar ≠ Yönetim Stüdyosu; sıra: özel alan+picklist → izin/kanban → stüdyo iskeleti → form düzeni).

---

## A. Özel Alan (custom field) altyapısı

### Mevcut ne var?

**Tablo:** `custom_field_definitions`  
Migration: `backend/database/migrations/2025_01_25_000001_create_custom_field_definitions_table.php`

| Kolon | Not |
|-------|-----|
| `company_id` | Tenant (FK, BelongsToCompany) |
| `entity_type` | `employee`, `leave_request`, `training`, `performance`, `document`, `expense`, `job_application`, `asset` |
| `field_key` / `field_label` / `field_type` | Unique `(company_id, entity_type, field_key)` |
| `field_options` | jsonb — select/radio için `[{value, label}]` beklenir |
| `is_required`, `is_active`, `sort_order` | |
| `validation_rules` | jsonb — **zorlanmıyor** (model’de TODO) |
| softDeletes + audit kolonları | Auditable trait yok |

**Model:** `backend/app/Models/CustomFieldDefinition.php`  
**API:** `GET/POST/PUT/DELETE /api/v1/custom-fields` (+ reorder, field-types, entity-types) — `CustomFieldController`  
**İzinler:** `management.custom_fields.*` + modül bazlı `{module}.custom_fields.*` (PermissionSeeder)

**Değer saklama:** Yalnızca **`employees.custom_fields` jsonb**. Diğer entity’lerde kolon **yok**.

**Renderer:** `frontend/packages/shared/src/components/CustomFieldRenderer.tsx`  
Tipler: text, number, date, select, checkbox, radio, textarea, file (dosya adı stub), email, phone, url.  
Validasyon: HTML `required`; zod/RHF yok; `validation_rules` kullanılmıyor.

**Admin UI:**
- Modül sayfaları: `/employees/custom-fields` vb. → `ModuleCustomFieldsPage.tsx` (7 modül)
- Orphan: `/settings/custom-fields` → `CustomFieldsPage.tsx` (sidebar’da yok)
- **Seeder veri yok** — alanlar elle/API ile oluşturulmalı

### Personel formu “Özel Alanlar” sekmesi — gerçekten çalışıyor mu?

| Adım | Durum |
|------|--------|
| Admin UI’dan alan tanımı | UI var; **select options payload FE↔BE uyumsuz** (`options: string[]` vs `field_options: [{value,label}]`); UI’da `datetime`/`multiselect`/`color` var, BE reddeder |
| Formda sekme + yükleme | `EmployeeForm` → `customFieldsApi.getAll('employee')` |
| Kaydet → jsonb | Evet — create/update body’de `custom_fields`; `EmployeeResource` döner |
| Detayda gösterim | **Yok** (`EmployeeDetailPage` göstermiyor) |
| Sunucu validasyonu (zorunlu/tip) | **Yok** — `nullable|array` |

**Verdict:** Temel text/number için “tanımla → formda doldur → kaydet → düzenlemede geri gel” **kısmen çalışır**. Select/opsiyonlu alanlar ve çoklu tip uyumsuzlukları kırık. İzin talebi formu custom field şeması yanlış + BE kolonu yok → **çalışmaz**.

### Ne eksik / tutarsız?

1. FE–BE sözleşme (options, UI-only kolonlar `is_unique`/`show_in_list`)
2. Tanımdan üretilen validasyon (Laravel + zod)
3. Diğer entity’lerde değer kolonu + Form entegrasyonu
4. Seed / demo alan yok
5. Feature test (CRUD + tenant + değer) yok; sadece permission wave testi
6. Form Engine (`form_definitions`, layout, koşullu görünürlük) **yok** — ROADMAP 4A

---

## B. Picklist / Lookup (combobox içerikleri)

### Madde madde

| # | Liste | Kaynak | company_id | Yönetilebilir UI |
|---|--------|--------|------------|------------------|
| 1 | Personel durum (Aktif/İzinli/Askıda/Çıkmış) | Hardcoded FE + BE `in:` validation | satırda | **Hayır** |
| 2 | İzin türleri | DB `leave_types` + LeaveTypeSeeder (firma başına 8 tür) | **Evet** | **Evet** — Leaves → Türler |
| 3 | Kanban aşamaları (8 kolon) | **FE hardcoded** `ApplicationsPage.statusColumns` + PHP `JobApplicationStatus` | N/A | **Hayır** — isim/sıra/renk değiştirilemez |
| 4a | Departman | DB `departments` | Evet | Evet — DepartmentsPage |
| 4b | Pozisyon (personel) | Serbest metin `employees.position` | — | Hayır (input); `job_positions` = ilan, master değil |
| 4c | Masraf kategorisi | DB `expense_categories` | Evet | **Hayır** (Company UI yok; Portal okur) |
| 4d | Doküman kategorisi | DB `document_categories` | Evet | Evet — Documents |
| 4e | Talep tipi | DB `request_types` | Evet | **Hayır** (Company UI yok; Portal okur) |

### Diğer örnekler (dağınık)

- Varlık durum/kondisyon: enum + FE hardcoded (`retired` vs BE `disposed` tutarsızlığı riski)
- Sözleşme tipi / çalışma tipi / cinsiyet / medeni hal / kan grubu: FE hardcoded
- İlan employment type / experience: PortableEnum + FE hardcode
- Asset kategorileri: DB + UI (Assets)

### Genel desen

```
Sabit workflow durumları  → PortableEnum (~67 enum) + FE local array (sık senkron değil)
Firma lookup (kategori/tür) → Her modül kendi tablosu + controller + (bazen) UI
Custom field select       → field_options jsonb — sistem picklist’i DEĞİL
Merkezi picklist servisi  → YOK
```

**Kanban özel not:** Kullanıcı vizyonundaki “aşama ismi/sırası/renk özelleştirme” için bugün **hiçbir DB modeli yok**; tamamen kod sabiti. Bu, “izin türü yönetme”den farklı ve daha büyük bir iş (workflow state machine + migrate + UI).

### Verdict (B)

Tutarlı picklist/lookup altyapısı **yok**. Birkaç yerde iyi örnek var (`leave_types`, `departments`, `document_categories`); çoğu durum/seçenek hardcoded; bazı tablolar UI’sız. Zoho tarzı “listeleri stüdyodan tasarla” hedefi için **yeni soyutlama + migrasyon stratejisi** gerekir.

---

## C. Ayarlar — mevcut durum

### Company `/settings` (`SettingsPage.tsx`)

**Saf firma ayarları** (kişisel profil karışık değil):

| Sekme | İçerik |
|-------|--------|
| Genel | Firma adı, logo, vergi, adres |
| SMTP / SMS | Kanal test |
| Genel Ayarlar | timezone, dil, para, çalışma günleri |
| Bildirimler | Firma kanal tercihleri |
| API Keys / Lisans / Entegrasyonlar / Modüller | |

Sidebar: Yönetim → Ayarlar. Header kullanıcı menüsü de buraya gider — **kişisel profil linki yok**.

### Kişisel (profil / şifre / 2FA / tercih)

| Özellik | Backend | Company UI | Portal |
|---------|---------|------------|--------|
| Profil | `PUT /auth/profile` | **Yok** | `/profile` |
| Şifre (self) | `PUT /auth/password` | **Yok** | Portal profile |
| 2FA | `users/{id}/2fa/*` | Admin: UserDetailPage | — |
| Tema | preferences + localStorage | Header toggle | Header |
| Density | localStorage (+ preferences tipi) | Geçici toggle (Faz 3) | — |

**Sonuç:** Kullanıcı vizyonundaki **(1) AYARLAR = kişisel** Company’de henüz yok; **(2) YÖNETİM / Stüdyo** ise bugünkü `/settings` + dağınık modül ayarları (izin türleri Leaves’te, kategoriler Documents’ta, özel alanlar modül altında) — tek çatı değil.

---

## Strateji için çıkarımlar (uygulama yok — tartışma notu)

Önerilen sıra (kullanıcı vizyonuyla uyumlu):

1. **Özel alan sertleştirme:** FE–BE sözleşme, validasyon servisi, personel detay gösterimi, select options düzeltmesi → Form Engine’in zemini.
2. **Picklist soyutlama (ilk dalga dar):** Önce yönetilebilir DB listelerini (izin türü, kategoriler) ortak “LookupRegistry / company_lists” desenine çekmek; sonra kanban aşamaları gibi workflow enum’larını ayrı tasarlamak (riskli).
3. **Ayarlar Stüdyosu iskeleti:** Menü ayrımı (Kişisel AYARLAR vs YÖNETİM) + mevcut sayfaları taşıma/gruplama.
4. **Form düzeni / koşullu görünürlük:** `form_definitions` + FormEngine (ROADMAP 4A).

**Belirsizlik (strateji oturumunda netleştirilecek):**
- Kanban aşamaları: yeniden adlandırma yeterli mi, yoksa aşama ekle/sil/sırala da mı?
- Personel “pozisyon”: serbest metin mi kalacak, lookup mu olacak?
- Picklist v1 kapsamı: yalnızca “zaten DB’de olanlar” mı, yoksa status enum’ları da mı?

---

## Dosya referansları (hızlı)

```
Custom fields:
  backend/.../CustomFieldDefinition.php
  backend/.../CustomFieldController.php
  backend/.../migrations/2025_01_25_000001_create_custom_field_definitions_table.php
  frontend/packages/shared/src/components/CustomFieldRenderer.tsx
  frontend/apps/company/src/components/EmployeeForm.tsx
  frontend/apps/company/src/pages/shared/ModuleCustomFieldsPage.tsx

Picklist örnekleri:
  frontend/.../ApplicationsPage.tsx (statusColumns)
  backend/.../Enums/JobApplicationStatus.php
  backend/.../Models/LeaveType.php + LeaveTypeSeeder.php
  backend/.../Models/Department.php / DocumentCategory.php / ExpenseCategory.php / RequestType.php

Ayarlar:
  frontend/.../pages/settings/SettingsPage.tsx
  frontend/.../portal/pages/ProfilePage.tsx
  frontend/.../layouts/MainLayout.tsx (header → /settings)
```

---

## Combobox Envanteri (ADIM 2)

**Amaç:** Lookup Engine boyutlandırma — sistemdeki her seçenek listesi.  
**Tarama:** Company SPA (birincil) + Portal + Superadmin + `backend/app/Enums` + lookup tabloları + controller `in:` validasyonları.  
**Sınıflar:**
- **BASİT** — değer + etiket (+ renk/sıra); Lookup Engine adayı
- **ZENGİN** — kendi tablosu + iş kuralları / hiyerarşi / form şeması; tablo kalır, tutarlı UI alır
- **?** — basit/zengin belirsiz (aşağıda sorulacak)
- **SİSTEM** — infra/meta (SMTP, timezone, widget tipi); firma HR lookup’ı değil (envanterde ayrı, Lookup Engine dışı)

**Kaynak kısaltmaları:** `hardcode` = FE sabit dizi/option · `enum` = PHP PortableEnum/CHECK · `tablo` = DB + API · `FK` = entity seçici · `jsonb` = tanım içi seçenek

Aynı mantıksal liste birden fazla UI yüzeyinde (form + filtre + badge) tekrarlanabilir; tabloda **bir satır = bir mantıksal alan**. UI yüzeyi “Sayfa” sütununda özetlenir.

### 1. Personel / Organizasyon

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Personel | Form + Liste filtre + Toplu | Durum | active, on_leave, suspended, terminated | hardcode (BE string, CHECK yok) | Evet | BASİT |
| Personel | Form | Cinsiyet | male, female, other | hardcode | Evet | BASİT |
| Personel | Form | Medeni durum | single, married, divorced, widowed | hardcode | Evet | BASİT |
| Personel | Form | Kan grubu | A/B/AB/0 × Rh± | hardcode | Evet (standart set) | BASİT |
| Personel | Form | Eğitim seviyesi | İlkokul…Doktora | hardcode (TR etiket) | Evet | BASİT |
| Personel | Form | Yakınlık (acil) | Eş, Anne, Baba, Kardeş, Çocuk, Arkadaş, Diğer | hardcode | Evet | BASİT |
| Personel | Form + Liste filtre | Sözleşme tipi | permanent, temporary, intern, contract | hardcode | Evet | BASİT |
| Personel | Form | Çalışma tipi | full_time, part_time, remote, hybrid | hardcode | Evet | BASİT |
| Personel | Form | Para birimi (maaş) | TRY, USD, EUR, GBP | hardcode | ? (global mi firma mı) | BASİT / ? |
| Personel | Form + Liste | Departman | departments.* | tablo FK | Evet (var) | ZENGİN |
| Personel | Form | Yönetici | employees FK | FK | — | ZENGİN (entity) |
| Personel | Form | Özel alan select/radio | field_options[] | jsonb | Evet (var) | ZENGİN (custom field; Lookup değil) |
| Personel › Belgeler | Tab form + filtre | Belge kategorisi | id_card, contract, certificate, education, health, other | hardcode + Rule::in | Evet | BASİT |
| Personel › Belgeler | (BE) | Belge durumu | active, archived, expired | string | Evet | BASİT |
| Departman | Form | Üst departman | departments FK | tablo | Evet | ZENGİN |
| Departman | Form | Departman yöneticisi | employee FK | FK | — | ZENGİN (entity) |
| Şube | Form | Ülke | Türkiye | hardcode | Hayır (sistem ref) | SİSTEM / BASİT |
| Şube | Form | Şehir | 81 il | hardcode | Hayır (TR ref) | SİSTEM / BASİT |
| Şube | Form | Yönetici | user FK | FK | — | ZENGİN (entity) |

### 2. İzin

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| İzin | Form (Company+Portal) | İzin türü | leave_types | tablo | Evet (CRUD var) | ZENGİN |
| İzin | Liste filtre + badge | Talep durumu | pending, approved, rejected, cancelled | hardcode + LeaveRequestStatus | Hayır (workflow) | BASİT / ? (kilitli sistem mi) |
| İzin › Tür formu | Form | Cinsiyet kısıtı | all, male, female | hardcode + GenderRestriction | Evet | BASİT |
| İzin › Hakediş | Form | Hakediş tipi | annual, monthly, per_pay_period, hourly, custom | enum AccrualTypeValue | Evet | BASİT / ? (politika motoru) |
| İzin › Hakediş | Form | İzin türü FK | leave_types | tablo | Evet | ZENGİN |
| İzin › Tatil | Form + filtre | Tatil tipi | national, company, regional (+ BE: religious) | hardcode / Typef3342e | Kısmi | BASİT |
| İzin › Bakiye/Rapor | Filtre | Yıl | son 5 yıl (dinamik) | hardcode logic | — | SİSTEM |

### 3. Puantaj (Company UI yok; Portal + BE)

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Puantaj | Portal Timesheet badge | Gün durumu | present, late, absent, leave, holiday, early_leave… | hardcode + BE string | Evet | BASİT |
| Puantaj | BE | Timesheet durumu | draft, submitted, approved, rejected | string | Hayır (workflow) | BASİT / ? |
| Puantaj | BE | Clock-in metodu | manual, qr, nfc, gps, face | string | ? | BASİT |
| Puantaj | BE / Portal okuma | Vardiya | shifts | tablo (CRUD UI yok) | Evet | ZENGİN |
| Puantaj | BE | Çalışma planı tipi | fixed, flexible, shift | string | Evet | BASİT / ? |
| Puantaj | BE | Dönem tipi | weekly, biweekly, monthly | string | Evet | BASİT |

### 4. Masraf (Company UI yok; Portal)

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Masraf | Portal form | Kategori | expense_categories | tablo (Company CRUD **yok**) | Evet | ZENGİN |
| Masraf | Portal badge | Talep durumu | draft, submitted, approved, rejected, paid | hardcode | Hayır (workflow) | BASİT / ? |

### 5. Doküman

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Doküman | Form + filtre | Kategori | document_categories | tablo | Evet (CRUD var) | ZENGİN |
| Doküman | Liste filtre | Dosya tipi | pdf, image, document, spreadsheet, presentation, archive | hardcode | Hayır | SİSTEM |
| Doküman | Badge | Onay durumu | draft, pending, approved, rejected | enum ApprovalStatusValue | Hayır (workflow) | BASİT / ? |
| Doküman | BE | required_documents.scope | all, department, position, employee_type | enum | Evet | BASİT |

### 6. İşe Alım

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| İşe Alım | Pozisyon formu | Çalışma şekli | full_time, part_time, contract, internship, remote | enum EmploymentType | Evet | BASİT — **Personel work_type ile FARKLI set** |
| İşe Alım | Pozisyon formu | Deneyim seviyesi | entry, mid, senior, lead, manager | enum ExperienceLevel | Evet | BASİT |
| İşe Alım | Pozisyon formu | Durum | draft, active, paused, closed | enum JobPositionStatus | Evet | BASİT |
| İşe Alım | Kanban + Detay | Başvuru aşamaları | new→…→hired/rejected (+ withdrawn) | hardcode + JobApplicationStatus | **Evet (vizyon)** | BASİT (workflow) — **renk+sıra kritik** |
| İşe Alım | Mülakat form | Tip | phone, video, onsite, technical, hr, panel | enum + API getTypes | Evet | BASİT |
| İşe Alım | Mülakat filtre/form | Durum | scheduled, completed, cancelled, no_show, rescheduled | enum | Evet | BASİT |
| İşe Alım | Tamamla modal | Öneri | strong_hire…strong_no_hire | enum RecommendationValue | Evet | BASİT |
| İşe Alım | Rapor etiket | Kaynak | website/linkedin… vs BE job_board/social… | hardcode / application_sources | Evet | BASİT — **FE↔BE etiket seti uyumsuz** |
| İşe Alım | Form | Pozisyon / Aday / Görüşmeci | FK | FK | — | ZENGİN (entity) |
| İşe Alım | BE | Teklif durumu | draft, sent, accepted, rejected, expired, withdrawn | enum | Evet | BASİT |
| İşe Alım | Form builder | Alan tipi | text, email, select… | hardcode | Meta | SİSTEM |

⚠️ `ApplicationController::updateStatus` validasyonu (`interview,testing,offer,accepted,pool`) enum/CHECK ile **uyumsuz** (ADIM 1 notu).

### 7. Onboarding

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Onboarding | Liste badge | Süreç durumu | pending, in_progress, completed, cancelled | enum | Hayır? | BASİT / ? |
| Onboarding | Şablon görev | Görev tipi | document_upload, document_fill, training, meeting, system_setup, quiz, custom | enum TypeValue | Evet | BASİT / ? (davranış bağlı) |
| Onboarding | Form | Çalışan / Şablon / Sorumlu | FK | FK / templates tablo | Evet | ZENGİN |
| Onboarding | BE | Görev durumu | pending, in_progress, completed, skipped | enum | Sistem | BASİT |
| Onboarding | BE | Milestone / buddy / survey_type | çeşitli | enum | Sistem | BASİT |

### 8. Performans

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Performans | Form | Dönem / çalışan / değerlendiren | FK | FK | — | ZENGİN (entity) |
| Performans | Badge | Dönem durumu | draft, active, closed | enum | Evet | BASİT |
| Performans | Badge | Değerlendirme durumu | draft, submitted, approved, rejected | enum | Workflow | BASİT / ? |
| Performans | BE + kısmi UI | OKR level | company, department, team, individual | enum | Evet | BASİT |
| Performans | BE | OKR / KR status, metric_type, confidence | çeşitli | enum | Evet | BASİT |
| Performans | BE | Feedback relationship | self, manager, peer, direct_report, external | enum | Evet | BASİT |
| Performans | Portal feedback tip | type | appreciation/suggestion… (**BE praise/suggestion… ile uyumsuz**) | hardcode | Evet | BASİT |
| Performans | BE | 1:1 mood / status | mood + status enum | enum | Evet | BASİT |
| Performans | CRUD | Yetkinlik / kriter | competencies, performance_criteria | tablo | Evet | ZENGİN |

### 9. Eğitim

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Eğitim | Form | Eğitim türü | online, classroom, hybrid | enum | Evet | BASİT |
| Eğitim | Form | Kategori | serbest + datalist öneri | API string | Evet | BASİT / ? (lookup mu serbest mi) |
| Eğitim | Oturum formu | Durum | scheduled, in_progress, completed, cancelled | enum | Evet | BASİT |
| Eğitim | Oturum / katılımcı | Eğitim / kullanıcı FK | FK | FK | — | ZENGİN |
| Eğitim | BE | Katılımcı durumu | registered, attended, absent, excused | enum | Evet | BASİT |
| Eğitim | BE | Learning path level / mandatory scope | beginner… / all… | enum | Evet | BASİT |
| Eğitim | BE | Eğitim talebi durumu | pending, approved, rejected, completed | enum | Workflow | BASİT / ? |

### 10. Varlık

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Varlık | Form | Kategori | asset_categories | tablo | Evet (CRUD var) | ZENGİN |
| Varlık | Form | Durum | FE: available/assigned/maintenance/**retired** · BE enum: …/**disposed** | enum + hardcode | Evet | BASİT — **FE↔BE tutarsız** |
| Varlık | Form | Kondisyon | FE: new/good/fair/poor · BE +broken | enum + hardcode | Evet | BASİT — **eksik değer** |
| Varlık | Zimmet | Kullanıcı FK | FK | FK | — | ZENGİN |
| Varlık | BE | Maintenance type/status | preventive… / scheduled… | enum | Evet | BASİT |
| Varlık | BE | Lifecycle stage | new, active, maintenance, retired, disposed | enum | Evet | BASİT |
| Varlık | BE | Depreciation method | none, straight_line, declining_balance | enum | Evet | BASİT |
| Varlık | BE/Portal | Asset request urgency/status | urgency + status | enum | Evet | BASİT |
| Varlık | BE | License type | perpetual, subscription… | enum | Evet | BASİT |

### 11. Anket

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Anket | Form | Anket türü | engagement, satisfaction, pulse, enps, onboarding, exit, custom | enum | Evet | BASİT |
| Anket | Form | Soru türü | text, rating, single_choice, multiple_choice, nps (+ BE: scale, matrix) | hardcode / enum | Meta | SİSTEM / BASİT |
| Anket | Form | Soru seçenekleri | kullanıcı yazar | dinamik | Anket başına | ZENGİN (içerik, lookup değil) |
| Anket | BE | recurrence / audience | none/weekly… · all/department… | enum | Evet | BASİT |
| Anket | Portal | Cevap (rating) | 1–5 | hardcode | — | SİSTEM |
| Anket | Portal | Filtre tab | all / pending / completed | hardcode | — | SİSTEM |

### 12. Talep (Company UI yok; Portal)

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Talep | Portal form | Talep türü | request_types | tablo (Company CRUD **yok**) | Evet | ZENGİN |
| Talep | Portal form + badge | Öncelik | low, normal, high, urgent | hardcode | Evet | BASİT |
| Talep | Portal badge | Durum | pending, in_review, approved, rejected, cancelled | hardcode | Workflow | BASİT / ? |

### 13. Kullanıcı / Rol / Workflow / Ayarlar / Rapor

| Modül | Sayfa | Alan | Şu anki değerler | Kaynak | Firma-özel? | Sınıf |
|-------|-------|------|------------------|--------|-------------|-------|
| Kullanıcı | Form / toplu | Rol | roles | Spatie tablo | Evet | ZENGİN |
| Kullanıcı | Toplu işlem | Aksiyon | activate, deactivate, assign_role… | hardcode | — | SİSTEM |
| Rol | RoleForm | data_scope | own…company (**UI yok**, BE kolon var) | DataScopeLevel | Evet | BASİT — **UI eksik** |
| Rol | RoleForm | İzinler | permissions grid | Spatie + sabit etiket | Sistem | ZENGİN / SİSTEM |
| Workflow | BE/UI | entity_type, approver_type, timeout_action | sabit enum setleri | enum | Evet (tanım) | BASİT / ? (motor) |
| Ayarlar | Settings | SMTP encryption, SMS provider, TZ, dil, tarih, para, çalışma günü | sabit listeler | hardcode | Firma config | SİSTEM |
| Özel alan tanımı | Form | field_type / entity_type | sabit listeler | hardcode | Meta | SİSTEM |
| Rapor / Dashboard | Builder | boyut/metrik/chart/KPI tipi | API metadata + hardcode | API / hardcode | Meta | SİSTEM |
| Audit | Filtre | action | 40+ action key | hardcode | Sistem | SİSTEM |

### 14. Superadmin (Lookup Engine dışı; referans)

| Modül | Sayfa | Alan | Değerler | Kaynak | Sınıf |
|-------|-------|------|----------|--------|-------|
| Firma | Form + filtre | status | trial/active/suspended/cancelled (filtre: inactive uyumsuz) | hardcode + CompanyStatus | SİSTEM |
| Firma | Form | Lisans paketi | license_packages | tablo | ZENGİN |
| Paket | Form | Süre ay | 1/3/6/12/24 | hardcode | SİSTEM |
| Modül | Form | icon | bootstrap icon list | hardcode | SİSTEM |
| Ledger | Modal | payment_method | bank_transfer… | hardcode | SİSTEM |

---

### Sınıflandırma özeti (mantıksal alan bazında)

| Sınıf | Ne demek | Örnekler |
|-------|----------|----------|
| **BASİT → Lookup Engine** | Etiket/renk/sıra/aktif; iş kuralı yok veya minimal | Durumlar, cinsiyet, kan grubu, kanban aşaması, öncelik, mülakat tipi, eğitim tipi, varlık kondisyonu… |
| **ZENGİN → kendi tablo + ortak UI** | CRUD + ek kolonlar/kurallar | leave_types, departments, *\_categories, request_types, competencies, job_positions, roles… |
| **SİSTEM** | Lookup Engine’e alınmaz | SMTP, timezone, field_type meta, audit action, chart tipi |
| **Entity FK** | Kişi/kayıt seçici; picklist değil | manager, reviewer, employee, training_id |

**Workflow durumları** (izin/masraf/talep/onay): teknik olarak BASİT ama **firma ekle-sil yaparsa state machine kırılır** → varsayılan: Lookup’ta *yeniden adlandır + renk + gizle*; aşama ekle/sil ayrı ürün kararı (kanban ile aynı).

---

### Sayısal özet

Sayım birimi: **benzersiz mantıksal seçenek alanı** (form+filtre tekrarı tek sayılır). Entity FK’ler ve saf SİSTEM meta ayrı.

| Metrik | Sayı (yaklaşık) |
|--------|-----------------|
| Toplam mantıksal combobox/picklist (domain + zengin + sistem) | **~95** |
| … yalnızca domain (HR iş alanı; sistem meta hariç) | **~78** |
| **BASİT** (Lookup Engine adayı, net) | **~48** |
| **BASİT / ?** (workflow veya davranış bağlı — netleştirilecek) | **~14** |
| **ZENGİN entity / tablo** | **~16** |
| **SİSTEM / meta** | **~17** |
| Entity FK seçiciler (picklist sayılmaz, UI’da select) | **~15+** |
| Şu an **hardcode / enum** (firma değiştiremiyor) | **~55+** domain basit alan |
| Şu an **DB tablo** (+ çoğu Company CRUD) | **~12** (leave_types, departments, branches, document/asset categories, competencies, holidays, accrual_policies, job_positions, custom_fields, criteria, workflows, templates…) |
| DB tablo ama **Company CRUD yok** | **3+** (`expense_categories`, `request_types`, `shifts`; ayrıca `application_sources`) |
| En yoğun modül (basit + zengin domain) | **1) İşe Alım (~12)** · **2) Personel (~12)** · **3) Performans+Varlık (~10’ar)** · **4) İzin (~7)** |
| Company’de UI’sız (sadece Portal/BE) | Puantaj, Masraf, Talep picklist’leri |

**UI yüzey sayısı (tekrarlı select’ler dahil, Company ağırlıklı):** ~70+ `<select>`/const option yüzeyi; Portal ~8 form picklist; Superadmin ~6.

---

### Lookup Engine boyutlandırma çıkarımı

1. **İlk dalga (yüksek ROI, düşük risk):** Personel alanları (durum, sözleşme, çalışma tipi, cinsiyet, medeni, eğitim, yakınlık, belge kategorisi) + İşe Alım (deneyim, pozisyon durumu, mülakat tipi/öneri) + Varlık durum/kondisyon + Talep önceliği + Anket türü + Eğitim türü. Hepsi bugün hardcode.
2. **Paralel (zengin UI birleştirme):** `expense_categories` + `request_types` Company CRUD; mevcut leave/document/asset category UI’larını ortak “Liste yönetimi” kabuğuna al.
3. **İkinci dalga (ürün kararı şart):** Kanban başvuru aşamaları + diğer workflow status’ları (yeniden adlandır vs ekle/sil).
4. **Alınma:** SMTP/SMS/timezone, rapor chart tipi, custom field `field_type`, audit actions, entity FK’ler.

---

---

### Belirsizlikler — KARARLAR (12 Temmuz 2026)

| # | Soru | Karar |
|---|------|--------|
| 1 | Workflow / kanban aşama ekle-sil | **SİSTEM-KODLU hibrit:** kod (`value`) sabit; etiket/renk/sıra firma. Aşama ekle/sil yok (şimdilik). |
| 2 | work_type vs employment_type | **Tek `work_type` lookup** (birleşik değer seti). |
| 3 | Para / şehir / kan grubu | **SİSTEM lookup** (`is_system`, `company_id=null`, salt okunur). |
| 4 | accrual_type / onboarding görev tipi | **Lookup DEĞİL** — iş kuralı / motor sabiti. |
| 5 | Eğitim kategori | Sonraki dalga (bu pilotta yok). |
| 6 | data_scope | **Lookup DEĞİL** — Faz 2 güvenlik; sabit enum. |

**Lookup DEĞİL (zengin entity kalır):** izin türü, departman, kategoriler, request_types, roller…

---

## Lookup Engine — kararlar + araştırma (ADIM 3)

### Üç katman

| Katman | `company_id` | `is_system` | Firma ne yapabilir? | Örnek |
|--------|--------------|-------------|---------------------|--------|
| **FİRMA** | null = platform default; firma override kendi `company_id` | false | ekle / label-renk-sıra / pasifleştir | `employee_status`, `work_type` |
| **SİSTEM** | null | true | salt okunur (403) | `currency`, `city_tr`, `blood_type`, `country` |
| **HİBRİT** | override ile | meta/hybrid | sadece label/renk/sıra; `value`+silme kilitli | `application_stage` (sonraki dalga) |

### Araştırma bulguları (Zoho / BambooHR deseni) — 3 kritik kural

**K-A. Kayıtlar LABEL değil REFERANS tutar.**  
`employees.status = 'active'` (value). Label `"Aktif"`→`"Çalışıyor"` değişince kayıt değişmez; görüntü `resolve(value)` ile yeni label’ı alır.

**K-B. Kullanımdaki değer HARD DELETE edilemez.**  
Kullanım varsa → `is_active=false` (pasif). Pasif değer yeni formlarda seçilemez; eski kayıtlarda `resolve` ile görünür. Kullanım yoksa silinebilir.

**K-C. Cascading için hazır kolon.**  
`parent_lookup_id` nullable — şimdi mantık yok; ileride şehir→ilçe vb.

### Bu dalga kapsamı (çekirdek + pilot)

- `lookups` tablosu + LookupService + CRUD API (`management.lookups.*`)
- Sistem seed: para, TR illeri, kan grubu, ülke
- Firma default seed: `employee_status`, `work_type`
- Pilot bağlama: Personel Durum + Çalışma Tipi; İşe Alım `employment_type` → aynı `work_type`
- Kalan ~46 combobox + kanban hibrit → sonraki yayılım dalgaları

---

**DUR (görsel kontrol).** Altyapı + pilot sonrası: Personel Durum dropdown API’den; lookup label rename → form + mevcut kayıtta yeni label.

---

## Uygulama durumu (ADIM 3 — çekirdek + pilot)

| Madde | Durum |
|-------|--------|
| Kararlar + K-A/K-B/K-C dokümante | ✅ |
| `lookups` migration + Model + LookupSeeder | ✅ |
| LookupService (`forType`, `resolve`, kullanım) | ✅ |
| CRUD API + `management.lookups.*` | ✅ |
| Sistem seed (para/şehir/kan/ülke) | ✅ |
| Pilot: `employee_status` + `work_type` (Personel + İşe Alım employment_type) | ✅ |
| LookupTest (K-A, K-B, sistem 403, tenant, permission) | ✅ (12) |
| Kalan ~46 combobox + kanban hibrit | ⏳ sonraki dalgalar |

**Görsel kontrol (kullanıcı):** Personel formunda Durum API’den; Yönetim lookup rename → liste/badge `status_label` yeni metin.

---

## Ortak Select (Radix) — ADIM 4

| Madde | Durum |
|-------|--------|
| `@radix-ui/react-select` → `@alatax/shared` | ✅ |
| `@shared/components/Select` (token, density, ellipsis+title, color swatch, portal) | ✅ |
| `searchable` iskelet (default kapalı) | ✅ |
| Pilot: Personel Durum + Çalışma Tipi → ortak Select | ✅ |
| Yazı sığma (trigger truncate) + açık menü token | ✅ (görsel onay bekleniyor) |
| Kalan ~106 native `<select>` | ⏳ yayılım dalgaları |

**DUR — görsel kontrol:** Personel formunda Durum/Çalışma Tipi aç; uzun etiket + menü tasarımına bak.

---

## Grup 1 yayılımı — Personel (kalan) + İzin

**Dalga:** ortak Select + basit → Lookup Engine; zengin entity → kendi API + Select.

### Yeni / genişletilen `lookup_type`

| Type | Sınıf | Not |
|------|-------|-----|
| `gender` | firma default | male/female/other |
| `marital_status` | firma default | |
| `education_level` | firma default | value = TR etiket (mevcut veri uyumu) |
| `emergency_relation` | firma default | value = TR etiket |
| `contract_type` | firma default | |
| `employee_document_category` | firma default | |
| `leave_request_status` | **HİBRİT** | kod sabit (pending/approved/rejected/cancelled), etiket/renk firma |
| `leave_gender_restriction` | **HİBRİT** | `GenderRestriction` enum + iş kuralı — kod sabit |
| `holiday_type` | firma default | UI create: yalnız company/regional (BE kısıtı) |
| `blood_type`, `currency` | **SİSTEM** (önceki) | salt okunur |

### Personel — dönüştürülen dropdown’lar

| Alan | Kaynak | Select |
|------|--------|--------|
| Durum, çalışma tipi (pilot) | Lookup | ✅ |
| Cinsiyet, medeni, kan, eğitim, yakınlık, sözleşme, para | Lookup | ✅ |
| Departman, yönetici | API (zengin) | ✅ |
| Liste filtre: durum / departman / sözleşme + toplu durum | Lookup / API | ✅ |
| Belge kategorisi (DocumentsTab) | Lookup | ✅ |
| CustomFieldRenderer select | field_options (Lookup değil) | ✅ |
| Departman formu: üst dept + yönetici | API | ✅ |

**Sayı (Personel bu dalga):** ~14 native→Select; ~9 basit lookup bağlama (+pilot 2).

### İzin — dönüştürülen dropdown’lar

| Alan | Sınıf | Kaynak |
|------|-------|--------|
| İzin türü (talep formu + portal) | ZENGİN | `leave_types` API + Select |
| Talep durumu (filtre/badge) | HİBRİT | `leave_request_status` |
| Cinsiyet kısıtı (tür formu) | HİBRİT | `leave_gender_restriction` |
| Tatil tipi | BASİT (+ BE create kısıtı) | `holiday_type` |
| Hakediş tipi | motor/enum — Lookup **değil** | statik options + Select |
| Yıl / kullanıcı filtreleri | sistem / zengin | Select |

**Sayı (İzin bu dalga):** LeavesPage + LeaveRequestForm + LeaveTypeForm + Holiday* + Accrual* + Balances/Reports + Portal Leaves — native `<select>` kalmadı (modül klasörlerinde).

### Belirsizlik / bilinçli kararlar

1. **`leave_gender_restriction` → HİBRİT:** Enum cast + uygunluk kuralları nedeniyle yeni value eklenemez; etiket firma. (Basit “firma serbestçe eklesin” değildi.)
2. **`holiday_type`:** Lookup’ta national/religious var; şirket UI/BE create yalnızca `company|regional` (sistem tatilleri ayrı).
3. **`accrual_type`:** Lookup’a alınmadı (politika motoru).
4. **Belge durumu** (`active/archived/expired`): bu dalgada dokunulmadı — sonraki mikro-dalga veya Grup X.
5. **Personel rapor widget select’leri:** Grup 1 dışı (rapor builder).

### Test / doğrulama

- LookupTest: Grup1 seed + hibrit store/delete 403 + etiket override → **15 passed** (sqlite in-memory; phpunit.xml pgsql driver yoksa env override)
- Seed: `php artisan db:seed --class=LookupSeeder` ✅
- 3 SPA lint ✅ + build ✅

**DUR — görsel kontrol (kullanıcı):** Personel formu (kişisel + iş + belge + özel alan select) + İzin talep/tür/tatil dropdown’ları tutarlı mı; izin türleri API’den geliyor mu; durum filtreleri lookup label. **SelectContent opak menü** (`--bg-elevated`) — şeffaflık bug düzeltildi.

**Kalan sonraki gruplar:** İşe Alım (kanban hibrit) + Masraf/Varlık/Doküman/Performans/Eğitim/Anket.

---

## 4B-B5 — Stüdyo Onay Zinciri Editörü (C2)

**Tarih:** 2026-07-16  
**Branch:** `faz4-form-engine`  
**Motor:** ApprovalFlowEngine / B0–B4 **dokunulmadı** (21 motor testi yeşil).

### ADIM 0 — Teşhis

| Konu | Bulgu |
|------|--------|
| Tablolar | `approval_workflows` + `approval_steps` (+ instances/records/delegations) |
| Alanlar | `step_order`, `approver_type` (dinamik/rol/kişi + legacy), `condition` jsonb, `parallel_group`, `completion_policy`, `escalation_days` |
| CRUD API | Vardı ama **legacy** (dinamik tip / condition / parallel yok) |
| Seed | `approvals:seed-default-leave-workflows` → `leave_request` + `dynamic_manager` |
| Entity | leave kesin; expense/asset/training/document/salary_review modelde |
| Snapshot | Instance’ta step snapshot **yok** → yapısal edit riskli |
| Migration | UI için ek kolon gerekmedi (DUR notu yok) |

### Guard kararları

| Guard | Seçim |
|-------|--------|
| (a) Aktif instance + adım değişikliği | **409** — “önce sonuçlandırın” (snapshot yok) |
| (b) Silme | Bağlı **herhangi** instance → **409** |
| (c) Entity’de aktif workflow zorunlu mu? | **Hayır** — workflow’suz = mevcut otomatik/yok davranışı |

Metadata-only update (ad, açıklama, aktif) açık instance varken serbest.

### API

| Method | Path | Permission |
|--------|------|------------|
| GET/POST | `/api/v1/workflows` | view / create |
| GET/PUT/PATCH/DELETE | `/api/v1/workflows/{id}` | view / edit / delete |
| POST | `/api/v1/workflows/seed-default-leave` | create |
| GET | `/workflows/entity-types`, `/approver-types`, `/condition-meta` | view |

Service: `WorkflowManagementService` — steps tek payload transaction sync.  
Policy: `ApprovalWorkflowPolicy` (company scope). Audit: `Auditable` + ActivityLog.

### UI

- `/settings/workflows` liste (entity filtre) + `/settings/workflows/:id|new` editör  
- ModuleRail YÖNETİM/Özelleştirme → **Onay Akışları** (`management.workflows.view`)  
- ↑↓ sıralama, koşul, parallel/any, canlı önizleme (`1 → 2a∥2b`), varsayılan izin seed butonu

### Doğrulama

| Metrik | Sonuç |
|--------|--------|
| `WorkflowManagementApiTest` | **8 passed** |
| Motor B0–B4 | **21 passed** (değişmedi) |
| Tam suite | **467 passed / 0 fail** |
| company `tsc` | **0** |
| Select sentinel | **PASSED** |
| DB wipe | **yok** |

**DUR — KULLANICI GÖRSEL KONTROLÜ BEKLİYOR:** Stüdyo → Onay Akışları editörü (adım kartları, koşul, paralel önizleme, seed butonu).
