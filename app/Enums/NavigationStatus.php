<?php

namespace App\Enums;

enum NavigationStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
