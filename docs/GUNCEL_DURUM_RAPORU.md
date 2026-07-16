# ALATAX HR — Güncel Durum Raporu (AI Handoff)

**Üretim tarihi:** 14 Temmuz 2026  
**Amaç:** Claude Opus 4.8 (veya başka bir agent) için tek kaynaklı, kod + `docs/` karşılaştırmalı durum.  
**Yöntem:** Tüm `docs/*.md` okundu; backend/frontend envanteri koddan doğrulandı; doküman–kod sapmaları ayrı bölümde listelendi.  
**Aktif branch:** `faz4-form-engine`  
**Remote durumu:** `origin/faz4-form-engine`’den **5 commit önde** — **PUSH YAPILMADI** (kullanıcı onayı bekleniyor).

---

## 0. Yönetici özeti (30 saniye)

ALATAX HR, Türkiye odaklı multi-tenant B2B HR SaaS’tır. Backend Laravel 12 REST (`/api/v1`), frontend pnpm monorepo (3 React 19 SPA + `@alatax/shared`), DB PostgreSQL.

| Katman | Durum |
|--------|--------|
| Faz 0–2 | ✅ Kapandı (stabilizasyon, pgsql, RBAC+Audit+2FA) |
| Faz 3 | 🔶 Kodda büyük ölçüde yapıldı (`faz3-tasarim`); ROADMAP checkbox’ları **güncellenmemiş** |
| Faz 4 | 🔶 **AKTİF** — Lookup+Select ✅ · Workflow B0–B3 ✅ · **Form Engine 4A ❌** · B4/B5 ❌ · 4C ❌ |
| FAZ A (TR/org) | ✅ A1–A5 lokal tamam (A3 branch, A4 org, A5 pozisyon); holding ertelendi |
| Faz 5–8 | ☐ Açık |
| Ürün olgunluğu | ~%55–65 (CRUD güçlü; dikey akış kopuklukları + platform motorları eksik) |
| Test | **294 passed**, 1 risky (lokal, gece A3–A5 sonrası) |
| Push | A3–A5 + gece özeti **5 commit local-only** |

**Bir sonraki mantıklı iş (ROADMAP sırası):** Faz 4A Form Engine — veya kullanıcı önceliğine göre AKIS kopuklukları (izin bakiyesi route, işe alım public apply) / push+CI.

**Kritik kural:** Yalnızca `faz4-form-engine` üzerinde çalış. `main` / `faz3-tasarim` dokunma. Push yalnız kullanıcı “push” deyince.

---

## 1. Proje kimliği

| Alan | Değer |
|------|-------|
| Ad | ALATAX HR |
| Tip | Multi-tenant B2B HR SaaS (Türkiye first; cloud + ileride on-prem) |
| Repo kök | `c:\xampp\htdocs\alatax-hr` |
| Backend | `backend/` — Laravel ^12, PHP ^8.2, Sanctum, Spatie Permission, Telescope, Google2FA |
| Frontend | `frontend/` — pnpm: `apps/company` (:3002), `apps/portal` (:3003), `apps/superadmin` (:3001), `packages/shared` |
| Auth | Sanctum Bearer; TOTP 2FA; davet + must_change_password (A2) |
| RBAC | `{module}.{page}.{action}` + wildcard; Policy + DataScope |
| Tenancy | `company_id` + `BelongsToCompany` global scope; client’tan `company_id` **asla** alınmaz |
| Bordro | ROADMAP Faz 8 motoru hâlâ ufuk; **C5** Company PDF upload/yayın + Portal “Bordrolarım” |
| Kurallar | `.cursorrules` — API katmanları, migration, i18n `t()`, DataTable, FormRequest |

---

## 2. Git / branch haritası

| Branch | Rol |
|--------|-----|
| `main` | Faz 2 merge sonrası baseline |
| `faz1-postgresql` | Kapalı |
| `faz2-rbac-audit` | Kapalı |
| `faz3-tasarim` | Tasarım sistemi (token, density, sidebar) — merge edilmiş / temel |
| **`faz4-form-engine`** | **Aktif çalışma branch’i** |

### Push edilmemiş 5 commit (HEAD = `d44bead`)

