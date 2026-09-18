<?php

namespace App\Filament\Widgets;

use App\Models\Location;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class LocationHistoryPlayback extends Widget
{
    protected static string $view = 'filament.widgets.location-history-playback';

    protected int|string|array $columnSpan = 'full';

    public string $date;

    public int $pointIndex = 0;

    public function mount(): void
    {
        $this->date = Carbon::today()->toDateString();
    }

    public function updatedDate(): void
    {
        $this->pointIndex = 0;
        $this->dispatch('history-updated', points: $this->getPoints(), selectedIndex: $this->pointIndex);
    }

    public function updatedPointIndex(): void
    {
        $points = $this->getPoints();

        $this->pointIndex = min(max($this->pointIndex, 0), max(count($points) - 1, 0));
        $this->dispatch('history-point-updated', point: $points[$this->pointIndex] ?? null);
    }

    public function getPoints(): array
    {
        $actor = Filament::auth()->user();

        if (! $actor instanceof User) {
            return [];
        }

        return Location::query()
            ->visibleTo($actor)
            ->whereDate('recorded_at', $this->date)
            ->orderBy('recorded_at')
            ->get()
            ->map(fn (Location $location): array => [
                'id' => $location->getKey(),
                'lat' => (float) $location->lat,
                'lng' => (float) $location->lng,
                'accuracy' => $location->accuracy === null ? null : (float) $location->accuracy,
                'recorded_at' => $location->recorded_at->toISOString(),
            ])->values()->all();
    }
}
