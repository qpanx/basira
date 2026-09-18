<x-filament-widgets::widget>
    @php($points = $this->getPoints())
    @php($selectedPoint = $points[$this->pointIndex] ?? null)
    <x-filament::section>
        <x-slot name="heading">{{ __('filament.widgets.history') }}</x-slot>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="text-sm font-medium" for="location-history-date">{{ __('filament.fields.date') }}</label>
                <input id="location-history-date" type="date" wire:model.live="date" class="fi-input mt-1" />
            </div>
            <div class="min-w-64 grow">
                <label class="text-sm font-medium" for="location-history-point">{{ __('filament.fields.timeline') }}</label>
                <input
                    id="location-history-point"
                    type="range"
                    min="0"
                    max="{{ max(count($points) - 1, 0) }}"
                    step="1"
                    wire:model.live="pointIndex"
                    @disabled(count($points) === 0)
                    class="mt-3 w-full accent-amber-600"
                />
            </div>
            <span class="text-sm text-gray-600 dark:text-gray-300">
                {{ count($points) === 0 ? '0' : $this->pointIndex + 1 }}/{{ count($points) }}
                · {{ $selectedPoint['recorded_at'] ?? '—' }}
            </span>
        </div>
        <div
            wire:ignore
            class="mt-4 h-[24rem] overflow-hidden rounded-xl"
            id="location-history-map"
            data-points="{{ json_encode($points) }}"
            data-selected-index="{{ $this->pointIndex }}"
            x-data="{
                map: null,
                line: null,
                markers: null,
                selectedMarker: null,
                points: [],
                selectedIndex: 0,
                init() {
                    this.points = JSON.parse(this.$el.dataset.points || '[]');
                    this.selectedIndex = Number(this.$el.dataset.selectedIndex || 0);
                    if (typeof L === 'undefined') return;
                    this.map = L.map(this.$el).setView([30.0444, 31.2357], 11);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(this.map);
                    this.draw();
                },
                draw() {
                    if (!this.map) return;
                    if (this.line) this.map.removeLayer(this.line);
                    if (this.markers) this.map.removeLayer(this.markers);
                    this.selectedMarker = null;
                    this.markers = L.layerGroup().addTo(this.map);
                    this.line = null;
                    if (!this.points.length) {
                        this.map.setView([30.0444, 31.2357], 11);
                        return;
                    }
                    const coordinates = this.points.map((point) => [point.lat, point.lng]);
                    this.line = L.polyline(coordinates, {color: '#d97706'}).addTo(this.map);
                    this.points.forEach((point) => L.circleMarker([point.lat, point.lng], {radius: 4}).addTo(this.markers).bindTooltip(new Date(point.recorded_at).toLocaleTimeString()));
                    this.selectPoint(this.points[this.selectedIndex] || this.points[0]);
                    this.map.fitBounds(this.line.getBounds(), {padding: [20, 20]});
                },
                selectPoint(point) {
                    if (!point) return;
                    if (this.selectedMarker) this.map.removeLayer(this.selectedMarker);
                    this.selectedMarker = L.circleMarker([point.lat, point.lng], {radius: 8, color: '#b45309', fillColor: '#f59e0b', fillOpacity: .9}).addTo(this.map);
                    this.selectedMarker.bindTooltip(new Date(point.recorded_at).toLocaleString()).openTooltip();
                    this.map.panTo([point.lat, point.lng]);
                },
                replace(points, selectedIndex) {
                    this.points = points || [];
                    this.selectedIndex = Number(selectedIndex || 0);
                    this.draw();
                },
                updateSelected(point) {
                    this.selectPoint(point);
                }
            }"
            x-init="init()"
            x-on:history-updated.window="replace($event.detail.points, $event.detail.selectedIndex)"
            x-on:history-point-updated.window="updateSelected($event.detail.point)"
        ></div>
    </x-filament::section>

    @once
        @push('styles')
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
        @endpush
        @push('scripts')
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
        @endpush
    @endonce
</x-filament-widgets::widget>
