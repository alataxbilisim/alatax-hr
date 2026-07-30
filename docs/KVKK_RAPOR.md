# KVKK — Rapor

## D2b — Veri sahibi talepleri + kişisel veri ihracı

**Tarih:** 2026-07-30 · Branch: `faz4-form-engine`

### Teşhis (özet)

D2a envanteri kategori/faaliyet düzeyinde; ihraç için kolon envanteri değil → **PersonalDataCollector** registry ile tamamlandı.  
Tablo dağılımı: `employees`, `leave_requests`, `leave_balances`, `attendance_records`, `expense_claims`, `payslips`, `employee_documents`, `assets`/`asset_assignments`, `training_participants`, `survey_responses` (anonim hariç), `consent_records`, `activity_logs`, `notifications`, `job_applications`, performans/onboarding…  
Workflow: `entity_type=data_subject_request` → mevcut motor.

### Collector registry (15)

employee_profile, leave, attendance, expense, payslip, employee_document, asset_assignment, training, survey, consent, activity_log, notification, job_application, performance, onboarding.  
DoD: kişisel veri tutan modül collector kaydetmeden bitmiş sayılmaz. `destroy()` D2c.

### Talep şeması

`data_subject_requests` + `data_subject_export_packages` + `data_subject_export_access_logs`.  
due_date=+N (`kvkk.data_subject.response_days`, legal_max=30). Kimlik yok → paket **422**. Kanallar: portal / public (e-posta) / İK manuel.

### Paket güvenliği

Private disk · UUID · süre sonu otomatik silme · e-posta eki yok · İK önizleme yok · indirme logu · A≠B kapsam testi yeşil.

### Silme sınırı

Onay → `destruction_pending`; veri **silinmez** (D2c).

### Test / CI

| | |
|--|--|
| `KvkkD2bTest` | 10 passed |
| Suite | **586 passed** (tek koşu, 0 fail) |
| tsc ×3 + lint | 0 |
| Sentinel / Actions | push sonrası |

---

## D2a — Veri envanteri + aydınlatma metinleri + rıza kayıtları

**Tarih:** 2026-07-29  
**Commit:** feat(kvkk): D2a veri envanteri + aydınlatma metinleri + rıza kayıtları  
**Branch:** `faz4-form-engine`

### ADIM 0 — Teşhis

**D1e hassasiyet:** `ReportField::SENSITIVITY_*` (`normal` | `personal` | `special` | `anonymous_source`) — dataset registry alanlarında. D2a ikinci sınıflandırma açmaz; `KvkkDataCategoryCatalog` bu sabitleri kullanır.

| Tablo | Örnek kolonlar | D1e / kategori |
|-------|----------------|----------------|
| employees | national_id, address, phone, birth, blood, iban, emergency_* | personal / special (kan) |
| job_applications | ad, e-posta, telefon, cv_path, form_data | personal (cv_recruitment) |
| leave_requests | reason, document | special (health) |
| employee_documents | category=health, file | special |
| attendance_records | lat/lng, IP | personal (location) |
| survey_responses | answer_* | anonymous_source |
| payslips | net/gross | personal (financial) |

**Audit:** `Auditable` → ActivityLog diff; yeni KVKK modelleri Auditable. Rapor erişim logu (D1e) ayrı hesap verebilirlik katmanı.

### Şema (3 tablo)

- `data_processing_activities` — VERBİS omurgası; `data_categories` jsonb (katalog key); `transfer_abroad` varsayılan **false** + on-prem notu
- `privacy_notices` — audience + version; yayınlandıktan sonra immutable
- `consent_records` — soft delete yok; `withdrawn_at` ile geri çekme

### Rıza tipleri

`aydinlatma_okundu` | `acik_riza_ozel_nitelikli` | `acik_riza_yurtdisi` | `ticari_elektronik_ileti` | `diger`  
UI’da tek checkbox birleştirilmez.

### Yüzeyler

- İK: `/kvkk` — Envanter · Aydınlatma · Rıza (`management.kvkk.view|edit`)
- Portal: aydınlatma modalı (onaysız devam OK; kayıt “görüntülendi/onaylanmadı”)
- Public başvuru: iki ayrı onay + `consent_records`

### Test / CI

| Suite | Sonuç |
|-------|--------|
| `KvkkD2aTest` | **6 passed** |
| Tam suite | **567 passed / 0 fail** |
| 3 SPA tsc + lint + sentinel | **PASSED** |
| DB wipe | **yok** |

**Hukuki uyarı:** metin/saklama süreleri koda gömülmez — firma doldurur.

**Bu dalga veri silmez / anonimleştirmez** (D2b/D2c).
