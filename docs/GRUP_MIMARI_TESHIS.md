# G0 — Grup / Holding Mimari Teşhis

**Tarih:** 31 Temmuz 2026 · **Branch:** `faz4-form-engine` · **Kod yazılmadı**  
**Karar çerçevesi (verilmiş — tartışma değil):** SEÇENEK 1 + **hibrit kapsam**

| Katman | Davranış |
|--------|----------|
| Operasyonel ekranlar | **Tek aktif şirket** bağlamı (üst bar seçici; A6 şube deseni). `BelongsToCompany` mantığı değişmez — yalnız aktif şirketi okur. |
| Rapor / pano | **Grup kapsamı** — erişilebilir şirketler birlikte, şirket kırılımıyla |
| Kullanıcı ↔ şirket | Çoktan-çoğa; bir kullanıcı N şirkete erişebilir |
| Yetki | Şimdilik tüm şirketlerde **aynı rol** (eşleme tablosunda rol kolonu açılır, boş kalır) |

**Ölçek aralığı (ürün çerçevesi):** tek şubeli ~10 kişi → yüzlerce şube / on binlerce personel; holding’de birden çok şirket; merkez İK. Pilot müşteri gereksinim kilidi değildir (`ROADMAP.md` §0).

**Önceki belge:** `FAZ_A_RAPOR` §A3 (14 Tem) — bu dosya onu günceller; A3’teki `branch` stub ve “Employee.branch_id yok” ifadeleri **artık geçersiz**.

---

## ADIM 1 — A3 teşhisinin güncel hali

### 1.1 `BelongsToCompany` bugün ne yapıyor?

**Dosya:** `app/Traits/BelongsToCompany.php`

| Davranış | Detay |
|----------|--------|
| Global scope adı | `company` |
| Filtre | `WHERE {table}.company_id = auth.user.company_id` |
| SuperAdmin | `UserType::SuperAdmin` → scope **uygulanmaz** (tüm tenant’lar görünür) |
| Creating hook | Auth varken ve `company_id` boşsa → `user.company_id` atanır (SuperAdmin hariç) |
| Yardımcılar | `scopeForCompany`, `scopeWithoutCompanyScope` |

**Kullanım:** `app/Models` altında **~59 model** `use BelongsToCompany`.

**Bilinçli istisnalar (trait yok):**

| Model | Neden |
|-------|--------|
| `Lookup` | `company_id` nullable — sistem + firma override birleşimi |
| `SettingValue` | System satırlarında `company_id` null |
| `User`, `Company`, `Role`, … | Platform / auth modelleri |

**Hibrit notu:** Trait bugün **yalnızca** `auth()->user()->company_id` okur. Operasyonel “aktif şirket” için ya (a) istek bağlamı trait’e bağlanır (`CompanyContext` → tek id; scope hâlâ `=`), ya (b) `users.company_id` oturumda geçici güncellenir (anti-pattern). Öneri yönü: A6’daki gibi **request-scoped context**; trait’in “tek şirket eşitliği” korunur, kaynak `user.company_id` yerine aktif bağlam olur.

---

### 1.2 `getCompanyId()` ve company_id desenleri

| Desen | Ölçü (kod taraması) |
|-------|---------------------|
| `BaseController::getCompanyId()` | `auth()->user()?->company_id` |
| `getCompanyId(` çağrısı | **~384** (70 controller dosyası) |
| `$user->company_id` / `auth()->user()->company_id` doğrudan | **~99** dosya (`app/`) |

**Desenler:**

1. **Liste/oluştur:** `$this->getCompanyId()` → query `where` / create payload  
2. **Portal:** çoğunlukla `$user->company_id` açık yazılmış  
3. **Servisler:** parametre olarak `int $companyId` (rapor, ayar, KVKK) — çağıran controller’dan gelir  
4. **Global scope’a güven:** bazı yerler ek `where(company_id)` yazmadan BelongsToCompany’ye güvenir

