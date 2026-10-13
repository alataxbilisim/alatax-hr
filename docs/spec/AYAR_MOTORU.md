# Ayar Motoru (Settings Registry)

## D4a

**Durum:** tamamlandı (2026-07-30) · branch `faz4-form-engine`

### Teşhis (özet)

| Kaynak | Ne |
|--------|-----|
| `companies.settings` JSONB | general/smtp/sms/notifications/integrations/report_privacy |
| `leave_types` / `accrual_policies` | tür ve hakediş entity alanları (CRUD ayrı kalır) |
| Lookup Engine | liste değerleri — **birleştirilmedi** |
| Stüdyo `/settings/forms|workflows|notification-templates|…` | motor yüzeyleri — **dönüştürülmedi** |

**Lookup vs Settings:** Lookup = seçenek listeleri; Settings = tolerans, TTL, negatif bakiye, saklama süresi vb.

### Mimari

- **Registry:** `SettingsRegistry` + `SettingDefinition` (D1a DatasetRegistry deseni)
- **Depolama:** `setting_values` (company_id nullable, scope_type/scope_id, value jsonb)
- **Çözümleme:** user → department → branch → company → system → default (+ legacy `company.settings` köprüsü)
- **API:** `GET/PUT /api/v1/settings/*` — mevcut `/company/settings` bozulmadı
- **UI-1:** `/settings/registry` · **UI-2:** `@shared` `PageSettingsButton`
- **Yasal taban:** `legal_*` system ayarları; altına set → 422 «Yasal asgari X»

### Pilot migrasyon

| Key | Not |
|-----|-----|
| `leaves.balance.allow_carryover` | yeni |
| `leaves.balance.allow_negative` | `LeaveCalculationService::checkBalance` okur |
| `leaves.request.min_days_notice` | firma önceliği |
| `leaves.retention.months` | + legal min |
| `reports.privacy.min_cell_*` | registry + legacy köprü |
| `reports.cache.default_ttl_seconds` | `ReportResultCache::resolveTtl` |

**DUR:** diğer modül ayarları Faz 6 kendi dalgalarında.

### Test / kararlılık

| Kanıt | Sonuç |
|-------|--------|
| `SettingsRegistryD4aTest` | **9 passed** |
| Tam suite | **576 passed / 0 fail** |
| 3 SPA tsc + lint | **0** |
| Sentinel `admin@demo.test` @ `alatax_hr` | **yes** |
| Flaky fix (önce) | 3× + `--order-by=random` → 567 pass |
