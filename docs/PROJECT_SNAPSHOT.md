# ALATAX HR — PROJECT_SNAPSHOT

> **Not (FAZ A/B güncellemesi):** 13 Tem 2026 baseline; FAZ A/B ile güncellendi (branch-context, izin/işe alım/masraf/puantaj HR, test **~338**). Tam yeniden envanter üretilmedi — detay: `docs/FAZ_B_RAPOR.md`.

**Üretim tarihi:** 13 Temmuz 2026  
**Branch:** `faz4-form-engine`  
**Kaynak kural:** `docs/CURSOR_PROJE_ANALIZ_PROMPT.md` (koddan okuma; değer uydurma yok)  
**Önceki snapshot:** Repoda `PROJECT_SNAPSHOT.md` yoktu → bu ilk tam envanter (Faz 0–4B B0–B3 sonrası).

---

## 1. Proje Özeti

- **Ad:** ALATAX HR — Türkiye odaklı multi-tenant B2B HR SaaS.
- **Repo:** Monorepo. Backend `backend/` (Laravel 12 REST `/api/v1`). Frontend `frontend/` (pnpm: `apps/superadmin`, `apps/company`, `apps/portal`, `packages/shared`).
- **Auth:** Laravel Sanctum Bearer. RBAC: Spatie Permission (`{module}.{page}.{action}`). Multi-tenancy: `company_id` + `BelongsToCompany` global scope.
- **Tamamlanma (kabaca):** Çekirdek HR modülleri + Faz 2 yetki/audit + Faz 3 UI + Faz 4 Lookup/Select/Ayarlar iskeleti + **4B B0–B3 onay motoru (izin pilot)** çalışır. Form Engine (4A), bildirim merkezi (4C), Stüdyo workflow UI (B5), paralel/eskalasyon (B4) henüz yok. Tahmini ürün olgunluğu ~%55–65 (çekirdek CRUD güçlü; yapılandırılabilir form/rapor/workflow UI eksik).

---

## 2. Teknoloji Stack'i

| Katman | Teknoloji |
|--------|-----------|
| Backend | PHP ^8.2, Laravel ^12, Sanctum ^4.2, Spatie Permission ^6.24, Telescope ^5.16, Google2FA + Bacon QR |
| Frontend | React 19, TypeScript ~5.9, pnpm monorepo, TanStack Query, Redux (auth/theme/ui), react-hook-form + zod, Radix UI, CSS variables (`theme.css`) |
| DB | PostgreSQL (üretim/CI); JSONB hibrit; Eloquent; string+CHECK enum (`PortableEnum`) |
| Test | PHPUnit 11, Pint; FE lint/build CI |

**Composer require (özet):** `laravel/framework`, `laravel/sanctum`, `spatie/laravel-permission`, `laravel/telescope`, `pragmarx/google2fa`, `bacon/bacon-qr-code`, `laravel/tinker`.

---

## 3. Klasör Yapısı (özet)

```
alatax-hr/
├── backend/          # Laravel API
│   ├── app/{Models,Http,Services,Policies,Events,Listeners,Notifications,Enums,Traits}
│   ├── database/{migrations,seeders,factories}
│   ├── routes/api.php
│   └── tests/{Feature,Unit}
├── frontend/
│   ├── apps/{company,portal,superadmin}
│   └── packages/shared
├── docs/             # ROADMAP, FAZ4_RAPOR, TEST_TURU, bu snapshot
└── .cursorrules
```

---

## 4. Veritabanı — kritik (Faz 4B sonrası)

**Migration sayısı:** 70 dosya (`backend/database/migrations/`).  
**Son eklenen (4B):** `2026_07_13_040000_extend_approval_engine_for_faz4b.php`

### Onay motoru tabloları

| Tablo | Rol |
|-------|-----|
| `approval_workflows` | Firma akış tanımı (`entity_type`, `is_default`, `conditions` jsonb) |
| `approval_steps` | Adımlar: `approver_type` (legacy + `dynamic_manager`/`dynamic_skip_manager`/`role`/`user`), `condition` jsonb, `parallel_group`, `completion_policy` (B4 için şema hazır) |
| `approval_instances` | **YENİ** polymorphic instance (`approvable_*`, `flow`, `current_step`, `status`, `company_id`) |
| `approval_records` | Adım aksiyonları + `approval_instance_id` FK |
| `approval_delegations` | Vekalet |

**Model sayısı:** 81 (`backend/app/Models/`).

**Multi-tenancy:** Firma verisi tutan modellerde `company_id` + `BelongsToCompany` deseni (onay tabloları dahil).

---

## 5. Modüller ve Özellikler (özet durum)

| Modül | Durum | Not |
|-------|-------|-----|
| Auth / 2FA / register | ✅ | TOTP; register’da default izin akışı seed (4B) |
| Personel / departman | ✅ | DataScope + alan izinleri |
| İzin | ✅ + motor | store→`startWorkflow`; approve köprü; bakiye; resubmit |
| İşe alım | ✅ | Lookup hibrit `application_stage` kanban |
| Masraf / puantaj / eğitim / varlık / performans / anket | 🔶 | CRUD var; workflow bağlı değil |
| Lookup Engine | ✅ | sistem/firma/hibrit; K-A/K-B |
| Custom fields | 🔶 | tanımlar + employee; Form Engine yok |
| Ayarlar Stüdyosu | 🔶 | iskelet + kişisel Ayarlar sayfaları |
| Onay zinciri 4B | ✅ B0–B3 | B4 paralel/eskalasyon, B5 Stüdyo UI yok |
| Rapor builder | ❌ | Faz 5 |

