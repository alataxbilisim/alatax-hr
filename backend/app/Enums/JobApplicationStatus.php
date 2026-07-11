<?php

namespace App\Enums;

enum JobApplicationStatus: string
{
    case New = 'new';
    case Reviewing = 'reviewing';
    case Shortlisted = 'shortlisted';
    case InterviewScheduled = 'interview_scheduled';
    case Interviewed = 'interviewed';
    case OfferSent = 'offer_sent';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
}
