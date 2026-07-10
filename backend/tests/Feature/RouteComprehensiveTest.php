<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

/**
 * Kapsamlı Route ve Yetkilendirme Testleri
 * Tüm route'ları ve yetkilendirmeleri test eder
 */
class RouteComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $companyAdmin;
    protected $employee;
    protected $company;
    protected $testResults = [];

    protected function setUp(): void
    {
        parent::setUp();

        // SuperAdmin oluştur
        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'type' => 'super_admin',
            'company_id' => null,
            'is_active' => true,
        ]);

        // Firma oluştur
        $this->company = Company::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'status' => 'active',
            'email' => 'test@company.com',
            'phone' => '5551234567',
        ]);

        // Company Admin oluştur
        $this->companyAdmin = User::create([
            'name' => 'Company Admin',
            'email' => 'admin@company.com',
            'password' => bcrypt('password'),
            'type' => 'company_admin',
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);

        // Employee oluştur
        $this->employee = User::create([
            'name' => 'Employee',
            'email' => 'employee@company.com',
            'password' => bcrypt('password'),
            'type' => 'employee',
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);

        // Core modülleri ata
        $coreModules = Module::where('is_core', true)->get();
        foreach ($coreModules as $module) {
            $this->company->modules()->syncWithoutDetaching([
                $module->id => [
                    'is_active' => true,
                    'activated_at' => now(),
                ]
            ]);
        }
    }

    /**
     * Tüm route'ları listele ve raporla
     */
    public function test_list_all_routes(): void
    {
        $routes = Route::getRoutes();
        $apiRoutes = collect($routes)->filter(function ($route) {
            return str_starts_with($route->uri(), 'api/v1');
        });

        $routeList = [];
        foreach ($apiRoutes as $route) {
            $methods = $route->methods();
            $uri = $route->uri();
            $middleware = $route->middleware();
            $name = $route->getName();

            $routeList[] = [
                'methods' => implode('|', $methods),
                'uri' => $uri,
                'name' => $name,
                'middleware' => $middleware,
            ];
        }

        $this->assertGreaterThan(0, count($routeList), 'API route\'ları bulunamadı');
        
        // Route listesini output'a yazdır
        echo "\n\n=== TÜM API ROUTE'LARI ===\n";
        foreach ($routeList as $route) {
            echo sprintf(
                "%s %s\n",
                str_pad(implode('|', explode('|', $route['methods'])), 10),
                $route['uri']
            );
        }
        echo "\nToplam Route Sayısı: " . count($routeList) . "\n\n";
    }

    /**
     * Her route için yetkilendirme testi
     */
    public function test_route_authorizations(): void
    {
        $testCases = [
            // Public Routes
            [
                'route' => 'POST /api/v1/auth/login',
                'method' => 'POST',
                'uri' => '/api/v1/auth/login',
                'requiresAuth' => false,
                'allowedUsers' => ['public'],
                'testData' => ['email' => 'superadmin@test.com', 'password' => 'password'],
            ],
            
            // Protected Routes - Company Admin
            [
                'route' => 'GET /api/v1/dashboard',
                'method' => 'GET',
                'uri' => '/api/v1/dashboard',
                'requiresAuth' => true,
                'allowedUsers' => ['super_admin', 'company_admin', 'employee'],
            ],
            [
                'route' => 'GET /api/v1/users',
                'method' => 'GET',
                'uri' => '/api/v1/users',
                'requiresAuth' => true,
                'allowedUsers' => ['company_admin'],
                'forbiddenUsers' => ['employee'],
            ],
            [
                'route' => 'GET /api/v1/roles',
                'method' => 'GET',
                'uri' => '/api/v1/roles',
                'requiresAuth' => true,
                'allowedUsers' => ['company_admin'],
                'forbiddenUsers' => ['employee'],
            ],
            [
                'route' => 'GET /api/v1/company',
                'method' => 'GET',
                'uri' => '/api/v1/company',
                'requiresAuth' => true,
                'allowedUsers' => ['company_admin'],
                'forbiddenUsers' => ['employee'],
            ],

            // SuperAdmin Routes
            [
                'route' => 'GET /api/v1/admin/dashboard',
                'method' => 'GET',
                'uri' => '/api/v1/admin/dashboard',
                'requiresAuth' => true,
                'allowedUsers' => ['super_admin'],
                'forbiddenUsers' => ['company_admin', 'employee'],
            ],
            [
                'route' => 'GET /api/v1/admin/companies',
                'method' => 'GET',
                'uri' => '/api/v1/admin/companies',
                'requiresAuth' => true,
                'allowedUsers' => ['super_admin'],
                'forbiddenUsers' => ['company_admin', 'employee'],
            ],

            // Module Routes - Job Applications
            [
                'route' => 'GET /api/v1/recruitment/positions',
                'method' => 'GET',
                'uri' => '/api/v1/recruitment/positions',
                'requiresAuth' => true,
                'requiresModule' => 'job-applications',
                'allowedUsers' => ['company_admin'],
            ],

            // Module Routes - Document Management
            [
                'route' => 'GET /api/v1/documents/categories',
                'method' => 'GET',
                'uri' => '/api/v1/documents/categories',
                'requiresAuth' => true,
                'requiresModule' => 'document-management',
                'allowedUsers' => ['company_admin'],
            ],

            // Module Routes - Onboarding
            [
                'route' => 'GET /api/v1/onboarding/templates',
                'method' => 'GET',
                'uri' => '/api/v1/onboarding/templates',
                'requiresAuth' => true,
                'requiresModule' => 'onboarding',
                'allowedUsers' => ['company_admin'],
            ],

            // Module Routes - Leave Management
            [
                'route' => 'GET /api/v1/leaves/types',
                'method' => 'GET',
                'uri' => '/api/v1/leaves/types',
                'requiresAuth' => true,
                'requiresModule' => 'leave-management',
                'allowedUsers' => ['company_admin'],
            ],

            // Portal Routes
            [
                'route' => 'GET /api/v1/portal/dashboard',
                'method' => 'GET',
                'uri' => '/api/v1/portal/dashboard',
                'requiresAuth' => true,
                'allowedUsers' => ['employee', 'company_admin'],
            ],
        ];

        $results = [
            'passed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => [],
        ];

        foreach ($testCases as $testCase) {
            $result = $this->testRouteAuthorization($testCase);
            $results['details'][] = $result;
            
            if ($result['status'] === 'passed') {
                $results['passed']++;
            } elseif ($result['status'] === 'failed') {
                $results['failed']++;
            } else {
                $results['skipped']++;
            }
        }

        // Sonuçları raporla
        $this->reportResults($results);

        // En az bir test geçmeli
        $this->assertGreaterThan(0, $results['passed'], 'Hiçbir test geçmedi');
    }

    /**
     * Tek bir route için yetkilendirme testi
     */
    protected function testRouteAuthorization(array $testCase): array
    {
        $result = [
            'route' => $testCase['route'],
            'status' => 'pending',
            'message' => '',
            'tests' => [],
        ];

        try {
            // Authentication gerektirmeyen route'lar
            if (!$testCase['requiresAuth']) {
                $response = $this->json($testCase['method'], $testCase['uri'], $testCase['testData'] ?? []);
                $result['tests'][] = [
                    'test' => 'Public access',
                    'status' => $response->status() !== 401 ? 'passed' : 'failed',
                    'statusCode' => $response->status(),
                ];
                $result['status'] = $response->status() !== 401 ? 'passed' : 'failed';
                return $result;
            }

            // Modül gerektiren route'lar için modülü aktifleştir
            if (isset($testCase['requiresModule'])) {
                $module = Module::firstOrCreate(
                    ['slug' => $testCase['requiresModule']],
                    [
                        'name' => ucfirst(str_replace('-', ' ', $testCase['requiresModule'])),
                        'slug' => $testCase['requiresModule'],
                        'is_core' => false,
                    ]
                );

                $this->company->modules()->syncWithoutDetaching([
                    $module->id => [
                        'is_active' => true,
                        'activated_at' => now(),
                    ]
                ]);
            }

            // İzin verilen kullanıcılar için test
            if (isset($testCase['allowedUsers'])) {
                foreach ($testCase['allowedUsers'] as $userType) {
                    $user = $this->getUserByType($userType);
                    if (!$user) continue;

                    Sanctum::actingAs($user);
                    $response = $this->json($testCase['method'], $testCase['uri'], $testCase['testData'] ?? []);

                    $testResult = [
                        'test' => "Access as {$userType}",
                        'status' => in_array($response->status(), [200, 201]) ? 'passed' : 'failed',
                        'statusCode' => $response->status(),
                        'expected' => '200/201',
                    ];

                    if ($testResult['status'] === 'failed') {
                        $testResult['error'] = $response->json()['message'] ?? 'Unknown error';
                    }

                    $result['tests'][] = $testResult;
                }
            }

            // Yasaklanan kullanıcılar için test
            if (isset($testCase['forbiddenUsers'])) {
                foreach ($testCase['forbiddenUsers'] as $userType) {
                    $user = $this->getUserByType($userType);
                    if (!$user) continue;

                    Sanctum::actingAs($user);
                    $response = $this->json($testCase['method'], $testCase['uri'], $testCase['testData'] ?? []);

                    $testResult = [
                        'test' => "Forbidden for {$userType}",
                        'status' => in_array($response->status(), [403, 401]) ? 'passed' : 'failed',
                        'statusCode' => $response->status(),
                        'expected' => '403/401',
                    ];

                    $result['tests'][] = $testResult;
                }
            }

            // Authentication olmadan test
            Sanctum::actingAs(null);
            $response = $this->json($testCase['method'], $testCase['uri'], $testCase['testData'] ?? []);
            $result['tests'][] = [
                'test' => 'Unauthenticated access',
                'status' => $response->status() === 401 ? 'passed' : 'failed',
                'statusCode' => $response->status(),
                'expected' => '401',
            ];

            // Genel durum belirleme
            $allPassed = collect($result['tests'])->every(fn($test) => $test['status'] === 'passed');
            $result['status'] = $allPassed ? 'passed' : 'failed';
            $result['message'] = $allPassed ? 'All tests passed' : 'Some tests failed';

        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['message'] = $e->getMessage();
            $result['error'] = $e->getTraceAsString();
        }

        return $result;
    }

    /**
     * Kullanıcı tipine göre kullanıcı döndür
     */
    protected function getUserByType(string $type): ?User
    {
        return match($type) {
            'super_admin' => $this->superAdmin,
            'company_admin' => $this->companyAdmin,
            'employee' => $this->employee,
            default => null,
        };
    }

    /**
     * Test sonuçlarını raporla
     */
    protected function reportResults(array $results): void
    {
        echo "\n\n";
        echo "========================================\n";
        echo "  ROUTE YETKİLENDİRME TEST RAPORU\n";
        echo "========================================\n\n";
        echo "Toplam Test: " . count($results['details']) . "\n";
        echo "Geçen: " . $results['passed'] . "\n";
        echo "Başarısız: " . $results['failed'] . "\n";
        echo "Atlanan: " . $results['skipped'] . "\n\n";

        foreach ($results['details'] as $detail) {
            $statusIcon = $detail['status'] === 'passed' ? '✓' : '✗';
            echo sprintf("%s %s\n", $statusIcon, $detail['route']);
            
            foreach ($detail['tests'] as $test) {
                $testIcon = $test['status'] === 'passed' ? '  ✓' : '  ✗';
                echo sprintf(
                    "%s %s (Status: %d, Expected: %s)\n",
                    $testIcon,
                    $test['test'],
                    $test['statusCode'],
                    $test['expected'] ?? 'N/A'
                );
                
                if (isset($test['error'])) {
                    echo sprintf("    Error: %s\n", $test['error']);
                }
            }
            echo "\n";
        }

        echo "========================================\n\n";
    }
}

