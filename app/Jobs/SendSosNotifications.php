<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Notifications\SosAlertNotification;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class SendSosNotifications implements ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public Alert $alert) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $alert = $this->alert->fresh(['blindUser']);

        if ($alert === null) {
            return;
        }

        $recipients = $alert->blindUser
            ->familyMembersWhoReceiveAlerts()
            ->where('users.is_active', true)
            ->get();

        Notification::sendNow($recipients, new SosAlertNotification($alert));
    }
}
