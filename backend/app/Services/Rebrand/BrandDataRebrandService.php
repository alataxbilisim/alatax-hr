<?php

namespace App\Services\Rebrand;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Veritabanındaki eski ürün adını Dobedan adlandırmasına çevirir (tek seferlik, idempotent).
 *
 * Denetim izi (activity_logs), Telescope ve framework sistem tabloları taranmaz.
 * Şifreli/hash'li ve PHP-serialize değerler değiştirilmez; benzersiz kolonda çakışan satır atlanır.
 */
class BrandDataRebrandService
{
    // brand-guard:allow-start
    private const NEEDLE = 'alatax';

    /** Değişmeyecek ifadeler (GitHub deposu). */
    private const PROTECTED = ['github.com/alataxbilisim/alatax-hr'];

    /** Sıra önemlidir: alan adı ve ürün adı genel kuraldan önce. */
    private const RULES = [
        '/alataxbilisim\.com/i' => 'dobedan.com',
        '/alatax(\s+)hr\b/i' => 'Dobedan$1HR',
        '/ALATAX/' => 'DOBEDAN',
        '/Alatax/' => 'Dobedan',
        '/alatax/i' => 'dobedan',
    ];
    // brand-guard:allow-end

    private const SKIP_TABLES = [
        'activity_logs',
        'migrations',
        'cache',
        'cache_locks',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    private const SKIP_TABLE_PREFIXES = ['telescope_'];

    private const TEXT_TYPES = ['text', 'varchar', 'bpchar', 'jsonb', 'json'];

    /**
     * @return array{changes: list<array{table: string, column: string, rows: int}>, skipped: list<array{table: string, column: string, key: string, reason: string}>}
     */
    public function run(bool $dryRun): array
    {
        return DB::transaction(function () use ($dryRun) {
            $changes = [];
            $skipped = [];

            foreach ($this->tables() as $table) {
                $columns = $this->textColumns($table);
                if ($columns === []) {
                    continue;
                }

                $primary = $this->primaryKey($table);
                $uniqueColumns = $this->singleColumnUniques($table);

                foreach ($columns as $column) {
                    $query = DB::table($table)->whereLike($column, '%'.self::NEEDLE.'%');

                    if ($primary === []) {
                        if ($query->exists()) {
                            $skipped[] = ['table' => $table, 'column' => $column, 'key' => '-', 'reason' => 'no_primary_key'];
                        }

                        continue;
                    }

                    $changed = 0;
                    foreach ($query->get([...$primary, $column]) as $row) {
                        $key = array_map(fn ($c) => $row->{$c}, array_combine($primary, $primary));
                        $keyLabel = implode(',', $key);
                        $old = (string) $row->{$column};

                        if ($this->isOpaque($old)) {
                            $skipped[] = ['table' => $table, 'column' => $column, 'key' => $keyLabel, 'reason' => 'encrypted_or_serialized'];

                            continue;
                        }

                        $new = $this->transform($old);
                        if ($new === $old) {
                            continue;
                        }

                        if (in_array($column, $uniqueColumns, true) && $this->conflicts($table, $column, $new, $key)) {
                            $skipped[] = ['table' => $table, 'column' => $column, 'key' => $keyLabel, 'reason' => 'unique_conflict'];

                            continue;
                        }

                        if (! $dryRun) {
                            DB::table($table)->where($key)->update([$column => $new]);
                        }
                        $changed++;
                    }

                    if ($changed > 0) {
                        $changes[] = ['table' => $table, 'column' => $column, 'rows' => $changed];
                    }
                }
            }

            return ['changes' => $changes, 'skipped' => $skipped];
        });
    }

    public function transform(string $value): string
    {
        $saved = [];
        foreach (self::PROTECTED as $i => $phrase) {
            $value = str_ireplace($phrase, "\0P{$i}\0", $value, $count);
            if ($count > 0) {
                $saved[$i] = $phrase;
            }
        }

        foreach (self::RULES as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value);
        }

        foreach ($saved as $i => $phrase) {
            $value = str_replace("\0P{$i}\0", $phrase, $value);
        }

        return $value;
    }

    /** @return list<string> */
    private function tables(): array
    {
        return collect(Schema::getTables())
            ->filter(fn ($t) => ($t['schema'] ?? 'public') === 'public')
            ->pluck('name')
            ->reject(fn ($name) => in_array($name, self::SKIP_TABLES, true)
                || collect(self::SKIP_TABLE_PREFIXES)->contains(fn ($p) => str_starts_with($name, $p)))
            ->sort()
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function textColumns(string $table): array
    {
        return collect(Schema::getColumns($table))
            ->filter(fn ($c) => in_array($c['type_name'], self::TEXT_TYPES, true))
            ->pluck('name')
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function primaryKey(string $table): array
    {
        $primary = collect(Schema::getIndexes($table))->firstWhere('primary', true);

        return $primary['columns'] ?? [];
    }

    /** @return list<string> */
    private function singleColumnUniques(string $table): array
    {
        return collect(Schema::getIndexes($table))
            ->filter(fn ($i) => $i['unique'] && count($i['columns']) === 1)
            ->map(fn ($i) => $i['columns'][0])
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $key */
    private function conflicts(string $table, string $column, string $value, array $key): bool
    {
        return DB::table($table)
            ->where($column, $value)
            ->whereNot(fn ($q) => $q->where($key))
            ->exists();
    }

    private function isOpaque(string $value): bool
    {
        return str_starts_with($value, 'eyJpdiI6')
            || str_starts_with($value, '$2y$')
            || preg_match('/(^|[;{])[aOs]:\d+:/', $value) === 1;
    }
}
