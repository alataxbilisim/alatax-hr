<?php

namespace App\Enums;

enum DataSubjectRequestStatus: string
{
    case New = 'new';
    case IdentityPending = 'identity_pending';
    case InReview = 'in_review';
    case AwaitingInfo = 'awaiting_info';
    case Approved = 'approved';
    case PartiallyApproved = 'partially_approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
}
