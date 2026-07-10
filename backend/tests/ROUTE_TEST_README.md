# Route ve Yetkilendirme Testleri

Bu dizinde tüm route'ların ve yetkilendirmelerin test edildiği kapsamlı test sınıfları bulunmaktadır.

## Test Sınıfları

### 1. RouteAuthorizationTest.php
Temel route yetkilendirme testleri:
- Public route'lar
- Protected route'lar
- SuperAdmin route'ları
- Modül bazlı route'lar
- Portal route'ları

### 2. RouteComprehensiveTest.php
Kapsamlı route testleri:
- Tüm route'ları listeleme
- Detaylı yetkilendirme testleri
- Test sonuç raporlama

## Testleri Çalıştırma

### Tüm Route Testlerini Çalıştır
```bash
php artisan test --filter RouteAuthorizationTest
php artisan test --filter RouteComprehensiveTest
```

### Sadece Route Listesini Görüntüle
```bash
php artisan test:routes
```

### Detaylı Rapor ile
```bash
php artisan test:routes --detailed
```

## Test Kapsamı

### Test Edilen Route Grupları

1. **Public Routes** (Authentication gerekmez)
   - `POST /api/v1/auth/login`
   - `POST /api/v1/auth/register`
   - `POST /api/v1/auth/forgot-password`
   - `POST /api/v1/auth/reset-password`
   - `GET /api/v1/public/companies/{slug}/jobs`
   - `POST /api/v1/public/jobs/{slug}/apply`

2. **Protected Routes** (Authentication gerekir)
   - `GET /api/v1/dashboard`
   - `GET /api/v1/auth/me`
   - `PUT /api/v1/auth/profile`
   - `PUT /api/v1/auth/password`
   - `GET /api/v1/activity-logs`
   - `GET /api/v1/notifications`

3. **Company Admin Routes** (Company Admin yetkisi gerekir)
   - `GET|POST|PUT|DELETE /api/v1/users`
   - `GET|POST|PUT|DELETE /api/v1/roles`
   - `GET|PUT /api/v1/company`
   - `GET /api/v1/company/modules`

4. **SuperAdmin Routes** (SuperAdmin yetkisi gerekir)
   - `GET|POST|PUT|DELETE /api/v1/admin/companies`
   - `GET|POST|PUT|DELETE /api/v1/admin/modules`
   - `GET /api/v1/admin/users`
   - `GET /api/v1/admin/dashboard`
   - `GET|POST|PUT|DELETE /api/v1/admin/license-packages`

5. **Module Routes** (Modül erişimi gerekir)
   - **İş Başvuru Modülü** (`job-applications`)
     - `GET|POST|PUT|DELETE /api/v1/recruitment/positions`
     - `GET /api/v1/recruitment/applications`
     - `GET /api/v1/recruitment/cv-pool`
     - `GET|POST|PUT|DELETE /api/v1/recruitment/forms`
   
   - **Evrak Yönetimi Modülü** (`document-management`)
     - `GET|POST|PUT|DELETE /api/v1/documents/categories`
     - `GET|POST|PUT|DELETE /api/v1/documents`
   
   - **Onboarding Modülü** (`onboarding`)
     - `GET|POST|PUT|DELETE /api/v1/onboarding/templates`
     - `GET|POST|PUT|DELETE /api/v1/onboarding/processes`
   
   - **İzin Yönetimi Modülü** (`leave-management`)
     - `GET|POST|PUT|DELETE /api/v1/leaves/types`
     - `GET|POST|PUT|DELETE /api/v1/leaves/requests`
     - `GET /api/v1/leaves/calendar`
     - `GET /api/v1/leaves/balance`
   
   - **Performans Değerlendirme Modülü** (`performance`)
     - `GET|POST|PUT|DELETE /api/v1/performance/periods`
     - `GET|POST|PUT|DELETE /api/v1/performance/criteria`
     - `GET|POST|PUT|DELETE /api/v1/performance/reviews`
   
   - **Eğitim Yönetimi Modülü** (`training`)
     - `GET|POST|PUT|DELETE /api/v1/training/trainings`
     - `GET|POST|PUT|DELETE /api/v1/training/sessions`
   
   - **Varlık Yönetimi Modülü** (`asset-management`)
     - `GET|POST|PUT|DELETE /api/v1/assets/categories`
     - `GET|POST|PUT|DELETE /api/v1/assets/items`
     - `GET|POST|PUT|DELETE /api/v1/assets/maintenance`

6. **Portal Routes** (Employee self-service)
   - `GET /api/v1/portal/dashboard`
   - `GET|PUT /api/v1/portal/profile`
   - `GET|POST|PUT /api/v1/portal/leaves`
   - `GET /api/v1/portal/documents`
   - `GET /api/v1/portal/payslips`
   - `GET /api/v1/portal/announcements`
   - `GET|POST|PUT /api/v1/portal/requests`

## Test Senaryoları

Her route için şu senaryolar test edilir:

1. **Authentication Kontrolü**
   - Authentication olmadan erişim denemesi (401 beklenir)

2. **Yetki Kontrolü**
   - İzin verilen kullanıcı tipleriyle erişim (200/201 beklenir)
   - Yasaklanan kullanıcı tipleriyle erişim (403 beklenir)

3. **Modül Erişim Kontrolü**
   - Modülü olan firma ile erişim (200 beklenir)
   - Modülü olmayan firma ile erişim (403 beklenir)

4. **Firma Durum Kontrolü**
   - Aktif firma ile erişim (200 beklenir)
   - Pasif firma ile erişim (403 beklenir)

## Test Kullanıcıları

Testlerde şu kullanıcılar kullanılır:

- **SuperAdmin**: `test_superadmin@test.com`
- **Company Admin**: `test_admin@company.com`
- **Employee**: `test_employee@company.com`

Tüm test kullanıcılarının şifresi: `password`

## Test Verileri

Testler çalıştırıldığında:
- Test kullanıcıları oluşturulur
- Test firması oluşturulur
- Gerekli modüller aktifleştirilir
- Test sonrası veriler temizlenir (RefreshDatabase trait'i sayesinde)

## Sonuç Raporu

Testler çalıştırıldığında şu bilgiler raporlanır:

- Toplam test sayısı
- Geçen test sayısı
- Başarısız test sayısı
- Her route için detaylı test sonuçları
- Hata mesajları (varsa)

## Notlar

- Testler `RefreshDatabase` trait'ini kullanır, bu yüzden her test çalıştırmasında veritabanı sıfırlanır
- Modül bazlı testler için ilgili modüller otomatik olarak aktifleştirilir
- Testler Laravel Sanctum authentication kullanır
- Middleware kontrolleri test edilir:
  - `auth:sanctum`
  - `company.active`
  - `super_admin`
  - `company_admin`
  - `module.access`

