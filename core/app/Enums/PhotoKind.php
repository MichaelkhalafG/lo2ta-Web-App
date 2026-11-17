<?php

namespace App\Enums;

enum PhotoKind: string
{
    use HasLabel;

    case Photo = 'photo';
    case FloorPlan = 'floor_plan';
}
