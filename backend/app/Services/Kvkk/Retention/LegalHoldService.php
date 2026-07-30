<?php

namespace App\Services\Kvkk\Retention;

use App\Models\LegalHold;
use App\Models\User;

class LegalHoldService
{
    public function place(
        int $companyId,
        User $actor,
        string $subjectType,
        int $subjectId,
        string $reason,
        ?string $caseReference = null,
    ): LegalHold {
        return LegalHold::query()->create([
            'company_id' => $companyId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'reason' => $reason,
            'case_reference' => $caseReference,
            'placed_by' => $actor->id,
            'placed_at' => now(),
            'active' => true,
        ]);
    }

    public function release(LegalHold $hold, User $actor): LegalHold
    {
        $hold->forceFill([
            'active' => false,
            'released_at' => now(),
            'released_by' => $actor->id,
        ])->save();

        return $hold->fresh();
    }
}