```
d44bead docs(A): gece özeti — A3/A4/A5 tamam, güvenlik yeşil, push yok
a014316 feat(A5): pozisyon kataloğu + SGK meslek kodu seed + personel Select
7a8582c feat(A4): org şeması 3 mod — people/department/hybrid
5faab49 feat(A3): employees.branch_id + DataScope branch + branch_manager rolü
86d4d47 docs(A3): A1 borç ROADMAP + grup/şirket/şube mimari teşhis (kod yok)
```

A1/A2 commit’leri remote’ta (önceki push’lar). A3–A5 + gece docs henüz remote’ta değil.

---

## 3. Docs envanteri — her dosya ne işe yarar / güncelliği

| Dosya | Amaç | Güncellik (14 Tem 2026) |
|-------|------|-------------------------|
| `BURADAN_BASLA.md` | 7 belgeyi bağlayan giriş | ⚠️ **ESKİ** — hâlâ “Faz 0’a başla”, SQLite kararı anlatıyor |
| `SISTEM_ISLEYIS.md` | Çalışan yaşam döngüsü (hedef hikaye) | Spec; kodla birebir değil |
| `ROADMAP.md` | Ana pusula Faz 0–8 | ⚠️ Faz 3 checkbox’lar açık; test sayısı 186; footer “sonraki Faz 3” eski. A1 borç + A3 holding notu **var** |
| `MODUL_SPEC.md` | Modül ekran/CRUD şartnamesi | Hedef spec; birçok ekran YARIM/YOK |
| `AKIS_SPEC.md` | Talep→Onay→Sonuç mekanizması | Spec |
| `TASARIM_REHBERI.md` | Kompakt UI token şartı | Faz 3 referansı |
| `I18N.md` | `t()` kuralları | Geçerli |
| `CURSOR_PROJE_ANALIZ_PROMPT.md` | Snapshot üretme prompt’u | Araç |
| `PROJECT_SNAPSHOT.md` | 13 Tem teknik röntgen | ⚠️ Migration 70→**74**, model 81→**82**, A1–A5 sonrası güncellenmedi |
| `DEPLOY_UBUNTU.md` | Ubuntu Docker + host FE | Geçerli (Aşama 1 doğrulandı) |
| `TEST_TURU.md` | Manuel smoke checklist | Geçerli |
| `AKIS_ENVANTERI.md` | 13 Tem akış teşhisi | ⚠️ **Kısmen bayat** — register leave type seed A1 ile düzeldi; diğer kopukluklar büyük ölçüde duruyor |
| `FAZ1_RAPOR.md` … `FAZ4_RAPOR.md` | Faz kapanış/teşhis | Tarihsel; FAZ4 Lookup+B0–B3+panel/scroll |
| `FAZ_A_RAPOR.md` | A1–A5 + gece özeti | ✅ **En güncel faz raporu** |
| **Bu dosya** `GUNCEL_DURUM_RAPORU.md` | AI handoff | ✅ 14 Tem |

---

## 4. ROADMAP faz durumu — doküman vs kod

| Faz | ROADMAP işareti | Kod gerçeği | Sapma |
|-----|-----------------|-------------|-------|
| **0 Stabilizasyon** | ✅ KAPANDI | ✅ | Açık borçlar: Mailtrap E2E, 6 kayıp route, `_archive_old_app`, i18n backlog |
| **1 PostgreSQL** | ✅ KAPANDI | ✅ pgsql default; mysql legacy | Squash/GIN/pg_dump ertelendi |
| **2 RBAC+Audit** | ✅ KAPANDI | ✅ 343 permission route, Policy, Auditable P0, TOTP, 186→şimdi **294** test | ROADMAP hâlâ “186”; DataScope’ta `branch` A3’te eklendi (Faz 2 metni eski) |
| **3 Tasarım** | ☐ Tüm maddeler açık | 🔶 `faz3-tasarim` + FAZ3_RAPOR: token, density, sidebar, scroll | **ROADMAP senkron değil** — pratikte kısmen/büyük oranda yapıldı |
| **4 Platform** | AKTİF (checkbox karışık) | Lookup ✅ Select ✅ B0–B3 ✅ · **4A FormEngine yok** · B4/B5/4C yok | Lookup/B0–B3 ROADMAP’te kısmen işaretli; 4A açık |
| **A (TR/org)** | ROADMAP 6A altında borç notları | A1–A5 kodda ✅ (local) | Holding SEÇENEK 1 ertelendi |
| **5 Rapor motoru** | ☐ | ☐ | — |
| **6 Modül+KVKK** | ☐ | Kısmi CRUD; derinleştirme yok | — |
| **7 On-prem+lisans** | ☐ | `DEPLOY_UBUNTU` Aşama 1 manuel | Installer/lisans yok |
| **8 Mobil/AI/bordro** | ☐ | Portal Capacitor notları var | — |

