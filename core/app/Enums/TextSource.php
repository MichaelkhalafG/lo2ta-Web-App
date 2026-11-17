<?php

namespace App\Enums;

enum TextSource: string
{
    use HasLabel;

    case Placeholder = 'placeholder';
    case Client = 'client';
}
