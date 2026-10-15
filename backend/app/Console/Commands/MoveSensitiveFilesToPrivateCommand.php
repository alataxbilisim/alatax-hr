<?php

namespace App\Console\Commands;

use App\Services\Files\SensitiveFileStorage;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Adım 03B S1 — public diske yazılmış hassas dosyaları private diske taşır (idempotent).
 *
 * Göreli yol iki diskte de aynı kaldığı için veritabanı güncellenmez. Kaynak:
 * (1) tablolarda başvurulan yollar, (2) public diskteki hassas klasörler (kaydı silinmiş
 * ama dosyası kalmış yetimler dahil).
 */
class MoveSensitiveFilesToPrivateCommand extends Command
{
    protected $signature = 'storage:move-sensitive-to-private {--dry-run : Yalnız raporla, dosyaya dokunma}';

    protected $description = 'Public diskteki hassas dosyaları private diske taşı';

    /** @var list<array{0: string, 1: string, 2: 'path'|'json_paths'}> tablo, kolon, tür */
    public const REFERENCES = [
        ['documents', 'file_path', 'path'],
        ['document_versions', 'file_path', 'path'],
        ['employee_documents', 'file_path', 'path'],
        ['payslips', 'file_path', 'path'],
        ['leave_requests', 'document_path', 'path'],
        ['job_applications', 'cv_path', 'path'],
        ['expense_items', 'receipt_path', 'path'],
        ['employee_requests', 'attachments', 'json_paths'],
    ];

    /** Public diskte yalnız hassas yüklemelerin yazıldığı klasörler. */
    public const SENSITIVE_DIRECTORIES = [
        'documents',
        'leave_documents',
        'leave-documents',
        'request_attachments',
        'expenses',
        'applications',
        'payslips',
        'employees',
    ];

    /** @var array<string, int> */
    private array $stats = ['moved' => 0, 'already_private' => 0, 'missing' => 0, 'conflict' => 0];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $public = Storage::disk(SensitiveFileStorage::LEGACY_DISK);
        $private = Storage::disk(SensitiveFileStorage::DISK);

        $this->info($dryRun ? 'DRY-RUN — dosyalara dokunulmayacak.' : 'Taşıma başlıyor.');

        $rows = [];
        $seen = [];
        foreach (self::REFERENCES as [$table, $column, $kind]) {
            $paths = $this->referencedPaths($table, $column, $kind);
            $counts = ['refs' => count($paths), 'to_move' => 0, 'already_private' => 0, 'missing' => 0, 'conflict' => 0];
            foreach ($paths as $path) {
                $seen[$path] = true;
                $counts[$this->process($path, $public, $private, $dryRun)]++;
            }
            $rows[] = ["{$table}.{$column}", $counts['refs'], $counts['to_move'], $counts['already_private'], $counts['missing'], $counts['conflict']];
        }

        $orphans = ['refs' => 0, 'to_move' => 0, 'already_private' => 0, 'missing' => 0, 'conflict' => 0];
        foreach (self::SENSITIVE_DIRECTORIES as $directory) {
            foreach ($public->allFiles($directory) as $path) {
                if (isset($seen[$path])) {
                    continue;
                }
                $orphans['refs']++;
                $orphans[$this->process($path, $public, $private, $dryRun)]++;
            }
        }
        $rows[] = ['(kayıtsız dosya: hassas klasörler)', $orphans['refs'], $orphans['to_move'], $orphans['already_private'], $orphans['missing'], $orphans['conflict']];

        $this->table(['Kaynak', 'Yol', $dryRun ? 'Taşınacak' : 'Taşındı', 'Zaten private', 'Diskte yok', 'Çakışma'], $rows);

        if ($this->stats['conflict'] > 0) {
            $this->warn("{$this->stats['conflict']} dosya iki diskte farklı içerikle var; dokunulmadı, elle incelenmeli.");
        }

        return $this->stats['conflict'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return 'to_move'|'already_private'|'missing'|'conflict'
     */
    private function process(string $path, Filesystem $public, Filesystem $private, bool $dryRun): string
    {
        $onPublic = $public->exists($path);
        $onPrivate = $private->exists($path);

        if (! $onPublic) {
            $result = $onPrivate ? 'already_private' : 'missing';
            $this->stats[$result]++;

            return $result;
        }

        if ($onPrivate && $public->size($path) !== $private->size($path)) {
            $this->stats['conflict']++;

            return 'conflict';
        }

        if (! $dryRun) {
            if (! $onPrivate) {
                $stream = $public->readStream($path);
                $private->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if ($private->size($path) !== $public->size($path)) {
                $this->stats['conflict']++;

                return 'conflict';
            }

            $public->delete($path);
        }

        $this->stats['moved']++;

        return 'to_move';
    }

    /**
     * @return list<string>
     */
    private function referencedPaths(string $table, string $column, string $kind): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        $values = DB::table($table)->whereNotNull($column)->orderBy('id')->pluck($column);

        $paths = [];
        foreach ($values as $value) {
            if ($kind === 'json_paths') {
                foreach ((array) json_decode((string) $value, true) as $item) {
                    if (is_array($item) && ! empty($item['path'])) {
                        $paths[] = (string) $item['path'];
                    }
                }
            } elseif ((string) $value !== '') {
                $paths[] = (string) $value;
            }
        }

        return array_values(array_unique($paths));
    }
}
