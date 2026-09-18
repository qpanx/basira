<?php

namespace App\Filament\Widgets;

use App\Models\BatteryLog;
use App\Models\Device;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class BatteryAnalyticsChart extends ChartWidget
{
    protected static ?string $pollingInterval = null;

    public function getHeading(): string
    {
        return __('filament.widgets.battery');
    }

    protected function getFilters(): ?array
    {
        $actor = Filament::auth()->user();

        if (! $actor instanceof User) {
            return [];
        }

        return Device::query()
            ->visibleTo($actor)
            ->orderBy('serial_number')
            ->pluck('serial_number', 'id')
            ->all();
    }

    protected function getData(): array
    {
        $actor = Filament::auth()->user();
        $logs = $actor instanceof User
            ? BatteryLog::query()
                ->visibleTo($actor)
                ->when($this->filter !== null, fn (Builder $query): Builder => $query->where('device_id', $this->filter))
                ->where('recorded_at', '>=', now()->subDay())
                ->orderBy('recorded_at')
                ->get()
            : collect();

        return [
            'datasets' => [[
                'label' => __('filament.fields.battery_level'),
                'data' => $logs->map(fn (BatteryLog $log): int => $log->battery_level)->values()->all(),
                'borderColor' => '#d97706',
                'backgroundColor' => 'rgba(217, 119, 6, .15)',
                'fill' => true,
                'tension' => .35,
            ]],
            'labels' => $logs->map(fn (BatteryLog $log): string => $log->recorded_at->format('H:i'))->values()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
