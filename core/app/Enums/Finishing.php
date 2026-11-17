<?php

namespace App\Enums;

enum Finishing: string
{
    use HasLabel;

    case CoreShell = 'core_shell';
    case SemiFinished = 'semi_finished';
    case FullyFinished = 'fully_finished';
    case FinishedKitchenAcs = 'finished_kitchen_acs';
    case Furnished = 'furnished';
}
