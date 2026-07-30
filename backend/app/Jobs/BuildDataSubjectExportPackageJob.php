<?php

namespace App\Jobs;

use App\Models\DataSubjectExportPackage;
use App\Services\Kvkk\PersonalDataExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BuildDataSubjectExportPackageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $packageId,
    ) {}

    public function handle(PersonalDataExportService $exports): void
    {
        $package = DataSubjectExportPackage::query()->find($this->packageId);
        if (! $package || $package->status !== 'pending') {
            return;
        }

        try {
            $exports->buildPackage($package);
        } catch (\Throwable $e) {
            Log::error('kvkk.export.job_failed', [
                'package_id' => $this->packageId,
                'error' => $e->getMessage(),
            ]);
            $package->forceFill([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ])->save();
            throw $e;
        }
    }
}
