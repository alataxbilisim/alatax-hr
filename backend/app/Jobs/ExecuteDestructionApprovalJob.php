<?php

namespace App\Jobs;

use App\Models\DestructionApproval;
use App\Services\Kvkk\Retention\DestructionEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * D2c AŞAMA 3 — Onaylı imha uygulaması.
 * Approval kaydı yoksa DestructionEngine exception fırlatır.
 */
class ExecuteDestructionApprovalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $approvalId) {}

    public function handle(DestructionEngine $engine): void
    {
        $approval = DestructionApproval::query()->find($this->approvalId);
        $engine->assertCanExecute($approval);
        /** @var DestructionApproval $approval */
        \App\Support\CompanyContext::run((int) $approval->company_id, function () use ($engine, $approval): void {
            $engine->executeApproved($approval);
        });
    }
}