---

## 6. API Envanteri (ölçüm)

- `php artisan route:list --path=api/v1` → **~491** satır (route kaydı).
- Standart response: `ApiResponse` → `{ success, message, data, errors, timestamp, meta? }`.
- Onay: `/api/v1/approvals/*` (kuyruk) + köprü `POST /leaves/requests/{id}/approve|reject|resubmit`.

---

## 7. Kimlik / Yetki

- Sanctum token; Spatie roller (`admin`, `hr_manager`, `manager`, `employee`, …).
- PermissionSeeder: ~100+ izin string’i (grep ~144 eşleşme satırı civarı — tam sayı seeder içeriğine bağlı).
- Policy + DataScope (`own`/`team`/`department`/`company`); `LeaveRequestPolicy` motor kaydı varken `canApprove`, yoksa team/dept legacy.
- **Katman ayrımı (4B):** Motor = kim onaylamalı; Policy = onaylayabilir mi. Köprü `processAuthorizedApproval` Policy sonrası canApprove tekrarlamaz.

---

## 8. Frontend Envanteri (özet)

- Company: operasyonel modüller + `/lookups`, `/settings/*`, `/account/*`.
- Portal: self-servis izin/masraf/anket.
- Superadmin: firma/lisans.
- Ortak: `@alatax/shared` (api client, DataTable, Select, i18n `t()`).

---

## 9. İş Akışları ve Otomasyon (4B güncel)

```
LeaveRequest store
  → WorkflowService::startWorkflow
  → ApprovalInstance + koşula göre atlanan adımlar (SKIPPED)
  → ilk pending ApprovalRecord + ApprovalRequested event → in-app stub notification
Approve (köprü)
  → Policy → processAuthorizedApproval → sonraki adım / onWorkflowCompleted (bakiye)
Reject → onWorkflowRejected; resubmit → YENİ instance (eski geçmiş kalır)
```

- **B4 henüz yok:** `parallel_group` / `completion_policy` şemada; runtime yok; eskalasyon job yok.
- **Legacy:** akış yoksa pending + warning log (otomatik onay yok).

---

## 10. Loglama / Güvenlik

- `ActivityLog`, `Auditable` trait (Faz 2).
- Rate limit: auth/api.
- Hassas alanlar Resource’ta izinle.

---

## 11–12. Ayarlar / Rapor

- Ayarlar Stüdyosu iskelet; Form Engine / Workflow UI yok.
- Hazır raporlar kısmi; self-servis BI → Faz 5.

---

## 13. Ortam

- `.env` değişken **isimleri** (değer yazılmaz): `APP_*`, `DB_*`, `SANCTUM_*`, `MAIL_*`, `FRONTEND_URLS_*`, queue/cache/session driver’ları.
- CI: GitHub Actions (Pint + PHPUnit Postgres + FE lint/build) — branch `faz4-form-engine`.

---

## 14. Test ve Kalite

| Metrik | Değer (13 Tem 2026) |
|--------|---------------------|
| Listelenen test metodu | ~288 (`--list-tests` `::` satırı) |
| Feature test dosyası | 28 |
| Unit test dosyası | 3 |
| 4B motor testleri | B0:6 + B1:3 + B2:3 + B3:3 = **15** (yeşil) |
| Faz 2 LeaveRequestPolicy | 7 test — motor sonrası **yeşil** (zayıflatılmadı) |
| Formatter | Laravel Pint |

---

## 15. Üçüncü Parti

- Mail (SMTP), Storage disk, Telescope (dev), SMS servis iskeleti (SmsService — ⚠️ kullanım yaygınlığı kısmi).

---

## 16. Teknik borç / TODO (onay odaklı)

- Eskalasyon scheduler / paralel grup runtime (B4).
- Stüdyo akış editörü (B5).
- Expense/document’i motor’a bağlama.
- 4C: olay kataloğu + şablon kanalları (stub database notification var).
- `ApprovalRecord::moveToNextStep` hâlâ model içinde — ileride service’e taşınabilir.

---

## 17. Genel Değerlendirme

| Alan | Durum |
|------|-------|
| Çekirdek HR CRUD | ✅ |
| Multi-tenant + RBAC + DataScope | ✅ |
| Lookup / Select / Kanban | ✅ |
| Onay motoru (izin pilot, B0–B3) | ✅ |
| Form Engine / Stüdyo workflow UI | ❌ |
| Paralel + eskalasyon | ❌ (şema hazır) |
| Rapor builder | ❌ |
| Bildirim merkezi 4C | 🔶 stub |

**Eski snapshot kıyas:** Dosya yoktu; bu sürüm Faz 4 Lookup + 4B motor bağlama sonrası baseline.

---

*Bu belge mevcut durum tespitidir; öneri içermez. Güncelleme: her büyük faz bitişinde yeniden üret.*