**Kilometre taşları:** M1 ✅ · M2 (Faz 5) · M3 (Faz 6A) · M4 (Faz 7) — açık.

---

## 5. Backend — güncel envanter

### 5.1 Stack

- Laravel 12, PHP 8.2+, Sanctum 4.2, Spatie Permission 6.24, Telescope, Google2FA + Bacon QR, PHPUnit 11, Pint

### 5.2 Modüller (`ModuleSeeder`)

**Core (3):** `user-management`, `company-management`, `audit-logs`  
**Satılabilir (10):** `job-applications`, `document-management`, `onboarding`, `leave-management`, `performance`, `training`, `asset-management`, `hr-analytics`, `surveys` (+ bir daha — toplam ~13)  
**Yok:** tam bordro motoru (Faz 8); C5’te `payroll.payslips.*` permission + upload var

### 5.3 API

- `routes/api.php` ~1160 satır, `/api/v1`, tahmini **~350–400** endpoint
- Alanlar: auth, users/roles/branches, employees/departments/**positions**, lookups, recruitment, documents, onboarding, leaves (+accrual/holidays), workflows/approvals, expenses, performance, training, assets, surveys, analytics, attendance, admin, portal, public kariyer
- Middleware: `auth:sanctum`, `company.active`, `permission:*`, `module.access:*`, `portal.access`, `super_admin`, throttle

### 5.4 Multi-tenancy / RBAC / DataScope

- `BelongsToCompany` — ~50+ model; Lookup istisna (`company_id` nullable)
- Roller: `admin`, `hr_manager`, `hr_specialist`, `branch_manager` (**A3**), `manager`, `employee`
- DataScope seviyeleri: `own` < `team` < `department` < **`branch`** < `company`
- Policy’ler: Employee, LeaveRequest, Document, EmployeeDocument, ExpenseClaim, PerformanceReview, ApprovalRecord
- Alan izinleri: maaş/TCKN Resource filtre; `field_permissions` tablosu Form Engine’e ertelenmiş

### 5.5 Auth (A2 dahil)

| Özellik | Durum |
|---------|--------|
| Login / logout / me | ✅ |
| Register (firma + admin + trial) | ✅ + **HR default seed (A1)** |
| Forgot / reset | ✅ (Mailtrap E2E borç) |
| Davet `InvitationService` | ✅ sha256, 7g, tek kullanım; public accept |
| `must_change_password` + force-change SPA | ✅ |
| TOTP 2FA | ✅ |

### 5.6 FAZ A kod kanıtları

| İş | Ana dosyalar |
|----|----------------|
| A1 TR seed | `DefaultCompanyHrSeedService`, `AccrualPolicy::calculateAnnualEntitlement`, `Holiday::seedTurkishHolidays*`, migration `2026_07_14_000001`, artisan `alatax:seed-defaults` |
| A2 Davet | `InvitationService`, Auth accept endpoints, shared Invite/ForcedPasswordChange pages |
| A3 Şube | `employees.branch_id`, `DataScopeService` branch, `branch_manager`, `BranchDataScopeTest` |
| A4 Org | `OrganizationChartService` modes: people \| department \| hybrid |
| A5 Pozisyon | `positions` tablosu (**≠** recruitment `job_positions`), `PositionCatalogSeedService` ~40 SGK kodu, CRUD + FE |

### 5.7 Sayılar (kod sayımı 14 Tem)

| Metrik | Değer |
|--------|-------|
| Migrations | **74** |
| Models | **82** |
| Feature test dosyası | **34** |
| Unit test dosyası | **4** |
| Lokal suite | **294 passed**, 1 risky |

Son migration’lar: leave_types system flags, must_change_password, branch_id, positions (2026_07_14_*).

### 5.8 Bilinen backend boşluklar

- İzin talebi update/delete/cancel route eksik (controller kısmi)
- Leave balance update/bulkUpdate **route yok** (AKIS 🔴)
- ~~Bordro upload/HR API yok; duyuru admin yok; vardiya CRUD yok~~ → **C5** (duyuru CRUD + bordro upload; vardiya PDKS’te zaten vardı)
- ApprovalRequestedNotification = stub (C4’te no-op; gerçek yol NotificationService)
- Custom field validation TODO
- Masraf kategori company panel route zayıf

---

## 6. Frontend — güncel envanter

### 6.1 Stack

React 19, TS ~5.9, Vite 7, RRD 7, Redux Toolkit (auth/theme/ui), TanStack Query (Portal + shared peer), RHF+zod, axios via `@shared/services/api`, i18next (namespaces: `common`, `auth`, `validation` — **yalnızca tr**), Radix Select, Nivo/grid-layout (company)

### 6.2 Company rotaları (özet)

Dashboard · Personel (+departments, **positions**, **organization**, custom-fields, reports) · Recruitment · Leaves · Documents · Onboarding · Performance · Training · Assets · Surveys · Analytics · Settings/users/roles/**branches**/lookups/webhooks/audit · Account

