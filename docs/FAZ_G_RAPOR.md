# FAZ G — Rapor (G1)

**Tarih:** 4 Ağustos 2026 · **Branch:** `faz4-form-engine`  
**Dalga:** G1 — kimlik + operasyonel şirket bağlamı  
**Kod yazıldı.** Rapor/pano grup kapsamı **yok** (G2).

---

## Özet

Hibrit model (SEÇENEK 1): operasyonel ekranlarda tek aktif şirket; `BelongsToCompany` hâlâ `= tek company_id`. Kaynak artık `CompanyContext` (X-Company-Id + membership). Portal dokunulmadı (seçici yok; header yok sayılır).

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

`demo-firma` + `demo-otel-b` + `demo-otel-c` aynı organization; Merkez Ofis = demo-firma şubesi; `admin@demo.test` / `ik@demo.test` üçüne membership.

---

## KULLANICI GÖRSEL KONTROLÜ BEKLİYOR (borç)

- Company navbar: çok şirketli kullanıcıda seçici + aktif şirket adı
- Tek şirketli kullanıcıda seçici gizli
- Şirket değişince dashboard + şube listesi
- Bildirim rozeti (diğer şirket)
- Portal’da şirket seçici olmadığını doğrula
