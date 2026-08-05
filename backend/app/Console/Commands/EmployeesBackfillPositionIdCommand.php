<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Position;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent: boş position_id'yi katalogla doldur; belirsiz/eşleşmeyenleri raporla.
 */
class EmployeesBackfillPositionIdCommand extends Command
{
    protected $signature = 'employees:backfill-position-id {--dry-run : Sadece rapor}';

    protected $description = 'employees.position_id backfill (tek eşleşme); belirsizleri listeler';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('production ortamında çalıştırılamaz.');

            return self::FAILURE;
        }

        $ambiguous = [];
        $unmatched = [];
        $updated = 0;

        Employee::withoutGlobalScopes()
            ->whereNull('position_id')
            ->whereNotNull('position')
            ->where('position', '!=', '')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$ambiguous, &$unmatched, &$updated): void {
                foreach ($rows as $employee) {
                    $raw = trim((string) $employee->position);
                    $byCode = Position::withoutGlobalScopes()
                        ->where('company_id', $employee->company_id)
                        ->whereNull('deleted_at')
                        ->where('code', $raw)
                        ->get(['id', 'code', 'name']);
                    if ($byCode->count() === 1) {
                        if (! $this->option('dry-run')) {
                            DB::table('employees')->where('id', $employee->id)->update([
                                'position_id' => $byCode->first()->id,
                            ]);
                        }
                        $updated++;

                        continue;
                    }

                    $byName = Position::withoutGlobalScopes()
                        ->where('company_id', $employee->company_id)
                        ->whereNull('deleted_at')
                        ->where('name', $raw)
                        ->get(['id', 'code', 'name']);
                    if ($byName->count() === 1) {
                        if (! $this->option('dry-run')) {
                            DB::table('employees')->where('id', $employee->id)->update([
                                'position_id' => $byName->first()->id,
                            ]);
                        }
                        $updated++;

                        continue;
                    }
                    if ($byName->count() > 1) {
                        $ambiguous[] = [
                            'employee_id' => $employee->id,
                            'company_id' => $employee->company_id,
                            'position' => $raw,
                            'matches' => $byName->map(fn ($p) => [
                                'id' => $p->id,
                                'code' => $p->code,
                                'name' => $p->name,
                            ])->all(),
                        ];

                        continue;
                    }
                    $unmatched[] = [
                        'employee_id' => $employee->id,
                        'company_id' => $employee->company_id,
                        'position' => $raw,
                    ];
                }
            });

        $report = [
            'updated_or_would_update' => $updated,
            'ambiguous_count' => count($ambiguous),
            'unmatched_count' => count($unmatched),
            'ambiguous' => $ambiguous,
            'unmatched' => $unmatched,
            'dry_run' => (bool) $this->option('dry-run'),
        ];
        $path = storage_path('logs/position_id_backfill_report.json');
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("updated={$updated} ambiguous=".count($ambiguous).' unmatched='.count($unmatched));
        $this->info("report={$path}");

        return self::SUCCESS;
    }
}
