<?php

namespace Tests\Architecture;

use Illuminate\Support\Facades\DB;
use ReflectionClassConstant;
use Tests\TestCase;

/**
 * Test koşusu canlı veritabanına bağlanamaz; test DB'si idempotent hazırlanır.
 */
class TestDatabaseSafetyTest extends TestCase
{
    private function guardPattern(): string
    {
        return (string) (new ReflectionClassConstant(TestCase::class, 'TESTING_DATABASE_PATTERN'))->getValue();
    }

    public function test_current_connection_is_a_testing_database(): void
    {
        $this->assertMatchesRegularExpression($this->guardPattern(), (string) DB::connection()->getDatabaseName());
    }

    public function test_guard_accepts_only_testing_database_names(): void
    {
        $pattern = $this->guardPattern();

        foreach (['dobedan_hr_testing', 'dobedan_hr_testing_test_1', 'dobedan_hr_testing_test_12'] as $allowed) {
            $this->assertMatchesRegularExpression($pattern, $allowed);
        }

        foreach (['dobedan_hr', 'postgres', 'dobedan_hr_testing_copy', 'testing_dobedan_hr', 'dobedan_hr_test_1'] as $denied) {
            $this->assertDoesNotMatchRegularExpression($pattern, $denied);
        }
    }

    public function test_create_testing_command_refuses_non_testing_names(): void
    {
        $this->artisan('db:create-testing', ['--database' => 'dobedan_hr'])->assertFailed();
        $this->artisan('db:create-testing', ['--database' => 'dobedan_hr; drop'])->assertFailed();
    }

    public function test_create_testing_command_is_idempotent_for_existing_database(): void
    {
        $current = (string) DB::connection()->getDatabaseName();

        $this->artisan('db:create-testing', ['--database' => $current])
            ->expectsOutputToContain('zaten var')
            ->assertSuccessful();
    }
}
