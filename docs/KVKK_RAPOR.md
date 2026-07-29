# KVKK — Rapor

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
