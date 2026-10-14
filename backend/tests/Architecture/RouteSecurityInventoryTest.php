<?php

namespace Tests\Architecture;

use Illuminate\Routing\Route;
use Tests\TestCase;

/**
 * api/v1 route envanteri:
 *  - her route auth:sanctum arkasında ya da PUBLIC_ROUTES listesinde; public route'lar throttle'lı,
 *  - her auth:sanctum route bir yetki kapısından geçer (permission/can/role middleware'i,
 *    super_admin, portal.access) ya da SELF_SERVICE_ROUTES listesinde gerekçesiyle durur.
 * Yeni route bu listelere girecekse gerekçe yazılır; liste sessizce büyütülmez.
 */
class RouteSecurityInventoryTest extends TestCase
{
    /** Route anahtarı: "METHOD uri" => gerekçe. */
    private const PUBLIC_ROUTES = [
        'POST api/v1/auth/login' => 'Oturum açma.',
        'POST api/v1/auth/register' => 'Kayıt.',
        'POST api/v1/auth/forgot-password' => 'Şifre sıfırlama talebi.',
        'POST api/v1/auth/reset-password' => 'Şifre sıfırlama (token ile).',
        'GET api/v1/auth/invitation/{token}' => 'Davet önizleme (token ile).',
        'POST api/v1/auth/accept-invitation' => 'Davet kabulü (token ile).',
        'GET api/v1/public/companies/{companySlug}/jobs' => 'Kariyer sayfası ilan listesi.',
        'GET api/v1/public/jobs/{positionSlug}' => 'Kariyer sayfası ilan detayı.',
        'GET api/v1/public/companies/{companySlug}/jobs/{positionSlug}/form' => 'Başvuru formu tanımı.',
        'GET api/v1/public/companies/{companySlug}/privacy-notice' => 'KVKK aydınlatma metni.',
        'POST api/v1/public/companies/{companySlug}/data-subject-requests' => 'KVKK ilgili kişi başvurusu.',
        'POST api/v1/public/companies/{companySlug}/data-subject-requests/{id}/verify-email' => 'KVKK başvuru e-posta doğrulaması.',
        'POST api/v1/public/jobs/{positionSlug}/apply' => 'İş başvurusu.',
        'POST api/v1/public/companies/{companySlug}/jobs/{positionSlug}/apply' => 'İş başvurusu (firma slug ile).',
    ];

    /** Yetki kapısı sayılan middleware'ler (tam ad veya önek). */
    private const GATE_MIDDLEWARE_PREFIXES = ['permission:', 'can:', 'role:', 'role_or_permission:'];

    private const GATE_MIDDLEWARE = [
        'super_admin' => 'Yalnız süper admin.',
        'portal.access' => 'Portal: personel yalnız kendi verisi (controller kendi kaydına filtreler).',
    ];

    /** Yetki middleware'i olmayan, oturum sahibinin kendi verisine/ortak sözlüğe erişen uçlar. */
    private const SELF_SERVICE_ROUTES = [
        'POST api/v1/auth/2fa/verify' => '2FA challenge doğrulaması (ability:2fa-challenge).',
        'GET api/v1/context/companies' => 'Kullanıcının erişebildiği şirketler.',
        'GET api/v1/context/branches' => 'Kullanıcının erişebildiği şubeler.',
        'POST api/v1/auth/logout' => 'Kendi oturumu.',
        'GET api/v1/auth/me' => 'Kendi profili.',
        'PUT api/v1/auth/profile' => 'Kendi profili.',
        'PUT api/v1/auth/password' => 'Kendi şifresi.',
        'GET api/v1/auth/2fa/status' => 'Kendi 2FA durumu.',
        'POST api/v1/auth/2fa/enable' => 'Kendi 2FA kurulumu.',
        'POST api/v1/auth/2fa/confirm' => 'Kendi 2FA kurulumu.',
        'POST api/v1/auth/2fa/disable' => 'Kendi 2FA kapatma.',
        'GET api/v1/auth/2fa/recovery-codes' => 'Kendi kurtarma kodları.',
        'POST api/v1/auth/2fa/recovery-codes/regenerate' => 'Kendi kurtarma kodları.',
        'GET api/v1/dashboard' => 'Panel özeti: şirket düzeyi toplam sayılar (kişisel veri yok); grup kapsamı reports.scope.group ister.',
        'GET api/v1/lookups/{type}' => 'Ortak sözlük (liste değerleri), şirket bağlamında.',
        'GET api/v1/lookups-resolve' => 'Ortak sözlük çözümleme, şirket bağlamında.',
        'GET api/v1/notifications' => 'Kendi bildirimleri.',
        'POST api/v1/notifications/{id}/read' => 'Kendi bildirimi.',
        'POST api/v1/notifications/read-all' => 'Kendi bildirimleri.',
        'GET api/v1/approvals/pending' => 'Kendisine atanmış onaylar.',
        'GET api/v1/approvals/history' => 'Kendi onay geçmişi.',
        'POST api/v1/approvals/{id}/approve' => 'Policy: $this->authorize(approve, ApprovalRecord).',
        'POST api/v1/approvals/{id}/reject' => 'Policy: $this->authorize(reject, ApprovalRecord).',
        'POST api/v1/approvals/{id}/skip' => 'Policy: $this->authorize(skip, ApprovalRecord).',
        'GET api/v1/approvals/record-history' => 'Controller: company veri kapsamı ya da kayıtta onaycı olma kontrolü.',
        'GET api/v1/approvals/delegations' => 'Controller: yalnız company veri kapsamı (İK).',
        'GET api/v1/approvals/delegations/my' => 'Kendi vekâletleri.',
        'POST api/v1/approvals/delegations' => 'Kendi vekâletini oluşturma.',
        'POST api/v1/approvals/delegations/{id}/cancel' => 'Kendi vekâletini iptal.',
    ];