**Hibrit etkisi:** Operasyonda `getCompanyId()` → **aktif şirket bağlamı** dönmeli (membership doğrulamalı). Rapor/pano yolu ayrı API veya `scope=group` bayrağı ile `accessibleCompanyIds()` kullanmalı; aksi halde merkez İK yanlışlıkla tek şirkete kilitlenir.

---

### 1.3 A6 şube bağlamı — şirket bağlamı kopyası olabilir mi?

| Parça | Bugün (şube) | Şirket bağlamı (hedef) |
|-------|--------------|------------------------|
| Header | `X-Branch-Id` (`all` veya id) | Öneri: `X-Company-Id` (zorunlu tek id; ops’ta `all` **yok**) |
| Middleware | `branch.context` → `ResolveBranchContext` | `company.context` benzeri |
| Container | `BranchContext` DTO | `CompanyContext` DTO |
| Servis | `BranchContextService` — DataScope tavanı içinde daraltır | Membership + aktif şirket doğrulama |
| FE | Redux `branchContextSlice` + `localStorage` `alatax_branch_id` | Aynı desen: slice + `alatax_company_id` |
| Axios | `@shared/services/api.ts` interceptor | Aynı interceptor’a şirket header |
| Global scope | Yalnız `Employee`’a ek scope (`branch_id`) | Ops: BelongsToCompany kaynağını context’ten okur |
| Endpoint | `GET /context/branches` | `GET /context/companies` |

**Birebir kopya mı?** Kabuk (header + MW + FE Select + localStorage + interceptor) **evet**. Ayrışan noktalar:

| # | Ayrışma |
|---|---------|
| 1 | Şube **daraltır** (DataScope içi); şirket **tenant sınırı** — yanlış şirket = sızıntı veya boş veri |
| 2 | Şubede `all` var (company scope); operasyonel şirkette hibrit karara göre **`all` yok** |
| 3 | Şube listesi `user.company_id` altındaki branches; şirket listesi **membership** tablosundan |
| 4 | Rapor/pano şirket seçiciden bağımsız **grup** yolu ister — şubede karşılığı yok |
| 5 | `Employee` şube scope’u nested: aktif şirket değişince şube listesi yenilenmeli |

---

### 1.4 `DataScopeService` — seviyeler (A3 sonrası)

**Enum genişlik:** `own < team < department < branch < company` — **`group` yok**.

| Seviye | A3 (14 Tem) | Bugün (31 Tem) |
|--------|-------------|----------------|
| `company` | Ek filtre yok | Aynı — BelongsToCompany yeter |
| `own` / `team` / `department` | Çalışıyor | Çalışıyor |
| `branch` | **Stub** `whereRaw('0=1')` | **Gerçek:** `Employee.branch_id` + `branchUserIds` / `applyBranchEmployeeScope` |
| `group` | Yok | Yok — Faz G |

**Resolve:** SuperAdmin / CompanyAdmin → `company`. Roller arasından en geniş `data_scope` kazanır (`config/data-scope.php` defaults).

**A6 ilişkisi:** `BranchContext` DataScope’u **genişletmez**; company-scope kullanıcıda seçilen şubeye daraltır; branch-scope kullanıcıda kilitli şube.

**Hibrit + group:** Operasyonda DataScope hâlâ şirket-içi (aktif company altında own/team/dept/branch). Rapor/pano’da ya `DataScopeLevel::Group` ya da rapor motoruna özel `company_id IN (...)` + dimension `company_id`. Öneri yönü: enum’a `group` eklenir ama **yalnız rapor/pano / açıkça group-allowed** yollarda; operasyonel BelongsToCompany tek şirket kalır.

---

## ADIM 2 — Company-scoped motorlar (grup gelince ne olur?)

> Her madde: **Öneri + gerekçe**. Karar kullanıcıda.

### 2.1 Rapor motoru

