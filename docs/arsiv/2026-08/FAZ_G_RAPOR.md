# FAZ G — Rapor (G1)

**Tarih:** 4 Ağustos 2026 · **Branch:** `faz4-form-engine`  
**Dalga:** G1 — kimlik + operasyonel şirket bağlamı  
**Kod yazıldı.** G1 = operasyonel bağlam; **G2 = rapor/pano grup kapsamı** (`docs/FAZ_G2_RAPOR.md`, `docs/FAZ_G2_KARAR.md`).

---

## Özet

Hibrit model (SEÇENEK 1): operasyonel ekranlarda tek aktif şirket; `BelongsToCompany` hâlâ `= tek company_id`. Kaynak artık `CompanyContext` (X-Company-Id + membership). Portal dokunulmadı (seçici yok; header yok sayılır).

**G1 boşlukları (Tur2–Tur5’te kapatıldı):** Dashboard / KVKK / PDKS / Settings / Dataset → CompanyContext.  
**G2 tamamlandı:** `scope=group` = organization ∩ membership + `reports.scope.group`; KVKK ve CRUD hariç.

---

## Şema

| Tablo / kolon | Not |
|---------------|-----|
| `organizations` | id, name, slug, settings jsonb, timestamps |
| `companies.organization_id` | nullable FK + indeks |
| `company_user` | user_id, company_id, role_id NULL, is_default; unique(user,company) |
| `users.last_company_id` | nullable FK — girişte son şirket |
| `users.company_id` | **kaldırılmadı** (home / geriye uyum) |

Backfill: migration içinde idempotent + `php artisan group:backfill-company-context`.

---

## company_id kullanım dönüşümü

| Kapsam | Adet / not |
|--------|------------|
| Doğrudan `$user->company_id` / `auth()->user()->company_id` → aktif bağlam | ~12 dosya (BelongsToCompany, BaseController, BranchContext*, NotificationController, ApprovalWorkflowPolicy, EmployeeDashboardController, ActivityLog, Auth formatUser, …) |
| Portal controller’lar | **dokunulmadı** (13 dosya) |
| Arka plan işleri `CompanyContext::run` | LeaveAccrualBatch, DocumentExpiryAlert, ApprovalEscalation, ScanRetention, ProcessHeavyReportExportJob, RunReportScheduleJob, ExecuteDestructionApprovalJob |

---

## FE (company SPA)

- Navbar şirket seçici (A6 deseni): Redux `companyContextSlice` + `alatax_company_id` + `X-Company-Id`
- Tek şirkette seçici gizli; aktif şirket adı her zaman görünür
- Değişimde şube sıfırlanır + dashboard’a dönüş; `companyContext.version` listeleri yeniler
- Portal SPA’ya dokunulmadı
- Bildirim: `other_company_unread` rozeti

---

## İzolasyon paketi (15 senaryo)

| # | Senaryo | Sonuç |
|---|---------|--------|
| 1 | UA aktif A → personel yalnız A | ✅ |
| 2 | UA sahte header (C) → 403 | ✅ |
| 3 | UA aktif A → B personel detay 404 | ✅ |
| 4 | UA aktif A → B izin/masraf/doküman 403/404 | ✅ |
| 5 | Oluşturma → company_id = A | ✅ |
| 6 | Şirket değişimi liste tazelenir | ✅ |
| 7 | Yanlış şirket dosya indirme 403/404 | ✅ |
| 8 | Portal UC; A yok; seçici yok | ✅ |
| 9 | SuperAdmin regresyon | ✅ |
| 10 | DataScope tek şirkette | ✅ |
| 11 | BranchContext yabancı şube 403 | ✅ |
| 12 | Job bağlamsız → exception | ✅ |
| 13 | Backfill/login | ✅ |
| 14 | last_company_id kalıcılığı | ✅ |
| 15 | Tek şirketli seçici listesi | ✅ |

Kritik düzeltme: `company.context` / `branch.context` → `SubstituteBindings` öncesi priority + istek başında stale context forget (tenant sızıntısı).

---

## Demo seeder

`demo-firma` + `demo-otel-b` + `demo-otel-c` aynı organization (`org-demo-holding` / `DemoOrganizationAligner`); Merkez Ofis = demo-firma şubesi; `admin@demo.test` / `ik@demo.test` üçüne membership.

G2 sonrası teşhis: bazı ortamlarda G1 1:1 backfill ile üç şirket ayrı org’da kalmıştı — niyet her zaman tek holding’di. Düzeltme: seeder her koşuda hizalar + `php artisan demo:align-organizations`. Detay: `docs/DEMO_ORG_DUZELTME.md`.

---

## Test / CI

- Suite: **656 passed** (tek koşu + 3× ardışık + `--order-by=random`) — hepsi yeşil
- Demo sentinel: OK (migrate sonrası)
- 3 SPA: tsc 0 · lint 0
- Push: `faz4-form-engine` ahead 0
- Actions: https://github.com/alataxbilisim/alatax-hr/actions?query=branch%3Afaz4-form-engine

---

## KULLANICI GÖRSEL KONTROLÜ BEKLİYOR (borç)

- Company navbar: çok şirketli kullanıcıda seçici + aktif şirket adı
- Tek şirketli kullanıcıda seçici gizli
- Şirket değişince dashboard + şube listesi
- Bildirim rozeti (diğer şirket)
- Portal’da şirket seçici olmadığını doğrula
