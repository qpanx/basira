<?php

namespace App\Console\Commands;

use App\Enums\DeviceStatus;
use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class MarkStaleDevicesOffline extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devices:mark-offline';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark devices that have not sent a heartbeat recently as offline.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = Carbon::now()->subMinutes((int) config('smart-cane.offline_after_minutes', 10));

        $updated = Device::query()
            ->where('status', DeviceStatus::Active)
            ->where(function (Builder $query) use ($cutoff): void {
                $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $cutoff);
            })
            ->update(['status' => DeviceStatus::Offline]);

        $this->info("Marked {$updated} device(s) offline.");

        return self::SUCCESS;
    }
}
