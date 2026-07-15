# OFFBOARDING_RAPOR

**Branch:** `faz4-form-engine`  
**Tarih:** 2026-07-16  
**Kapsam:** İşten çıkış sihirbazı (onboarding motorunun `process_type` ile genişletilmesi)

## Özet

| Zincir | İçerik | Commit |
|--------|--------|--------|
| Z1 | `process_type`, SGK lookup, varsayılan offboarding şablonu, şablon tip filtresi | `feat(offboarding): Z1…` |
| Z2 | Sihirbaz, akıllı görevler, finalize/iptal, `employees.terminate.create` | `feat(offboarding): Z2…` |
| Z3 | İbraname PDF, kalan izin günü, bu rapor | `feat(offboarding): Z3…` |

## Kritik kurallar (doğrulandı)

- **DB silinmedi** — yalnızca ekleyici migration `2026_07_16_120000_add_process_type_and_offboarding_fields`.
- **PUSH yok.**
- Onboarding motoru yeniden kullanıldı; ayrı görev motoru yok.
- `process_type` default **`onboarding`** → mevcut hire→onboarding regresyonu korunur.
- Süreç açıkken personel **`active`** kalır; yalnız **Çıkışı Tamamla** → `terminated`.

## SGK çıkış kodları (lookup `termination_reason`)

**12 kayıt:** 01, 02, 03, 04, 05, 08, 09, 11, 13, 17, 22, 25.

## Varsayılan şablon görevleri (`action_key`)

1. `asset_return` — açık zimmet varken tamamlanamaz  
2. `document_handover`  
3. `revoke_portal` — tamamlanınca kullanıcı `is_active=false`, `employee.user_id=null` (süreç `user_id`/`employee_id` korunur)  
4. `clearance_form`  
5. `knowledge_transfer`  

## API

- `POST /api/v1/employees/{id}/offboarding` — `permission:employees.terminate.create`
- `POST /api/v1/onboarding/processes/{id}/finalize-offboarding`
- `POST /api/v1/onboarding/processes/{id}/cancel-offboarding`
- `GET /api/v1/onboarding/processes/{id}/clearance-form` — `application/pdf`

## Bildirim

Mevcut `onboarding.task_assigned` yeniden kullanıldı (ayrı `offboarding.task_assigned` eklenmedi).

## İzin / ibraname

- Kalan yıllık izin: `LeaveBalance` (`system_code=annual`) → `remaining_leave_days` süreçte; ücret hesabı yok.
- PDF: `SimpleTextPdf` (Türkçe karakterler ASCII yaklaşımı).

## Test

- `OffboardingTemplateSeedTest` + `OffboardingFlowTest` (13 test)
- Tam suite / tsc / sentinel: commit sonrası çalıştırılır.
