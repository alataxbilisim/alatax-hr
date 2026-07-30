<?php

namespace Tests\Concerns;

use App\Support\PostgresAdvisoryLock;
use Illuminate\Foundation\Testing\RefreshDatabase as LaravelRefreshDatabase;

/**
 * Laravel RefreshDatabase + testing DB advisory lock.
 *
 * Kilit ayrı PDO oturumunda tutulur — migrate sırasında varsayılan bağlantıda
 * aborted transaction olsa bile unlock/sızıntı olmaz (W1-fix).
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
        PostgresAdvisoryLock::withSessionLockOnSideConnection(
            PostgresAdvisoryLock::KEY_TESTING_MIGRATE,
            fn () => $this->laravelMigrateDatabases()
        );
    }
}
