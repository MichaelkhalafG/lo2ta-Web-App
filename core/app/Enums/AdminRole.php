<?php

namespace App\Enums;

/** PLAN 2.4: admin (everything) and reviewer (units, documents, requests, deals; no settings, no commissions). */
enum AdminRole: string
{
    use HasLabel;

    case Admin = 'admin';
    case Reviewer = 'reviewer';
}
