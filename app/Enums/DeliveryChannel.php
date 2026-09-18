<?php

namespace App\Enums;

enum DeliveryChannel: string
{
    case Sms = 'sms';
    case Voice = 'voice';
}