**Sidebar’da var, App.tsx’te yok (Faz 0 borcu):**  
`/onboarding/templates`, `/performance/periods`, `/performance/criteria`, `/training/sessions`, `/assets/categories`, `/assets/assignments`

### 6.3 Shared — kritik gerçekler

| Bileşen | Durum |
|---------|--------|
| **FormEngine** | ❌ **YOK** (0 dosya) — entity formları elle (`EmployeeForm` vb.) |
| CustomFieldRenderer | ✅ |
| Select + useLookupOptions | ✅ |
| DataTable | ⚠️ Company-local (`apps/company/.../DataTable.tsx`), shared değil |
| InviteAccept / ForcedPasswordChange / 2FA | ✅ shared |
| theme.css / density | ✅ Faz 3 |
| `_archive_old_app/` | Hâlâ repoda (Faz 0 borç) |

### 6.4 Portal / SuperAdmin

- Portal: Bootstrap 5 kalıntısı; self-service leave/attendance/expenses/payslip/announcements
- SuperAdmin: şirketler, lisans paketleri, sistem yönetimi

---

## 7. FAZ A özeti (en güncel ürün işi)

Detay: `docs/FAZ_A_RAPOR.md`

| Madde | Durum | Not |
|-------|--------|-----|
| A1 TR default seed | ✅ (pushed) | 10 izin türü, tenure_rules 14/20/26 + yaş, tatil 2026–28, overtime_type lookup |
| A2 Invite + şifre | ✅ (pushed) | CI yeşil geçmişte |
| A3 branch DataScope | ✅ local | Holding **yok**; companies=tenant |
| A4 org 3 mod | ✅ local | |
| A5 pozisyon+SGK | ✅ local | |
| A1 borç | Açık | Hakediş UI yok; dini bayram sabit → API sonra (ROADMAP 6A) |

**Karar kilitleri:** Holding = SEÇENEK 1 ileride; şimdilik branch yeterli. A4/A5 A3’ten bağımsız tamamlandı.

---

## 8. Akış olgunluğu (`AKIS_ENVANTERI` + A1 sonrası düzeltme)

**13 Tem sayaç:** ~28 TAM · ~32 YARIM · ~24 KOPUK · ~18 YOK

### A1 sonrası düzelen

| Akış (eski etiket) | Yeni gerçek |
|--------------------|-------------|
| Register → leave type seed ⬜ | ✅ `AuthController` + `DefaultCompanyHrSeedService::ensureForCompany` |

### Hâlâ kritik kopukluklar (AKIS — büyük ölçüde geçerli)

1. **İzin bakiyesi manuel atama** — FE var, BE update route yok  
2. **İzin iptal** — cancel route eksik  
3. **İşe alım public başvuru** — şema/status uyumsuz; Kanban boş riski  
4. **hired → employee/onboarding** wire yok  
5. **Puantaj** — portal clock-in OK; company attendance + vardiya CRUD **var** (PDKS/C5); Portal shifts tarih eşleşmesi C5’te düzeltildi  

