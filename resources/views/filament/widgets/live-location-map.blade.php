<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('filament.widgets.live_location') }}</x-slot>
        <div wire:ignore class="h-[28rem] overflow-hidden rounded-xl" id="live-location-map"></div>
    </x-filament::section>

    @once
        @push('styles')
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
        @endpush
        @push('scripts')
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
        @endpush
    @endonce
    <script>
        document.addEventListener('livewire:navigated', () => {
            const element = document.getElementById('live-location-map');
            if (!element || element.dataset.initialized || typeof L === 'undefined') return;
            element.dataset.initialized = 'true';
            const devices = @js($this->getDevices());
            const first = devices.find((device) => device.lat !== null && device.lng !== null);
            const map = L.map(element).setView(first ? [first.lat, first.lng] : [30.0444, 31.2357], first ? 14 : 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(map);
            const markers = new Map();
            devices.forEach((device) => {
                if (device.lat === null || device.lng === null) return;
                markers.set(device.id, L.marker([device.lat, device.lng]).addTo(map).bindPopup(device.name));
            });
            if (window.Echo) {
                devices.forEach((device) => window.Echo.private('family.' + device.blind_user_id).listen('.location.updated', (event) => {
                    const point = event.location;
                    if (!point || point.device_id !== device.id) return;
                    const marker = markers.get(device.id) || L.marker([point.lat, point.lng]).addTo(map).bindPopup(device.name);
                    marker.setLatLng([point.lat, point.lng]);
                    markers.set(device.id, marker);
                }));
            }
        });
    </script>
</x-filament-widgets::widget>
