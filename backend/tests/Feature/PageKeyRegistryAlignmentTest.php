<?php

namespace Tests\Feature;

use App\Services\Settings\SettingsRegistry;
use Tests\TestCase;

/**
 * E0 — D4a page_keys + nav registry bütünlüğü (Docker'da FE mount yok → fixture).
 */
class PageKeyRegistryAlignmentTest extends TestCase
{
    /** @var list<string> */
    private const EXPECTED_SETTINGS_PAGE_KEYS = [
        'settings.registry',
        'leaves.balances.list',
        'leaves.requests.list',
        'analytics.reports.builder',
        'settings.report_privacy',
        'kvkk.requests',
        'kvkk.destruction',
    ];

    /**
     * @return array{page_keys: list<string>, legacy_redirects_from: list<string>}
     */
    private function fixture(): array
    {
        $path = base_path('tests/fixtures/e0_nav_registry.json');
        $this->assertFileExists($path);
        /** @var array{page_keys: list<string>, legacy_redirects_from: list<string>} $data */
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }

    public function test_settings_registry_page_keys_use_stable_e0_keys(): void
    {
        $registry = app(SettingsRegistry::class);
        $found = [];

        foreach ($registry->all() as $def) {
            foreach ($def->pageKeys as $key) {
                $found[$key] = true;
                $this->assertDoesNotMatchRegularExpression(
                    '/^(leaves-|settings-|kvkk-|reports-)/',
                    $key,
                    "Eski kebab page_key kalmış: {$key}"
                );
            }
        }

        foreach (self::EXPECTED_SETTINGS_PAGE_KEYS as $key) {
            $this->assertArrayHasKey($key, $found, "Eksik page_key: {$key}");
        }
    }

    public function test_page_registry_fixture_has_unique_keys(): void
    {
        $keys = $this->fixture()['page_keys'];
        $this->assertGreaterThan(40, count($keys));
        $this->assertSame(count($keys), count(array_unique($keys)), 'Çift page_key var');

        foreach (self::EXPECTED_SETTINGS_PAGE_KEYS as $key) {
            $this->assertContains($key, $keys, "Registry fixture eksik: {$key}");
        }
    }

    public function test_legacy_redirects_fixture_complete(): void
    {
        $fromList = $this->fixture()['legacy_redirects_from'];
        $requiredFrom = [
            '/attendance',
            '/attendance/shifts',
            '/attendance/shift-assignments',
            '/attendance/reports',
            '/attendance/kiosk',
            '/employees/departments',
            '/employees/positions',
            '/employees/organization',
            '/branches',
            '/branches/:id',
            '/payslips',
            '/expenses',
            '/expenses/all',
            '/expenses/categories',
            '/employees/salary-bands',
            '/employees/salary-reviews',
            '/employees/salary-reviews/:id',
            '/announcements',
            '/surveys',
        ];

        foreach ($requiredFrom as $from) {
            $this->assertContains($from, $fromList, "Legacy redirect eksik: {$from}");
        }
    }
}
