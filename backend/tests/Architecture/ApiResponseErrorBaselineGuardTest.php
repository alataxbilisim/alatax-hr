<?php

namespace Tests\Architecture;

use Tests\TestCase;

/**
 * `ApiResponse::error(string $message, int<400,599> $code, array|MessageBag|null $errors)` —
 * yanlış argümanlı çağrılar 500 üretir (Adım 03B S3). PHPStan bunları yakalar; baseline'a
 * gizlenmeleri bu testle engellenir.
 */
class ApiResponseErrorBaselineGuardTest extends TestCase
{
    public function test_phpstan_baseline_does_not_hide_error_response_misuse(): void
    {
        $baseline = (string) file_get_contents(base_path('phpstan-baseline.neon'));

        $this->assertStringNotContainsString(
            'error\(\) expects',
            $baseline,
            'ApiResponse::error() yanlış kullanımı baseline\'a eklenemez; çağrıyı düzelt.'
        );
        $this->assertStringNotContainsString('validationError\(\) expects', $baseline);
    }
}
