<?php

namespace App\Enums;

enum PowerState: string
{
    case Charging = 'charging';
    case Discharging = 'discharging';
    case Full = 'full';
    case Unknown = 'unknown';
}
