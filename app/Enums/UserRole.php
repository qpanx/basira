<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case NgoStaff = 'ngo_staff';
    case Family = 'family';
}
