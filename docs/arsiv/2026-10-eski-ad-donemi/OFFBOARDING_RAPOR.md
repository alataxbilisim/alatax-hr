# OFFBOARDING_RAPOR

**Branch:** `faz4-form-engine`  
**Tarih:** 2026-07-16  
**Kapsam:** İşten çıkış sihirbazı (onboarding motorunun `process_type` ile genişletilmesi)

## Zincir tablosu

| Zincir | İçerik | Commit |
|--------|--------|--------|
| Z1 | `process_type`, SGK lookup (12 kod), varsayılan offboarding şablonu, şablon tip filtresi | `d4ed6a5` |
| Z2 | Sihirbaz, akıllı görevler, finalize/iptal, `employees.terminate.create` | `e3dbf8c` |
| Z3 | İbraname PDF, kalan izin günü, bu rapor | `69e565f` |

## Doğrulama

| Kontrol | Sonuç |
|---------|--------|
| Suite | **448 passed / 0 fail** (önceki 435 + 13 offboarding) |
| company + portal `tsc` | **0** |
| Select sentinel | **PASSED** |
| DB wipe | **yok** (ekleyici migration) |
| PUSH | **yok** |

## Kritik kurallar (doğrulandı)

- Onboarding motoru yeniden kullanıldı; ayrı görev motoru yok.
- `process_type` default **`onboarding`** → hire→onboarding regresyonu yeşil.
- Süreç açıkken personel **`active`**; yalnız **Çıkışı Tamamla** → `terminated`.

## SGK çıkış kodları (lookup `termination_reason`)

**12 kayıt:** 01, 02, 03, 04, 05, 08, 09, 11, 13, 17, 22, 25.

## Varsayılan şablon görevleri (`action_key`)

1. `asset_return` — açık zimmet varken tamamlanamaz  
2. `document_handover`  
3. `revoke_portal` — `is_active=false` + `employee.user_id=null` (süreç `user_id`/`employee_id` korunur)  
4. `clearance_form`  
5. `knowledge_transfer`  

## API

- `POST /api/v1/employees/{id}/offboarding` — `permission:employees.terminate.create`
- `POST /api/v1/onboarding/processes/{id}/finalize-offboarding`
- `POST /api/v1/onboarding/processes/{id}/cancel-offboarding`
- `GET /api/v1/onboarding/processes/{id}/clearance-form` — `application/pdf`

## Bildirim

Mevcut `onboarding.task_assigned` yeniden kullanıldı.

## İzin / ibraname

- Kalan yıllık izin: `LeaveBalance` (`system_code=annual`) → `remaining_leave_days` (ücret yok).
- PDF: `SimpleTextPdf` (Türkçe → ASCII yaklaşımı).
