<?php

namespace App\Enums;

enum AlertResolution: string
{
    case Confirmed = 'confirmed';
    case FalsePositive = 'false_positive';
}