6. **Masraf** — portal OK; company HR UI + workflow zayıf  
7. **Form Engine / Stüdyo / tam bildirim** yok  
8. Nav/route drift (6 path)

---

## 9. Doküman ↔ kod sapma listesi (agent için zorunlu okuma)

Bu liste yanlış varsayımı önler:

1. **`BURADAN_BASLA.md`** — “Faz 0’a başla” artık yanlış; proje Faz 4 + FAZ A’da.
2. **`ROADMAP.md` Faz 3** — checkbox’lar boş ama tasarım kodu büyük ölçüde mevcut; “Faz 3’ü sıfırdan yap” deme.
3. **`ROADMAP.md` test** — “186” yazıyor → **294**.
4. **`ROADMAP.md` DataScope** — Faz 2 metninde branch yok → A3’te `branch` + `branch_manager` var.
5. **`PROJECT_SNAPSHOT.md`** — 70 migration / 81 model / A1–A5 yok → **74 / 82** + FAZ A.
6. **`AKIS_ENVANTERI.md`** — “register leave type seed etmez” **YANLIŞ oldu** (A1). Diğer izin/recruitment/masraf kopuklukları duruyor.
7. **Form Engine** — ROADMAP 4A ve snapshot “yok” diyor → kodda da **yok**; CustomFieldRenderer ≠ FormEngine.
8. **`leave_types` vs Lookup** — A1 kararı: türler **ayrı tablo** (`system_code`); Lookup’a taşınmadı.
9. **`positions` vs `job_positions`** — A5 katalog ≠ recruitment iş ilanı pozisyonu; karıştırma.
10. **Holding** — dokümanda SEÇENEK 1 önerisi; kodda **yok**; companies=tenant kırılmamalı.
11. **Push** — FAZ_A “4 commit önde” demişti; şu an **5** (gece docs dahil).
12. **Faz sırası** — “önce platform sonra modül”; Form Engine (4A) roadmap önceliği; FAZ A istisnai TR/org işiydi.

---

## 10. Deploy / ortam

- Lokal: sıkça XAMPP + Windows; staging: Ubuntu LAN (`DEPLOY_UBUNTU.md`)
- Docker: app + nginx + **postgres** + redis + worker + scheduler (+ mysql legacy)
- FE host pnpm: **Node 20 + pnpm@9.15.9** (pnpm 11 kırılır)
- LAN: `CORS_ALLOWED_ORIGINS` + `VITE_API_URL=SUNUCU_IP:8000`
- Seed: SuperAdmin seed; Company/Portal için register + personel + portal-access
- Otomatik deploy yok; `deploy-ubuntu-update` script chore olarak var

---

## 11. Test / CI

| Suit | Not |
|------|-----|
| PHPUnit lokal | 294 passed, 1 risky (~215s) — A3 sonrası DataScope/policy yeşil |
| Kritik güvenlik | PermissionEnforcement Wave1–4, RouteAuthorization, DataScope*, BranchDataScope, Invite*, PanelAccess, Totp2fa, Audit* |
| FAZ A testleri | DefaultCompanyHrSeed*, InviteAndPasswordOnboarding*, BranchDataScope*, OrganizationChart*, PositionCatalog*, AccrualPolicyEntitlement* |
| CI | Push sonrası GitHub Actions: Pint + FE lint/build + PHPUnit (pgsql) — **A3–A5 için henüz koşmadı** (push yok) |
| Company `tsc` | Gece özetine göre yeşil |

**DoD kuralı (.cursorrules):** Yeni endpoint → auth 401, yetkisiz 403, happy path, tenant izolasyonu testleri.

---

## 12. Mimari kararlar (kilitli)

1. Modüler monolith — mikroservis yok  
2. PostgreSQL default; MySQL legacy silinmez  
3. API-first; UI’a özel gizli endpoint yok  
4. `company_id` yalnız auth’tan  
5. Enum → string + CHECK + PHP backed enum  
6. Baseline migration düzenlenmez — yeni migration  
7. UI metinleri Türkçe + `t()`  
8. Form Engine gelince entity formları ona geçer — şimdilik elle OK  
9. Holding gelince SEÇENEK 1 (`organizations`); şimdi branch yeterli  
10. Bordro kapsam dışı (Faz 8 ufuk)

