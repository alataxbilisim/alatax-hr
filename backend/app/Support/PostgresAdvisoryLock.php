<?php

namespace App\Support;

use App\Exceptions\AdvisoryLockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * PostgreSQL advisory lock yardımcıları.
 *
 * - transactionScoped: pg_advisory_xact_lock — TX bitince (commit/rollback) otomatik bırakılır.
 * - withSessionLockOnSideConnection: migrate/seed gibi uzun işler; kilit ayrı PDO'da,
 *   varsayılan bağlantıdaki aborted TX'den etkilenmez.
 */
final class PostgresAdvisoryLock
{
    /** Test migrate serileştirme (RefreshDatabase). */
    public const KEY_TESTING_MIGRATE = 74290114;

    /** Test PermissionSeeder serileştirme. */
    public const KEY_TESTING_PERMISSION_SEED = 74290115;

    /**
     * company_id + scope + entity_id → iki int4 anahtar (tenant izolasyonu).
     * k1 = company_id (doğrudan; farklı firmalar asla aynı k1'i paylaşmaz),
     * k2 = crc32(scope:entityId) signed int32.
     *
     * @return array{0: int, 1: int}
     */
    public static function keys(int $companyId, string $scope, int $entityId): array
    {
        if ($companyId < 1) {
            throw new \InvalidArgumentException('advisory.lock.company_id_required');
        }

        return [$companyId, self::signedCrc32($scope.':'.$entityId)];
    }

    /**
     * Mevcut transaction içinde xact lock. Exception/rollback → otomatik bırakılır.
     *
     * @throws AdvisoryLockTimeoutException
     */
    public static function transactionScoped(
        int $companyId,
        string $scope,
        int $entityId,
        string $timeout = '3s',
    ): void {
        if (DB::transactionLevel() < 1) {
            throw new \RuntimeException(
                'PostgresAdvisoryLock::transactionScoped yalnızca açık transaction içinde çağrılmalıdır.'
            );
        }

        if (! preg_match('/^\d+(ms|s)$/', $timeout)) {
            throw new \InvalidArgumentException('advisory.lock.invalid_timeout');
        }

        [$k1, $k2] = self::keys($companyId, $scope, $entityId);

        // SET LOCAL bu TX boyunca sürer — kilidi aldıktan sonra DEFAULT'a çek,
        // aksi halde aynı TX'deki sonraki sorgular (test dış TX dahil) 3s ile kesilir.
        DB::statement("SET LOCAL lock_timeout = '{$timeout}'");

        try {
            DB::select('SELECT pg_advisory_xact_lock(?, ?)', [$k1, $k2]);
        } catch (QueryException $e) {
            // Timeout/aborted TX: SET LOCAL çalışmaz (25P02). Laravel outer TX rollback
            // LOCAL ayarları zaten temizler — burada ek SQL yok.
            if (self::isLockTimeout($e)) {
                throw new AdvisoryLockTimeoutException(
                    "Kayıt kilitli (company={$companyId} scope={$scope} id={$entityId})."
                );
            }

            throw $e;
        }

        DB::statement('SET LOCAL lock_timeout TO DEFAULT');
    }

    /**
     * Oturum kilidi ayrı PDO bağlantısında — varsayılan TX aborted olsa bile finally güvenli.
     * Bağlantı kapanınca kilit otomatik düşer (garanti).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withSessionLockOnSideConnection(int $lockKey, callable $callback): mixed
    {
        $pdo = self::openSidePdo();

        try {
            $stmt = $pdo->prepare('SELECT pg_advisory_lock(?)');
            $stmt->execute([$lockKey]);
            $stmt->fetchAll();
            $stmt->closeCursor();

            return $callback();
        } finally {
            try {
                $unlock = $pdo->prepare('SELECT pg_advisory_unlock(?)');
                $unlock->execute([$lockKey]);
                $unlock->fetchAll();
                $unlock->closeCursor();
            } catch (Throwable) {
                // Bağlantıyı kapatmak kilidi her durumda bırakır
            }

            $pdo = null;
        }
    }

    /**
     * Bu backend pid'inin tuttuğu granted advisory kilitler (sızıntı assert için).
     *
     * @return list<object>
     */
    public static function heldByCurrentBackend(): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return [];
        }

        return DB::select(
            'SELECT locktype, classid, objid, mode, granted
             FROM pg_locks
             WHERE locktype = \'advisory\'
               AND pid = pg_backend_pid()
               AND granted = true'
        );
    }

    public static function isLockTimeout(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        if ($sqlState === '55P03') {
            return true;
        }

        $msg = $e->getMessage();

        return str_contains($msg, 'lock timeout')
            || str_contains($msg, 'canceling statement due to lock timeout');
    }

    private static function signedCrc32(string $material): int
    {
        $u = crc32($material);
        // PHP crc32 → unsigned; PG int4 signed
        if ($u >= 0x80000000) {
            return (int) ($u - 0x100000000);
        }

        return (int) $u;
    }

    private static function openSidePdo(): PDO
    {
        $cfg = config('database.connections.'.config('database.default'));
        $host = $cfg['host'] ?? '127.0.0.1';
        $port = $cfg['port'] ?? '5432';
        $database = $cfg['database'] ?? '';
        $username = $cfg['username'] ?? '';
        $password = $cfg['password'] ?? '';

        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        return $pdo;
    }
}
