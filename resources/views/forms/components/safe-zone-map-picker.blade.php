@php
    $centerLatStatePath = str_replace('.map', '.center_lat', $getStatePath());
    $centerLngStatePath = str_replace('.map', '.center_lng', $getStatePath());
    $radiusStatePath = str_replace('.map', '.radius_meters', $getStatePath());
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            state: {lat: null, lng: null, radius: null},
            centerLat: $wire.$entangle('{{ $centerLatStatePath }}'),
            centerLng: $wire.$entangle('{{ $centerLngStatePath }}'),
            radius: $wire.$entangle('{{ $radiusStatePath }}'),
            map: null,
            marker: null,
            circle: null,
            init() {
                const lat = this.centerLat === null || this.centerLat === '' ? 30.0444 : Number(this.centerLat);
                const lng = this.centerLng === null || this.centerLng === '' ? 31.2357 : Number(this.centerLng);
                const radius = this.radius === null || this.radius === '' ? 200 : Number(this.radius);
                this.centerLat = lat;
                this.centerLng = lng;
                this.radius = radius;
                this.state = {lat, lng, radius};
                if (typeof L === 'undefined') return;
                this.map = L.map(this.$refs.map).setView([lat, lng], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(this.map);
                this.updateOverlay();
                this.map.on('click', (event) => {
                    this.centerLat = event.latlng.lat;
                    this.centerLng = event.latlng.lng;
                    this.updateOverlay();
                });
            },
            syncFromForm() {
                const lat = this.centerLat === null || this.centerLat === '' ? 30.0444 : Number(this.centerLat);
                const lng = this.centerLng === null || this.centerLng === '' ? 31.2357 : Number(this.centerLng);
                const radius = this.radius === null || this.radius === '' ? 200 : Number(this.radius);
                if (this.state.lat !== lat || this.state.lng !== lng || this.state.radius !== radius) {
                    this.state = {lat, lng, radius};
                    this.updateOverlay();
                }
            },
            updateOverlay() {
                if (!this.map || !this.state) return;
                if (this.marker) this.marker.setLatLng([this.state.lat, this.state.lng]);
                else this.marker = L.marker([this.state.lat, this.state.lng]).addTo(this.map);
                if (this.circle) this.circle.setLatLng([this.state.lat, this.state.lng]).setRadius(Number(this.state.radius || 200));
                else this.circle = L.circle([this.state.lat, this.state.lng], {radius: Number(this.state.radius || 200), color: '#d97706'}).addTo(this.map);
            }
        }"
        x-init="init()"
        x-effect="syncFromForm()"
    >
        <div x-ref="map" class="h-72 overflow-hidden rounded-lg"></div>
        <p class="mt-2 text-xs text-gray-500">{{ __('filament.safe_zones_extra.map_help') }}</p>
    </div>
</x-dynamic-component>

@once
    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    @endpush
    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    @endpush
@endonce