| Bileşen | Bugün | Grup gelince | Öneri |
|---------|--------|--------------|--------|
| Dataset registry | Model + BelongsToCompany | Ops tek şirket; grup sorgusu `whereIn` veya scope without + IN | **Dataset’e `supportsGroupScope(): bool`**; varsayılan false, personel/izin/puantaj true |
| Query builder | `applyDataScope` + global company scope | Grup: context’te tek company yerine set; dimension `company_id` zorunlu kırılım opsiyonu | **Ayrı execute yolu** `scope=group` — operasyonel `getCompanyId()` ile karışmasın |
| Cache `scope_sig` | `ReportScopeSignature`: **`company_id` + user_id + data_scope + scope_values + field_perms`** | Tek şirket imzası grup cache’ine sızmamalı | Grup cache: `organization_id` + `sorted(company_ids)` + user + scope; şirket cache ayrı kalsın |
| Paylaşım | `report_shares.company_id` | Paylaşım şirket veya grup? | **Şirket bazlı paylaşım kalır**; grup raporu “org içi roller” ile ayrıca (sonra) |
| Zamanlanmış rapor | `report_schedules.company_id` + alıcı resolve company’de | Grup schedule? | **v1: şirket bazlı schedule**; grup schedule Faz G+ (alıcı hangi şirket bağlamında?) |
| Erişim logu | `report_access_logs.company_id` | Grup koşuda hangi company? | Log’a **`organization_id` + `company_ids[]` (json)** veya satır başına company; audit’te şirket ayrımı şart |

**Gerekçe:** Hibrit modelde rapor zaten tek “kaçış kapısı”; cache/paylaşım/schedule yanlış company_id ile sızıntı üretir. Scope imzasında company seti şart.

---

### 2.2 Pano + ölçü kütüphanesi

| Bileşen | Bugün | Öneri |
|---------|--------|--------|
| `dashboards` | `company_id` (+ sistem `company_id null`) | **Şirket panoları** aktif şirkette; **grup panosu** ayrı `scope=group` / `is_group` — widget’lar grup dataset kullanır |
| `dashboard_shares` | company scoped | Şirket panosu paylaşımı şirket içi kalır |
| `report_measures` | unique `(company_id, dataset_key, key)` | **Şirket başına ölçü** (hesap firmanın politikasına göre değişebilir); grupta ortak “sistem ölçü” zaten `company_id null` olabilir — genişletme ihtiyatlı |

**Gerekçe:** Dobedan merkez İK grup KPI ister; operasyonel şube müdürü kendi şirket panosunda kalmalı.

---

### 2.3 Ayar motoru

**Bugün çözümleme:** `user → department → branch → company → system → definition default` (`SettingScopeType`). **`group` yok.**

| Soru | Öneri |
|------|--------|
| `group` eklensin mi? | **Evet (opsiyonel seviye)** — zincir: `user → dept → branch → company → **group** → system → default` |
| Hangileri group? | Marka/logo, grup geneli rapor gizlilik tabanı, ortak SLA varsayılanı gibi **az sayıda** anahtar; mesai/izin politikası **şirket** kalsın |
| Cache | Firm cache key bugün company; group satırları `organization_id` ile ayrı invalidation |

**Gerekçe:** Zincire group eklemek ucuz; tüm ayarları group yapmak holding’de şirket otonomisini öldürür (otel şirketleri farklı mesai/izin uygulayabilir).

---

### 2.4 KVKK

| Varlık | Veri anlamı | Öneri | Gerekçe |
|--------|-------------|--------|---------|
| `data_processing_activities` | Envanter / hukuki dayanak | **Şirket** (hukuki kişi) | Aydınlatma yükümlülüğü tüzel kişilikte |
| `privacy_notices` | Versiyonlu metin | **Şirket** (+ isteğe bağlı group şablon kopyala) | Metin şirket unvanı/adresi taşır |
| `consent_records` | Kişi rızası | **Şirket** (subject o şirketin çalışanı) | Rıza işlenen veri sorumlusuyla bağlanır |
| `retention_policies` | Saklama kuralı | **Şirket** (group’tan seed/kopya serbest) | İmha hukuki kişi bazlı; yanlış aktif politika riski |
| `destruction_logs` / adaylar | İmha kanıtı | **Şirket** | Audit’te şirket ayrımı zorunlu |

**Grup UI:** Merkez İK “tüm şirketlerin KVKK durumu” panosu = **agregasyon**, tek birleşik politika tablosu değil.

---

### 2.5 Onay motoru

| Bugün | Öneri |
|-------|--------|
| `approval_workflows.company_id` — şirket başına | **Şirket başına kalır** (v1) |
| Grup geneli ortak akış | **İsteğe bağlı sonra:** group şablon → şirketlere klon; runtime instance yine `company_id` taşır |

**Gerekçe:** Onaycı çözümleme (`dynamic_manager`, rol, user) personel ağacına bağlı; çapraz şirket onaycı v1’de belirsiz ve sızıntı riski yüksek. Hibrit operasyon zaten tek aktif şirkette akış çalıştırır.

---

### 2.6 Bildirim şablonları · form tanımları · lookup’lar

| Varlık | Bugün | Öneri | Gerekçe |
|--------|--------|--------|---------|
| `notification_templates` | unique `(company_id, event_key)` | **Şirket** (+ sistem/lang varsayılan) | Metin/marka şirket diline göre |
| `form_definitions` | unique `(company_id, entity_type)` | **Şirket** | Özlük alan seti şirket/sektöre göre değişir |
| `lookups` | sistem + `(company_id, type, value)` override | **Sistem ortak + şirket override** (mevcut model) | Group seviye lookup **gerekmez**; gerekirse system’e eklenir |
| İzin türleri / departman / pozisyon | şirket | **Şirket tekrarı** (group şablon klon) | Sicil/org şeması şirket gerçeği |

---

## ADIM 3 — Çakışma ve bütünlük riskleri

### 3.1 Unique `(company_id, …)` desenleri

Grup genelinde **çakışmaz** — unique şirket içinde. Aynı `employee_code` / `branch.code` / `department.code` iki şirkette serbest.

| Risk | Not |
|------|-----|
| Merkez İK “sicil no global tek” isterse | Bugünkü şema **zorlamaz**; ürün kuralı + opsiyonel org-unique indeks ayrı karar |
| `attendance_records` unique `(user_id, date)` | **company_id yok** — bir user iki şirkette aynı gün iki puantaj tutamaz |

---

### 3.2 Kullanıcı e-postası (kritik)

| Katman | Bugün |
|--------|--------|
| `users.email` | **Global unique** |
| `employees.personal_email` | Nullable, unique değil |
| `employees` unique | `(company_id, employee_code)`, `(company_id, user_id)` |
| `User::employee()` | **`hasOne`** — tek employee varsayımı |

**Sonuç:**

- Bir **login kimliği** = bir e-posta (global).  
- DB seviyesinde aynı `user_id` **farklı şirketlerde** birer `employees` satırı tutabilir (`(company_id, user_id)` unique bunu engellemez).  
- Eloquent `hasOne` + Portal akışları **tek employee** varsayar → “iki şirkette çalışan” bugün **ürün olarak desteklenmiyor**.

**Eşleme modeli etkisi (öneri yönü — karar değil):**

| Senaryo | Öneri modeli |
|---------|----------------|
| Merkez İK / müdür N şirket yönetir | `organization_user` / `company_user` membership; **tek User**; opsiyonel tek “home” `users.company_id` |
| Aynı kişi iki şirkette bordrolu çalışan | Ya (A) tek User + N Employee (Portal’da şirket seçici veya “aktif iş ilişkisi”) — büyük FE/BE işi; ya (B) **iki User** (iki e-posta) — basit, Dobedan v1’e yakın | 

**Öneri (v1):** Portal personeli **1 şirket / 1 employee**; multi-company yalnız **yönetici membership**. Çift şirket çalışan = bilinçli Faz G+ veya iki hesap.

---

### 3.3 Portal

| Kontrol | Durum |
|---------|--------|
| Şirket/şube seçici | **Yok** (Company app’te A6 var) |
| Veri | `$user->company_id` + kendi kaydı |
| Hibrit uyum | **Uygun** — bağlam seçici eklenmemeli (görev kararı) |

Risk: Membership’li yönetici Portal’a düşerse hangi company? → Portal’da yalnız `employee` bağlı şirket; yoksa 403.

---

### 3.4 Tanımlar: tekrar mı, grup ortak mı?

| Tanım | Öneri |
|-------|--------|
| Departman / pozisyon / şube | **Şirket** |
| İzin türü / hakediş politikası | **Şirket** (A1 seed şirket bazlı) |
| Lookup sistem değerleri | **Ortak (system)** |
| Onay akışı / form / bildirim şablonu | **Şirket** (yukarı ADIM 2) |

---

## ADIM 4 — Ölçek aralığı kontrolü (küçük ofisten kurumsal holding’e)

### 4.1 Hacim tahmini (üst sınır örneği)

| Varsayım | Değer (aralık / örnek üst sınır) |
|----------|--------|
| Personel | ~10 (küçük) → on binlerce (kurumsal); teşhis örneği ~binler–on binler grup |
| Şirket / şube | 1 şirket 1 şube → onlarca şirket / yüzlerce şube |
| Puantaj | personel × ~250 iş günü/yıl × saklama yılı → milyonlarca `attendance_records` |
| İzin | Personel × birkaç talep/yıl → düşük yüzbinler–milyonlar |

### 4.2 Mevcut indeksler (D1f / A3)

| İndeks | Var mı? |
|--------|---------|
| `employees (company_id, department_id, status)` | ✅ D1f |
| `employees (company_id, branch_id)` | ✅ A3 migration |
| `leave_requests (company_id, status, start_date, end_date)` | ✅ D1f |
| `attendance_records (company_id, date)` | ❌ **yok** |
| `attendance_records (company_id, user_id, date)` | ❌ (yalnız `unique(user_id, date)`) |

### 4.3 Riskli sorgular

| Sorgu | Risk | Not |
|-------|------|-----|
| Grup puantaj raporu `company_id IN (...) AND date BETWEEN` | **Yüksek** | Seq scan / user_id unique tersine join |
| Grup personel + dept kırılımı | Orta | D1f indeksi tek company’de iyi; `IN` listesi küçük (≤10) OK |
| Cache’siz pivot 1.5M satır | **Yüksek** | D1f cache + aggregate şart; timeout |
| QR 500+ eşzamanlı | Ayrı (PDKS) — grup şemasından bağımsız; connection pool |

### 4.4 `company_id IN (...)` indeksleri

PostgreSQL, küçük IN listelerinde `(company_id, …)` bileşik indeksleri **kullanabilir**. Asıl boşluk: **attendance** üzerinde company+date yok.

**Öneri (Faz G Dalga 1 veya hemen önce):**  
`attendance_records (company_id, date)` ve mümkünse `(company_id, status, date)`.

---

## ADIM 5 — Uygulama planı taslağı (hibrit · 3 dalga)

### Dalga G1 — Temel kimlik + operasyonel şirket bağlamı

| | |
|--|--|
| **Kapsam** | `organizations` + `companies.organization_id`; `company_user` (user_id, company_id, role_id nullable); `X-Company-Id` + MW + FE Select (A6 kopyası); `getCompanyId()` / BelongsToCompany aktif şirketi okur; membership doğrulama; Portal dokunulmaz |
| **Dosya (tahmini)** | 25–40 (migration, modeller, MW, BaseController/trait, FE layout/slice/api, auth formatUser) |
| **Risk** | 🔴 Yüksek (tenant sınırı) |
| **Tehlikedeki testler** | Tüm Policy/DataScope feature suite; login/me; BranchContext (şirket değişince şube listesi); DemoSeeder |

### Dalga G2 — Rapor/pano grup kapsamı + cache/imza

| | |
|--|--|
| **Kapsam** | Report execute `scope=group`; dataset whitelist; `ReportScopeSignature` set imzası; grup pano tipi; erişim logu organization/company set; attendance indeksleri |
| **Dosya (tahmini)** | 20–35 (ReportDefinitionService, QueryBuilder, DashboardService, cache, FE builder scope UI) |
| **Risk** | 🔴 Yüksek (sızıntı + perf) |
| **Tehlikedeki testler** | DashboardV2*, Report*, Schedule*, AccessLog*; performans smoke |

### Dalga G3 — Ayar `group` + KVKK agregasyon UI + sertleştirme

| | |
|--|--|
| **Kapsam** | `SettingScopeType::Group` + resolver sırası; KVKK şirket kalır, grup özet panosu; onay/bildirim/form **dokunulmaz** (şirket); izolasyon test paketinin tamamı yeşil; docs/help |
| **Dosya (tahmini)** | 15–25 |
| **Risk** | 🟠 Orta |
| **Tehlikedeki testler** | Settings*; KVKK feature; regresyon full suite |

**Sıra gerekçesi:** Önce tek-şirket bağlamı sağlamadan grup rapor açmak = sızıntı. G2’den önce attendance indeksi.

---

### Cross-company sızıntı test paketi (DoD — pazarlıksız)

**Kurulum fikstürü:** Org X → Company A + Company B; Org Y → Company C.  
User UA: membership A+B, rol hr_manager (aynı). User UB: yalnız B. User UC: C. Employee EA/EB/EC.

| # | Senaryo | Beklenen |
|---|---------|----------|
| 1 | UA, `X-Company-Id=A`, `GET` employees list | Yalnız A; B yok |
| 2 | UA, header B id’si ama membership yok (sahte) | **403** |
| 3 | UA aktif A iken `GET` employees/{EB.id} | **403/404** |
| 4 | UA aktif A, leave/expense/document detay B kaydı | **403/404** |
| 5 | UA rapor `scope=company` (aktif A) | Yalnız A satırları |
| 6 | UA rapor `scope=group` | A+B; **C yok**; kırılımda company_id |
| 7 | UA grup rapor cache sonrası UB/UC farklı sonuç | Cache key ayrışır; C sızmaz |
| 8 | UA export/Excel group | Aynı satır kümesi; dosyada C yok |
| 9 | UA grup pano widget | A+B metrik; C yok |
| 10 | Bildirim/in-app: B talebi, UA aktif A | Kuyruk/policy: yanlış şirket bağlamında onaylama yok |
| 11 | Dosya indirme (document/payslip) B id, header A | **403** |
| 12 | Portal EC ile A verisi | Portal’da A yok |
| 13 | SuperAdmin istisnası regresyon | Bilinçli platform erişimi; membership bypass değil |
| 14 | Mevcut DataScope own/team/dept/branch (tek şirkette) | **Hepsi yeşil** |
| 15 | BranchContext: aktif şirket A iken B şube id | **403** |

**Paket adı önerisi:** `tests/Feature/GroupIsolation/` + Unit `CompanyContextTest`.  
**CI:** blocking; Faz G DoD = bu paket + mevcut DataScope/Policy yeşil.

---

## Özet tablo — karar bekleyen öneriler

| # | Konu | Öneri (onayınız şart) |
|---|------|------------------------|
| 1 | Ops BelongsToCompany | Tek şirket eşitliği kalsın; kaynak = `CompanyContext` |
| 2 | Rapor/pano | Ayrı group scope; cache imzasında company set |
| 3 | KVKK | Hepsi şirket; grup yalnız agregasyon UI |
| 4 | Onay / form / bildirim şablon | Şirket; group şablon→klon sonra |
| 5 | Lookup | Mevcut system+company |
| 6 | Dept/pozisyon/izin türü | Şirket tekrarı |
| 7 | Çift şirket çalışan | v1 yok; yönetici = membership |
| 8 | `users.email` | Global unique kalsın |
| 9 | Ayar | Opsiyonel `group` seviyesi (az anahtar) |
| 10 | İndeks | `attendance_records (company_id, date)` erken |

---

*G0 teşhis. Uygulama: ROADMAP Faz G (G1→G2→G3). Güncel durum özeti: `GUNCEL_DURUM_RAPORU.md`.*
