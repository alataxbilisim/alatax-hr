<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase as LaravelRefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Laravel RefreshDatabase + testing DB advisory lock.
 * Eşzamanlı suite'lerin migrate:fresh / DROP TABLE deadlock'unu engeller.
 */
trait RefreshDatabase
{
    use LaravelRefreshDatabase {
        migrateDatabases as private laravelMigrateDatabases;
    }

    /**
     * @return void
     */
    protected function migrateDatabases()
    {
        $lockKey = 74290114;
        DB::select('SELECT pg_advisory_lock(?)', [$lockKey]);

        try {
            $this->laravelMigrateDatabases();
        } finally {
            DB::select('SELECT pg_advisory_unlock(?)', [$lockKey]);
        }
    }
}
