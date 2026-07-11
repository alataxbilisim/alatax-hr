<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000004_create_performance_extended_tables.php::relationship */
enum RelationshipValue: string
{
    case SelfCase = 'self';
    case Manager = 'manager';
    case Peer = 'peer';
    case DirectReport = 'direct_report';
    case External = 'external';
}
