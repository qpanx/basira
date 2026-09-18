<x-filament-panels::page>
    @php($device = $this->getDevice())
    @php($latest = $device->locations->first())

    <x-filament::section :heading="$device->blindUser?->name ?? $device->serial_number">
        <div wire:ignore class="h-[32rem] overflow-hidden rounded-xl" id="device-live-map"></div>
        <div class="mt-3 text-sm text-gray-600 dark:text-gray-300">
            {{ __('filament.fields.serial_number') }}: {{ $device->serial_number }} ·
            {{ __('filament.fields.status') }}: {{ __('filament.statuses.'.$device->status->value) }}
        </div>
    </x-filament::section>

    @once
        @push('styles')
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        @endpush
        @push('scripts')
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        @endpush
    @endonce
    <script>
        document.addEventListener('livewire:navigated', () => {
            const element = document.getElementById('device-live-map');
            if (!element || element.dataset.initialized || typeof L === 'undefined') return;
            element.dataset.initialized = 'true';
            const initial = @js($latest ? ['lat' => (float) $latest->lat, 'lng' => (float) $latest->lng] : null);
            const map = L.map(element).setView(initial ? [initial.lat, initial.lng] : [30.0444, 31.2357], initial ? 16 : 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(map);
            let marker = initial ? L.marker([initial.lat, initial.lng]).addTo(map) : null;
            if (window.Echo) {
                window.Echo.private('family.{{ $device->blind_user_id }}').listen('.location.updated', (event) => {
                    const point = event.location;
                    if (!point || point.device_id !== {{ $device->getKey() }}) return;
                    if (marker) marker.setLatLng([point.lat, point.lng]);
                    else marker = L.marker([point.lat, point.lng]).addTo(map);
                    map.setView([point.lat, point.lng]);
                });
            }
        });
    </script>
</x-filament-panels::page>
