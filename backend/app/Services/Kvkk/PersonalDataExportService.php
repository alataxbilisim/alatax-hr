<?php

namespace App\Services\Kvkk;

use App\Models\DataSubjectExportAccessLog;
use App\Models\DataSubjectExportPackage;
use App\Models\DataSubjectRequest;
use App\Models\User;
use App\Services\Kvkk\PersonalData\PersonalDataCollectorRegistry;
use App\Services\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

/**
 * D2b — Kişisel veri ihraç paketi (güvenlik kritik).
 * Önizleme YOK; paket private diskte; e-posta eki YOK.
 */
class PersonalDataExportService
{
    public function __construct(
        protected PersonalDataCollectorRegistry $registry,
    ) {}

    public function assertCanBuild(DataSubjectRequest $request): void
    {
        if (! $request->identity_verified) {
            throw ValidationException::withMessages([
                'identity_verified' => ['Kimlik doğrulanmadan ihraç paketi üretilemez.'],
            ]);
        }
        if (! $request->subject_id) {
            throw ValidationException::withMessages([
                'subject_id' => ['Paket için sistemdeki özne kaydı (subject_id) gerekli.'],
            ]);
        }
    }

    public function createPendingPackage(DataSubjectRequest $request, User $actor): DataSubjectExportPackage
    {
        $this->assertCanBuild($request);

        $days = (int) Settings::get('kvkk.data_subject.export_link_days', [
            'company_id' => (int) $request->company_id,
        ]);
        if ($days < 1) {
            $days = 7;
        }

        return DataSubjectExportPackage::query()->create([
            'company_id' => $request->company_id,
            'data_subject_request_id' => $request->id,
            'status' => 'pending',
            'expires_at' => now()->addDays($days),
            'created_by' => $actor->id,
        ]);
    }

    public function buildPackage(DataSubjectExportPackage $package): DataSubjectExportPackage
    {
        $request = $package->request;
        if (! $request) {
            throw new RuntimeException('Talep bulunamadı');
        }
        $this->assertCanBuild($request);

        $subjectType = $request->subject_type->value;
        $subjectId = (int) $request->subject_id;
        $companyId = (int) $request->company_id;

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'company_id' => $companyId,
            'request_id' => $request->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'applicant_name' => $request->applicant_name,
            'anonymous_survey_note' => 'Anonim anket cevapları sizinle ilişkilendirilemediği için pakete dahil edilmemiştir.',
            'collectors' => $this->registry->collectAll($subjectType, $subjectId, $companyId),
        ];

        // Kapsam izolasyonu: başka company_id kaydı olmamalı (collector'lar zaten filtreler)
        $this->assertTenantIsolation($payload['collectors'], $companyId);

        $base = 'kvkk-exports/'.$companyId.'/'.$package->uuid;
        $disk = Storage::disk('local');
        $disk->makeDirectory($base);

        $jsonRel = $base.'/data.json';
        $humanRel = $base.'/ozet.html';
        $zipRel = $base.'/paket.zip';

        $disk->put($jsonRel, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $disk->put($humanRel, $this->renderHumanHtml($payload, $request));

        $zipAbs = $disk->path($zipRel);
        $zip = new ZipArchive;
        if ($zip->open($zipAbs, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ZIP oluşturulamadı');
        }
        $zip->addFile($disk->path($jsonRel), 'data.json');
        $zip->addFile($disk->path($humanRel), 'ozet.html');

        foreach ($payload['collectors'] as $block) {
            foreach ($block['sections'] as $section) {
                foreach ($section['files'] as $file) {
                    $path = (string) ($file['path'] ?? '');
                    if ($path === '') {
                        continue;
                    }
                    $abs = $disk->exists($path) ? $disk->path($path) : (file_exists($path) ? $path : null);
                    if ($abs && is_file($abs)) {
                        $name = 'files/'.($file['name'] ?? basename($path));
                        $zip->addFile($abs, $name);
                    }
                }
            }
        }
        $zip->close();

        $package->forceFill([
            'status' => 'ready',
            'json_path' => $jsonRel,
            'human_path' => $humanRel,
            'storage_path' => $zipRel,
            'error_message' => null,
        ])->save();

        return $package->fresh();
    }

    public function logDownload(DataSubjectExportPackage $package, ?User $user, string $action = 'download'): void
    {
        DataSubjectExportAccessLog::query()->create([
            'company_id' => $package->company_id,
            'export_package_id' => $package->id,
            'user_id' => $user?->id,
            'action' => $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    public function purgeExpired(): int
    {
        $count = 0;
        $packages = DataSubjectExportPackage::query()
            ->where('status', 'ready')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($packages as $pkg) {
            $this->purgePackage($pkg);
            $count++;
        }

        return $count;
    }

    public function purgePackage(DataSubjectExportPackage $package): void
    {
        $disk = Storage::disk('local');
        foreach ([$package->storage_path, $package->json_path, $package->human_path] as $path) {
            if ($path && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
        $dir = 'kvkk-exports/'.$package->company_id.'/'.$package->uuid;
        if ($disk->exists($dir)) {
            $disk->deleteDirectory($dir);
        }
        $package->forceFill([
            'status' => 'purged',
            'purged_at' => now(),
            'storage_path' => null,
            'json_path' => null,
            'human_path' => null,
        ])->save();
    }

    /**
     * @param  list<array<string, mixed>>  $collectors
     */
    private function assertTenantIsolation(array $collectors, int $companyId): void
    {
        foreach ($collectors as $block) {
            foreach ($block['sections'] as $section) {
                foreach ($section['records'] as $record) {
                    if (isset($record['company_id']) && (int) $record['company_id'] !== $companyId) {
                        throw new RuntimeException('Tenant izolasyon ihlali: başka firma kaydı pakete girdi');
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderHumanHtml(array $payload, DataSubjectRequest $request): string
    {
        $esc = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><title>Kişisel Veri Özeti</title></head><body>';
        $html .= '<h1>Kişisel Veri İhraç Özeti</h1>';
        $html .= '<p>Talep #'.$esc($request->id).' — '.$esc($request->applicant_name).'</p>';
        $html .= '<p>'.$esc($payload['anonymous_survey_note']).'</p>';
        foreach ($payload['collectors'] as $block) {
            $html .= '<h2>'.$esc($block['collector']).'</h2>';
            foreach ($block['sections'] as $section) {
                $html .= '<h3>'.$esc($section['label']).' ('.$esc($section['category']).')</h3>';
                $html .= '<pre>'.$esc(json_encode($section['records'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)).'</pre>';
            }
        }
        $html .= '</body></html>';

        return $html;
    }
}
