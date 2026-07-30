<?php

namespace App\Services\Kvkk\PersonalData;

/**
 * Anonimleştirme yardımcıları — kimlik maskelenir, istatistik alanları kalır.
 * Eşleme tablosu TUTULMAZ (geri dönüş yok → anonymize, takma ad değil).
 */
final class AnonymizationHelper
{
    public static function anonymLabel(int $id): string
    {
        return 'Anonim-'.substr(hash('sha256', 'alatax-kvkk-'.$id), 0, 10);
    }

    public static function yearOnly(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($date)->format('Y').'-01-01';
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $before
     * @return array{rows_affected: int, fields_masked: list<string>, files_deleted: list<string>, tables: list<string>}
     */
    public static function emptyResult(): array
    {
        return [
            'rows_affected' => 0,
            'fields_masked' => [],
            'files_deleted' => [],
            'tables' => [],
        ];
    }

    /**
     * @param  list<string>  $fields
     * @param  list<string>  $files
     * @param  list<string>  $tables
     * @return array{rows_affected: int, fields_masked: list<string>, files_deleted: list<string>, tables: list<string>}
     */
    public static function result(int $rows, array $fields = [], array $files = [], array $tables = []): array
    {
        return [
            'rows_affected' => $rows,
            'fields_masked' => array_values(array_unique($fields)),
            'files_deleted' => array_values(array_unique($files)),
            'tables' => array_values(array_unique($tables)),
        ];
    }
}
