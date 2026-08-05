# FAZ G2 — Tasarım kararları

**Branch:** `faz4-form-engine` · **Tarih:** 5 Ağustos 2026  
Kod yazılmadan önce kilitlenen iki karar.

---

## (a) “Grup” kimin için ne demek?

| Aday | Sonuç |
|------|--------|
| Organization’daki tüm şirketler | **Red.** Tek otele üye müdür, org’daki diğer otelleri de raporlar. |
| Yalnız kullanıcının membership’leri | Kısmi. Org dışı membership “grup” anlamını bulandırır. |
| **organization ∩ membership + `reports.scope.group`** | **Seçilen.** |

**Gerekçe (Tur6 ile uyum):** Membership = kullanıcı o şirkette kendi **global** Spatie rolüyle çalışır; şirkete özel rol yok (`company_user.role_id` NULL). Bu yüzden “üyeyim” ≠ “grubun tamamını raporlarım”. Grup rapor **ekstra açık izin** ister.

**Küme tanımı:**

1. Aktif operasyonel şirketin (`CompanyContext` / `getCompanyId()`) `organization_id`’si alınır.
2. Aynı `organization_id` altındaki şirketler ∩ kullanıcının `company_user` satırları.
3. `reports.scope.group` yoksa `scope=group` → **403**.
4. Kümede tek şirket kalırsa group, company ile aynı satır kümesini döner (hâlâ izin gerekir).

Operasyonel CRUD (personel, izin, masraf…) **group kullanmaz**; yalnız aktif şirket.

---

## (b) KVKK dışarıda

Grup kapsamı **hiçbir koşulda** KVKK dataset / ekran / API yollarına uygulanmaz:

- Veri sahibi talepleri (DSR)
- İmha adayları / onayları
- Legal hold
- Veri ihlali
- Saklama politikaları

**Gerekçe:** KVKK’da veri sorumlusu **şirket** bazındadır; holding agregasyonu yasal süreçleri karıştırmaz. Dataset registry’de KVKK satırı yoktur; KVKK controller’ları `scope=group` parametresini yok sayar veya reddeder ve satırlar yalnız aktif şirkette kalır.

Bu kural feature testi ile sabitlenir.

---

## Özet kurallar (uygulama checklist)

| Kural | Değer |
|-------|--------|
| Varsayılan rapor/pano kapsamı | `scope=company` (aktif CompanyContext) |
| Grup kapsamı | org ∩ membership + `reports.scope.group` |
| CRUD / operasyonel | her zaman tek aktif şirket |
| KVKK | her zaman tek aktif şirket; group yok |
| Cache imzası | `{report_scope, sıralı company_ids, …}` |
| Portal | tek personel kaydı / home kuralı (Tur5–6); group yok |

---

## (c) DataScopeLevel::Group — ara durum kapatıldı (B)

| Seçenek | Sonuç |
|---------|--------|
| (A) CHECK’e `group` ekle + rol `data_scope=group` | Reddedildi |
| **(B) `DataScopeLevel::Group` koddan çıkar** | **Seçilen** |

**Gerekçe:** Grup rapor kapsamı zaten `reports.scope.group` + `ReportQueryBuilder` `scope=group` ile yönetiliyor. İkinci bir `roles.data_scope=group` yolu, Faz 6’da 14 modül gelirken “hangisi geçerli?” belirsizliği yaratır. CHECK’e yazılmayan ama enum’da duran Group değeri testlerde config default ile sahte yeşil üretiyordu — gerçek DB yolu sınanmıyordu.

**Sonuç:** Satır düzeyi DataScope = own…company. Çok şirket = yalnız rapor/pano izin + scope parametresi.
