<?php

namespace App\Services\Kvkk\PersonalData;

/**
 * D2b — Modül kişisel veri toplayıcısı (D1a/D4a registry deseni).
 * destroy() D2c'de çağrılacak; bu dalgada tanımlanır, çağrılmaz.
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
     * D2c — silme/anonimleştirme. D2b'de çağrılmaz.
     *
     * @param  'delete'|'anonymize'  $strategy
     */
    public function destroy(int $subjectId, int $companyId, string $strategy): void;
}
