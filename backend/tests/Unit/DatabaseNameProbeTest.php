<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kalıcı güvenlik kanıtı — testler yalnızca alatax_hr_testing'e bağlanmalı.
 */
class DatabaseNameProbeTest extends TestCase
{
    public function test_reports_connected_database_name(): void
    {
        $name = DB::connection()->getDatabaseName();
        fwrite(STDERR, "\n[PROBE] DB::connection()->getDatabaseName() = {$name}\n");

        $this->assertSame('alatax_hr_testing', $name);
    }
}
