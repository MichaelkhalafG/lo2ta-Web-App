<?php

namespace App\Enums;

enum FeatureGroup: string
{
    use HasLabel;

    case Unit = 'unit';
    case Building = 'building';
    case Compound = 'compound';
    case View = 'view';
}
