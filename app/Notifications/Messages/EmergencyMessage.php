<?php

namespace App\Notifications\Messages;

class EmergencyMessage
{
    public function __construct(public readonly string $body) {}
}
