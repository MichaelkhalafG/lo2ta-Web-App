<?php

namespace App\Enums;

enum MainType: string
{
    use HasLabel;

    case Residential = 'residential';
    case Commercial = 'commercial';
    case Administrative = 'administrative';
    case Land = 'land';
}
