# Export Güvenliği + Entegrasyon Ekleri

**Tarih:** 6 Ağustos 2026 · **Branch:** `faz4-form-engine` · **Push:** yok  
**Kapsam:** Legacy export yetki kapatma · `field_key` immutability · `ENTEGRASYON_SPEC` §5–6

---

## 1) Legacy `exportExcel` yetki açığı — kapatıldı

| | |
|--|--|
| **Sorun** | `EmployeeReportController::exportExcel` `canViewSalary` / `requires_permission` atlıyordu; JSON `reports/data` kontrol ediyordu. |
| **Test (önce kırmızı)** | `EmployeeFieldPermissionTest::test_report_export_excel_salary_measure_forbidden_without_permission` — beklenen 403, alınan **200**. |
| **Düzeltme** | Export yoluna JSON ile aynı doğrulama (geçersiz boyut/metrik + maaş izni). |
| **Sonuç** | Test yeşil (403). |

### Personel liste CSV — izin filtresi

`EmployeeController::export` kolonları `exportColumnsForUser()` ile üretilir. `permission => salary|tckn` olan kolonlar izinsiz kullanıcıda düşer. Bugün ücret/TCKN kolonları set’te yok (yorumda örnek); ileride eklendiğinde kapı hazır.

---

## 2) `field_key` değişmezliği — kapatıldı

| Katman | Davranış |
|--------|----------|
| API update | `field_key` / `system_key` / `entity_type` → **`prohibited`** |
| Model | `updating` → `field_key` dirty ise `RuntimeException` |
| Test | `CustomFieldKeyImmutabilityTest` — key reddi · etiket OK · `cf_*` rapor kolonu sağlam · model guard |

---

## 3) Export tarama tablosu

| Yol | Dosya / uç | Alan izni | Veri kapsamı (DataScope) | Açık / not |
|-----|------------|-----------|--------------------------|------------|
| **Rapor motoru** export | `ReportDefinitionService::export` → `ReportQueryBuilder` | ✅ `filterAllowedFields` / `hidden_fields` | ✅ `applyDataScope` | — |
| **Legacy personel rapor** Excel | `EmployeeReportController::exportExcel` | ✅ **düzeltildi** (maaş metrikleri) | ⚠️ yalnız `company_id`; liste DataScope yok | DataScope hizası ayrı iş |
| **Personel liste CSV** | `EmployeeController::export` | ✅ kolon filtresi (salary/tckn kapısı) | ❌ `scopeForEmployee` yok (index’te var) | **DataScope eksik** |
| **Kullanıcı liste CSV** | `UserController::export` | ❌ (hassas alan seti yok; telefon vb.) | ❌ membership/scope yok; `home_company_id` filtre | Alan izni + kapsam ayrı |
| **Aktivite log CSV** | `ActivityLogController::export` | N/A (audit alanları) | ⚠️ tenant `company_id` (SuperAdmin bypass) | Satır kapsamı (own) yok |
| **Puantaj raporu** Excel | `AttendanceReportController::export` | N/A (ücret yok) | ✅ `scopeForUser` | — |
| **KVKK** paket export | `PersonalDataExportService` / collector’lar | ⚠️ DSAR amacı: maaş dahil kişisel paket; `salary.view` RBAC değil | Tenant + talep sahibi | Bilinçli ayrı tehdit modeli |
| **Dashboard** Excel | `EmployeeDashboardController::exportExcel` | ✅ maaş widget atlanır | ⚠️ company_id | — |

**Sonraki iş (bu turda düzeltilmedi):** personel/kullanıcı liste export DataScope; kullanıcı CSV alan politikası; legacy rapor DataScope.

---

## 4) ENTEGRASYON_SPEC ekleri

- **§5 Özel alan yaşam döngüsü** — ayrı namespace, değişmez key, etiket serbest, terfi yok, onaylı taşıma + emeklilik.
- **§6 Senkronizasyon** — üç tetikleyici, su işareti / hash, uygulama kuralları, şema doğrulama, sonuç raporu zorunlu.

---

## 5) Test / commit

- Odak: `EmployeeFieldPermissionTest` (10) + `CustomFieldKeyImmutabilityTest` (3) → **13 passed**.
- Tam suite: **762 passed** (Docker `alatax-hr-app`).
- Push yok.
