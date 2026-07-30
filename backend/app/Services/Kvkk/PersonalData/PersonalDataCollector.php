<?php

namespace App\Services\Kvkk\PersonalData;

/**
 * D2b/D2c — Modül kişisel veri toplayıcısı + imha.
 *
 * İlke (D2c): Sistem kendi başına veri imha etmez. destroy() yalnız onaylı
 * DestructionApproval kaydı olan işlerden çağrılır. Varsayılan strateji anonymize.
 */
interface PersonalDataCollector
{
    public function key(): string;

    public function labelKey(): string;

    /**
     * @return list<array{
     *   category: string,
     *   label: string,
     *   records: list<array<string, mixed>>,
     *   files: list<array{path: string, name: string, category?: string}>
     * }>
     */
    public function collect(string $subjectType, int $subjectId, int $companyId): array;

    /**
     * İmha/anonimleştirme.
     *
     * @param  'delete'|'anonymize'  $strategy
     * @return array{rows_affected: int, fields_masked: list<string>, files_deleted: list<string>, tables: list<string>}
     */
    public function destroy(string $subjectType, int $subjectId, int $companyId, string $strategy, bool $dryRun = false): array;
}
