<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Queued = 'queued';
    case Accepted = 'accepted';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
