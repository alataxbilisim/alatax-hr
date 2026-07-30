<?php

namespace App\Enums;

enum DestructionCandidateStatus: string
{
    case Pending = 'pending';
    case Deferred = 'deferred';
    case Excluded = 'excluded';
    case SkippedLegalHold = 'skipped_legal_hold';
    case Approved = 'approved';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
