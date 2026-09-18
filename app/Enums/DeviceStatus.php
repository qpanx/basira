<?php

namespace App\Enums;

enum DeviceStatus: string
{
    case Active = 'active';
    case Offline = 'offline';
    case Maintenance = 'maintenance';
}
