<x-filament-panels::page>
    @php($metrics = $this->getMetrics())
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach([
            ['label' => __('filament.analytics.metrics.obstacle_accuracy'), 'value' => ($metrics['obstacle_accuracy'] ?? 0).'%', 'icon' => 'heroicon-o-adjustments-horizontal'],
            ['label' => __('filament.analytics.metrics.false_alarm_rate'), 'value' => ($metrics['false_alarm_rate'] ?? 0).'%', 'icon' => 'heroicon-o-flag'],
            ['label' => __('filament.analytics.metrics.average_sos_delivery'), 'value' => ($metrics['average_sos_delivery'] ?? 0).' s', 'icon' => 'heroicon-o-clock'],
            ['label' => __('filament.analytics.metrics.gps_accuracy'), 'value' => ($metrics['gps_accuracy'] ?? 0).' m', 'icon' => 'heroicon-o-map-pin'],
            ['label' => __('filament.analytics.metrics.battery_life'), 'value' => ($metrics['battery_life_hours'] ?? 0).' h', 'icon' => 'heroicon-o-battery-50'],
            ['label' => __('filament.analytics.metrics.satisfaction'), 'value' => ($metrics['satisfaction'] ?? 0).'/5', 'icon' => 'heroicon-o-face-smile'],
        ] as $metric)
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-filament::icon :icon="$metric['icon']" class="h-7 w-7 text-primary-600" />
                    <div>
                        <div class="text-sm text-gray-500">{{ $metric['label'] }}</div>
                        <div class="text-2xl font-semibold">{{ $metric['value'] }}</div>
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