    /** @return array<string, array{route: Route, middleware: list<string>}> */
    private function apiRoutes(): array
    {
        $routes = [];
        foreach (app('router')->getRoutes() as $route) {
            /** @var Route $route */
            if (! str_starts_with($route->uri(), 'api/v1')) {
                continue;
            }
            $middleware = array_values(array_filter($route->gatherMiddleware(), 'is_string'));
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $routes[$method.' '.$route->uri()] = ['route' => $route, 'middleware' => $middleware];
            }
        }

        return $routes;
    }

    public function test_every_api_route_requires_sanctum_or_is_listed_public(): void
    {
        $unexpected = [];
        foreach ($this->apiRoutes() as $key => $info) {
            if (! in_array('auth:sanctum', $info['middleware'], true) && ! array_key_exists($key, self::PUBLIC_ROUTES)) {
                $unexpected[] = $key;
            }
        }

        $this->assertSame([], $unexpected, "auth:sanctum'suz ve public listede olmayan route'lar:\n".implode("\n", $unexpected));
    }

    public function test_public_routes_are_throttled(): void
    {
        $routes = $this->apiRoutes();
        $unthrottled = [];
        foreach (array_keys(self::PUBLIC_ROUTES) as $key) {
            if (! isset($routes[$key])) {
                continue;
            }
            $throttled = array_filter($routes[$key]['middleware'], fn (string $m) => str_starts_with($m, 'throttle'));
            if ($throttled === []) {
                $unthrottled[] = $key;
            }
        }

        $this->assertSame([], $unthrottled, "throttle'sız public route'lar:\n".implode("\n", $unthrottled));
    }

    public function test_every_sanctum_route_passes_an_authorization_gate(): void
    {
        $ungated = [];
        foreach ($this->apiRoutes() as $key => $info) {
            $middleware = $info['middleware'];
            if (! in_array('auth:sanctum', $middleware, true) || array_key_exists($key, self::SELF_SERVICE_ROUTES)) {
                continue;
            }
            if (! $this->hasGate($middleware)) {
                $ungated[] = $key.' -> '.$info['route']->getActionName();
            }
        }

        $this->assertSame(
            [],
            $ungated,
            "Yetki kapısı (permission/can/role/super_admin/portal.access) olmayan route'lar; ".
            "middleware ekleyin ya da gerekçesiyle SELF_SERVICE_ROUTES'a yazın:\n".implode("\n", $ungated)
        );
    }

    public function test_allow_lists_have_no_stale_or_redundant_entries(): void
    {
        $routes = $this->apiRoutes();

        foreach (array_keys(self::PUBLIC_ROUTES) as $key) {
            $this->assertArrayHasKey($key, $routes, "Public listedeki route artık yok, listeden çıkarın: {$key}");
            $this->assertNotContains('auth:sanctum', $routes[$key]['middleware'], "Public listedeki route artık auth:sanctum'lu: {$key}");
        }

        foreach (array_keys(self::SELF_SERVICE_ROUTES) as $key) {
            $this->assertArrayHasKey($key, $routes, "Self-service listedeki route artık yok, listeden çıkarın: {$key}");
            $this->assertFalse(
                $this->hasGate($routes[$key]['middleware']),
                "Self-service listedeki route artık yetki kapılı, listeden çıkarın: {$key}"
            );
        }
    }

    /** @param  list<string>  $middleware */
    private function hasGate(array $middleware): bool
    {
        foreach ($middleware as $m) {
            if (array_key_exists($m, self::GATE_MIDDLEWARE)) {
                return true;
            }
            foreach (self::GATE_MIDDLEWARE_PREFIXES as $prefix) {
                if (str_starts_with($m, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
