<?php

namespace App\Events;

use App\Models\ApprovalRecord;
use App\Models\ApprovalStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApprovalRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ApprovalRecord $record,
        public User $approver,
        public Model $approvable,
        public ApprovalStep $step,
    ) {}
}