---

## 13. Açık borçlar — öncelik grupları

### P0 — Güvenlik / regresyon

- Push sonrası CI yeşil teyit  
- DataScope/policy suite’i yeşil tut (her org değişikliğinde)

### P1 — Platform (ROADMAP Faz 4)

- **4A Form Engine** (Personel formu ilk geçiş)  
- B4 paralel/eskalasyon  
- B5 Stüdyo workflow UI  
- 4C Bildirim Merkezi  
- Cascading picklist (`parent_lookup_id`)

### P2 — Kullanıcıyı “kopuk” hissettiren dikey akışlar

- İzin: balance update route + cancel + portal alan drift  
- İşe alım: public apply şema + hired→employee  
- Masraf/puantaj company HR UI  
- 6 kayıp sidebar route kararı

### P3 — Doküman hijyeni

- ROADMAP Faz 3 checkbox senkronu  
- PROJECT_SNAPSHOT / AKIS_ENVANTERI A1+A3–A5 güncellemesi  
- BURADAN_BASLA modernizasyonu  
- `_archive_old_app` temizliği

### P4 — Faz 5–7

- Rapor semantic layer  
- KVKK  
- On-prem installer + lisans

### A1 teknik borç (Zincir 2/3)

- Accrual kuralları yönetim UI  
- Dini bayram tarihleri API

---

## 14. Agent’a çalışma talimatı (kopyala-yapıştır)

```
Proje: ALATAX HR — branch yalnızca faz4-form-engine.
Kurallar: .cursorrules + docs/ROADMAP.md + docs/FAZ_A_RAPOR.md + bu GUNCEL_DURUM_RAPORU.md.
UI Türkçe t(); company_id client'tan alma; FormRequest+Service+ApiResponse; BelongsToCompany.
FormEngine henüz YOK — icat etme, istenirse 4A olarak tasarla.
positions ≠ job_positions; leave_types Lookup değil.
Holding ekleme (ertelendi). Push yalnız kullanıcı isterse.
Faz 2 DataScope/permission testleri kırılırsa özellik bitmiş sayılmaz.
docs/AKIS_ENVANTERI.md'teki "register leave seed yok" satırı ESKİ — A1 düzeltti.
```

---

## 15. Önerilen okuma sırası (yeni agent)

1. Bu dosya (`GUNCEL_DURUM_RAPORU.md`)  
2. `.cursorrules`  
3. `docs/FAZ_A_RAPOR.md` (gece özeti + A1–A5)  
4. `docs/ROADMAP.md` §Faz 4 + §Faz 6A A1/A3 notları (checkbox sapmalarına dikkat)  
5. `docs/AKIS_ENVANTERI.md` (kopukluklar; A1 satırını düzelt)  
6. `docs/FAZ4_RAPOR.md` (Lookup + panel/scroll)  
7. İhtiyaç halinde: `SISTEM_ISLEYIS` / `MODUL_SPEC` / `AKIS_SPEC` / `DEPLOY_UBUNTU`

---

## 16. Hızlı dosya indeksi

```
backend/app/Services/DefaultCompanyHrSeedService.php
backend/app/Services/InvitationService.php
backend/app/Services/DataScopeService.php
backend/app/Services/OrganizationChartService.php
backend/app/Services/PositionCatalogSeedService.php
backend/app/Services/DefaultLeaveApprovalWorkflowService.php
backend/config/data-scope.php
backend/routes/api.php
backend/database/seeders/{Module,Permission,LeaveType}Seeder.php
frontend/apps/company/src/App.tsx
frontend/apps/company/src/pages/{employees,lookups,organization,positions}/
frontend/packages/shared/src/{components/Select.tsx,hooks/useLookupOptions.ts,styles/theme.css}
docs/{ROADMAP,FAZ_A_RAPOR,AKIS_ENVANTERI,FAZ4_RAPOR,DEPLOY_UBUNTU,PROJECT_SNAPSHOT}.md
```

---

*Bu rapor 14 Temmuz 2026 tarihinde kod sayımı + docs karşılaştırması ile üretilmiştir. Push sonrası CI sonucu ve yeni özellikler eklendikçe güncellenmelidir.*
