<?php

namespace App\Filament\Widgets;

use App\Enums\AlertType;
use App\Enums\DeliveryStatus;
use App\Models\NotificationDelivery;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class SosDeliveryTime extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $actor = Filament::auth()->user();
        $deliveries = $actor instanceof User
            ? NotificationDelivery::query()
                ->visibleTo($actor)
                ->where('status', DeliveryStatus::Delivered)
                ->whereHas('alert', fn (Builder $query): Builder => $query->where('type', AlertType::Sos))
                ->with('alert')
                ->get()
            : collect();
        $averageSeconds = $deliveries->filter(fn (NotificationDelivery $delivery): bool => $delivery->delivered_at !== null && $delivery->alert !== null)
            ->map(fn (NotificationDelivery $delivery): int => $delivery->delivered_at->diffInSeconds($delivery->alert->created_at))
            ->average();

        return [
            Stat::make(__('filament.widgets.sos_delivery'), $averageSeconds === null ? '—' : round($averageSeconds).' s')
                ->description(__('filament.widgets.sos_delivery_description'))
                ->icon('heroicon-o-clock'),
        ];
    }
}
