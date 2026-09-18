<?php

namespace App\Enums;

enum FamilyPermission: string
{
    case ViewLocation = 'can_view_location';
    case ReceiveAlerts = 'can_receive_alerts';
    case ManageSafeZones = 'can_manage_safe_zones';
}
