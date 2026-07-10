<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Module;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class TestRoutes extends Command
{
    protected $signature = 'test:routes {--detailed : Detaylı rapor göster}';

    protected $description = 'Tüm route\'ları ve yetkilendirmeleri test et';

    protected $superAdmin;

    protected $companyAdmin;

    protected $employee;

    protected $company;

    protected $results = [];

    public function handle()
    {
        $this->info('Route ve Yetkilendirme Testleri Başlatılıyor...');
        $this->newLine();

        // Test verilerini hazırla
        $this->setupTestData();

        // Tüm route'ları listele
        $this->listAllRoutes();

        // Route'ları test et
        $this->testAllRoutes();

        // Sonuçları raporla
        $this->reportResults();

        return Command::SUCCESS;
    }

    protected function setupTestData()
    {
        $this->info('Test verileri hazırlanıyor...');

        // SuperAdmin oluştur veya bul
        $this->superAdmin = User::firstOrCreate(
            ['email' => 'test_superadmin@test.com'],
            [
                'name' => 'Test Super Admin',
                'password' => bcrypt('password'),
                'type' => 'super_admin',
                'company_id' => null,
                'is_active' => true,
            ]
        );

        // Firma oluştur veya bul
        $this->company = Company::firstOrCreate(
            ['slug' => 'test-company-route'],
            [
                'name' => 'Test Company Route',
                'slug' => 'test-company-route',
                'status' => 'active',
                'email' => 'testroute@company.com',
                'phone' => '5551234567',
            ]
        );

        // Company Admin oluştur veya bul
        $this->companyAdmin = User::firstOrCreate(
            ['email' => 'test_admin@company.com'],
            [
                'name' => 'Test Company Admin',
                'password' => bcrypt('password'),
                'type' => 'company_admin',
                'company_id' => $this->company->id,
                'is_active' => true,
            ]
        );

        // Employee oluştur veya bul
        $this->employee = User::firstOrCreate(
            ['email' => 'test_employee@company.com'],
            [
                'name' => 'Test Employee',
                'password' => bcrypt('password'),
                'type' => 'employee',
                'company_id' => $this->company->id,
                'is_active' => true,
            ]
        );

        // Core modülleri ata
        $coreModules = Module::where('is_core', true)->get();
        foreach ($coreModules as $module) {
            $this->company->modules()->syncWithoutDetaching([
                $module->id => [
                    'is_active' => true,
                    'activated_at' => now(),
                ],
            ]);
        }

        $this->info('✓ Test verileri hazırlandı');
    }

    protected function listAllRoutes()
    {
        $this->info('Tüm API route\'ları listeleniyor...');
        $this->newLine();

        $routes = Route::getRoutes();
        $apiRoutes = collect($routes)->filter(function ($route) {
            return str_starts_with($route->uri(), 'api/v1');
        });

        $routeGroups = [
            'Public Routes' => [],
            'Protected Routes' => [],
            'SuperAdmin Routes' => [],
            'Module Routes' => [],
            'Portal Routes' => [],
        ];

        foreach ($apiRoutes as $route) {
            $uri = $route->uri();
            $methods = $route->methods();
            $middleware = $route->middleware();

            $routeInfo = [
                'methods' => array_diff($methods, ['HEAD', 'OPTIONS']),
                'uri' => $uri,
                'middleware' => $middleware,
            ];

            if (str_contains($uri, '/admin/')) {
                $routeGroups['SuperAdmin Routes'][] = $routeInfo;
            } elseif (str_contains($uri, '/portal/')) {
                $routeGroups['Portal Routes'][] = $routeInfo;
            } elseif (str_contains($uri, '/public/') || str_contains($uri, '/auth/login') || str_contains($uri, '/auth/register')) {
                $routeGroups['Public Routes'][] = $routeInfo;
            } elseif (in_array('module.access', $middleware)) {
                $routeGroups['Module Routes'][] = $routeInfo;
            } else {
                $routeGroups['Protected Routes'][] = $routeInfo;
            }
        }

        foreach ($routeGroups as $groupName => $routes) {
            if (empty($routes)) {
                continue;
            }

            $this->line("<fg=cyan>{$groupName} ({count($routes)})</>");
            foreach ($routes as $route) {
                $methods = implode('|', $route['methods']);
                $this->line("  <fg=yellow>{$methods}</> <fg=white>{$route['uri']}</>");
            }
            $this->newLine();
        }
    }

    protected function testAllRoutes()
    {
        $this->info('Route yetkilendirmeleri için test sınıflarını çalıştırın:');
        $this->line('  php artisan test --filter RouteAuthorizationTest');
        $this->line('  php artisan test --filter RouteComprehensiveTest');
        $this->newLine();

        // Route listesi ve yapı analizi
        $this->analyzeRouteStructure();
    }

    protected function analyzeRouteStructure()
    {
        $this->info('Route yapısı analiz ediliyor...');
        $this->newLine();

        $routes = Route::getRoutes();
        $apiRoutes = collect($routes)->filter(function ($route) {
            return str_starts_with($route->uri(), 'api/v1');
        });

        $middlewareAnalysis = [];

        foreach ($apiRoutes as $route) {
            $middleware = $route->middleware();
            foreach ($middleware as $mw) {
                if (! isset($middlewareAnalysis[$mw])) {
                    $middlewareAnalysis[$mw] = 0;
                }
                $middlewareAnalysis[$mw]++;
            }
        }

        $this->line('Middleware Kullanım İstatistikleri:');
        arsort($middlewareAnalysis);
        foreach ($middlewareAnalysis as $mw => $count) {
            $this->line("  <fg=cyan>{$mw}</>: {$count} route");
        }
        $this->newLine();
    }

    protected function reportResults()
    {
        $this->info('Route analizi tamamlandı!');
        $this->newLine();
        $this->line('Detaylı testler için test sınıflarını çalıştırın:');
        $this->line('  <fg=yellow>php artisan test --filter RouteAuthorizationTest</>');
        $this->line('  <fg=yellow>php artisan test --filter RouteComprehensiveTest</>');
        $this->newLine();
    }
}
