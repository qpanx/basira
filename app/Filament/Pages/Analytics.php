<?php

namespace App\Filament\Pages;

use App\Enums\AlertType;
use App\Enums\DeliveryStatus;
use App\Models\Alert;
use App\Models\BatteryLog;
use App\Models\Location;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Models\UserFeedback;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

class Analytics extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.analytics';

    public static function getNavigationLabel(): string
    {
        return __('filament.analytics.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.smart_cane');
    }

    public function getTitle(): string
    {
        return __('filament.analytics.heading');
    }

    public static function canAccess(): bool
    {
        return Filament::auth()->user() instanceof User;
    }

    /**
     * @return array<string, int|float|string>
     */
    public function getMetrics(): array
    {
        $actor = Filament::auth()->user();

        if (! $actor instanceof User) {
            return [];
        }

        $alerts = Alert::query()->visibleTo($actor)->get();
        $locations = Location::query()->visibleTo($actor)->where('recorded_at', '>=', now()->subDays(30))->get();
        $deliveries = NotificationDelivery::query()
            ->visibleTo($actor)
            ->where('status', DeliveryStatus::Delivered)
            ->whereHas('alert', fn (Builder $query): Builder => $query->where('type', AlertType::Sos))
            ->with('alert')
            ->get();
        $feedback = UserFeedback::query()->visibleTo($actor)->get();
        $obstacleThresholds = [
            AlertType::ObstacleFront->value => 200,
            AlertType::ObstacleUpper->value => 100,
            AlertType::ObstacleLower->value => 30,
        ];
        $obstacleAlerts = $alerts->filter(fn (Alert $alert): bool => $alert->type?->isObstacle() && $alert->distance_cm !== null);
        $accurateObstacles = $obstacleAlerts->filter(function (Alert $alert) use ($obstacleThresholds): bool {
            $threshold = $obstacleThresholds[$alert->type->value] ?? null;

            return $threshold !== null && abs($alert->distance_cm - $threshold) <= max(1, $threshold * .2);
        })->count();
        $averageDelivery = $deliveries
            ->filter(fn (NotificationDelivery $delivery): bool => $delivery->alert !== null && $delivery->delivered_at !== null)
            ->map(fn (NotificationDelivery $delivery): int => $delivery->delivered_at->diffInSeconds($delivery->alert->created_at))
            ->average();

        return [
            'obstacle_accuracy' => $obstacleAlerts->count() === 0 ? 0 : round($accurateObstacles / $obstacleAlerts->count() * 100, 1),
            'false_alarm_rate' => $alerts->count() === 0
                ? 0
                : round($alerts->filter(fn (Alert $alert): bool => $alert->isFalsePositive())->count() / $alerts->count() * 100, 1),
            'average_sos_delivery' => $averageDelivery === null ? 0 : round($averageDelivery, 1),
            'gps_accuracy' => round((float) ($locations->avg('accuracy') ?? 0), 1),
            'battery_life_hours' => $this->averageBatteryLifeHours($actor),
            'satisfaction' => round((float) ($feedback->avg('rating') ?? 0), 1),
        ];
    }

    private function averageBatteryLifeHours(User $actor): float
    {
        $logs = BatteryLog::query()->visibleTo($actor)->orderBy('device_id')->orderBy('recorded_at')->get();
        $durations = [];
        foreach ($logs->groupBy('device_id') as $deviceLogs) {
            $startedAt = null;

            foreach ($deviceLogs as $log) {
                if ($log->power_state?->value === 'full' || $log->battery_level >= 95) {
                    $startedAt ??= $log->recorded_at;
                }

                if ($startedAt !== null && $log->battery_level <= 15) {
                    $durations[] = $startedAt->diffInMinutes($log->recorded_at) / 60;
                    $startedAt = null;
                }
            }
        }

        return round((float) (count($durations) ? array_sum($durations) / count($durations) : 0), 1);
    }
}
