<x-filament-panels::page>
    {{-- Text-to-speech toolbar: browser Web Speech API only, no cloud TTS. --}}
    <x-filament::section
        :heading="__('filament.simulator.sections.speech')"
        :description="__('filament.simulator.section_descriptions.speech')"
        icon="heroicon-o-speaker-wave"
    >
        <div id="tts-unsupported" class="hidden">
            <p class="mb-3 rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger-700 ring-1 ring-inset ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400">
                {{ __('filament.simulator.messages.tts_unsupported') }}
            </p>
        </div>

        <div class="flex flex-col gap-4 md:flex-row md:items-end">
            <label class="flex min-w-40 flex-col gap-1.5 text-sm font-medium">
                {{ __('filament.simulator.fields.speech_language') }}
                <select
                    id="tts-language"
                    class="h-10 w-full rounded-lg border-none bg-white py-2 pe-8 ps-3 text-sm shadow-sm outline-none ring-1 ring-gray-950/10 transition focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:ring-white/10"
                >
                    <option value="en">English (en-US)</option>
                    <option value="ar">العربية (ar-EG)</option>
                </select>
            </label>

            <label class="flex min-w-56 flex-1 flex-col gap-1.5 text-sm font-medium">
                {{ __('filament.simulator.fields.voice') }}
                <select
                    id="tts-voice"
                    data-placeholder="{{ __('filament.simulator.fields.default_voice') }}"
                    class="h-10 w-full rounded-lg border-none bg-white py-2 pe-8 ps-3 text-sm shadow-sm outline-none ring-1 ring-gray-950/10 transition focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:ring-white/10"
                >
                    <option value="">{{ __('filament.simulator.fields.default_voice') }}</option>
                </select>
            </label>

            <label class="flex min-w-44 flex-col gap-1.5 text-sm font-medium">
                {{ __('filament.simulator.fields.volume') }}
                <input
                    id="tts-volume"
                    type="range"
                    min="0"
                    max="1"
                    step="0.1"
                    value="1"
                    class="w-full accent-primary-600"
                >
            </label>

            <x-filament::button color="gray" type="button" data-tts-stop icon="heroicon-o-stop">
                {{ __('filament.simulator.actions.stop_speaking') }}
            </x-filament::button>
        </div>

        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
            {{ __('filament.simulator.messages.tts_retry_hint') }}
        </p>
    </x-filament::section>

    {{-- Vision --}}
    <x-filament::section
        :heading="__('filament.simulator.sections.vision')"
        :description="__('filament.simulator.section_descriptions.vision')"
        icon="heroicon-o-eye"
        class="mt-6"
    >
        <div class="flex flex-col gap-4">
            <label class="flex flex-col gap-1.5 text-sm font-medium">
                {{ __('filament.simulator.fields.image') }}
                <input
                    type="file"
                    wire:model="visionImage"
                    accept="image/jpeg,image/png,image/webp"
                    class="block w-full cursor-pointer text-sm file:me-4 file:rounded-lg file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-500"
                >
            </label>
            <div wire:loading wire:target="visionImage" class="text-sm text-gray-500">
                {{ __('filament.simulator.messages.uploading') }}
            </div>
            @error('visionImage')
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
            @enderror

            <label class="flex flex-col gap-1.5 text-sm font-medium">
                {{ __('filament.simulator.fields.language') }}
                <select
                    wire:model.live="visionLanguage"
                    class="h-10 w-full max-w-xs rounded-lg border-none bg-white py-2 pe-8 ps-3 text-sm shadow-sm outline-none ring-1 ring-gray-950/10 transition focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:ring-white/10"
                >
                    <option value="en">English</option>
                    <option value="ar">العربية</option>
                </select>
            </label>

            <div class="flex flex-wrap items-center gap-3">
                <x-filament::button wire:click="detectVision" wire:loading.attr="disabled" wire:target="detectVision">
                    {{ __('filament.simulator.actions.detect') }}
                </x-filament::button>
                @if($visionSpeech !== null)
                    <x-filament::button color="gray" type="button" data-tts-text="{{ $visionSpeech }}" data-tts-lang="{{ $visionLanguage }}" icon="heroicon-o-speaker-wave">
                        {{ __('filament.simulator.actions.read_aloud') }}
                    </x-filament::button>
                    <x-filament::button color="gray" type="button" data-tts-stop icon="heroicon-o-stop">
                        {{ __('filament.simulator.actions.stop_speaking') }}
                    </x-filament::button>
                @endif
                <span wire:loading wire:target="detectVision" class="text-sm text-gray-500">
                    {{ __('filament.simulator.messages.processing') }}
                </span>
            </div>

            @if($visionError !== null)
                <p class="rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger-700 ring-1 ring-inset ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400">
                    {{ $visionError }}
                </p>
            @endif

            @if($visionObjects !== null)
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-filament::badge color="success">{{ __('filament.simulator.labels.recognition_status') }}</x-filament::badge>
                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ $visionMessage }}</span>
                    </div>

                    @if($visionObjects !== [])
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 text-start dark:border-white/10">
                                        <th class="py-2 text-start font-medium">{{ __('filament.simulator.labels.objects') }}</th>
                                        <th class="py-2 text-start font-medium">{{ __('filament.simulator.labels.confidence') }}</th>
                                        <th class="py-2 text-start font-medium">{{ __('filament.simulator.labels.position') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($visionObjects as $object)
                                        <tr class="border-b border-gray-100 dark:border-white/5">
                                            <td class="py-2">{{ $object['label'] }}</td>
                                            <td class="py-2">{{ number_format($object['confidence'] * 100, 0) }}%</td>
                                            <td class="py-2">{{ __('filament.simulator.positions.'.$object['position']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <details class="text-sm">
                            <summary class="cursor-pointer font-medium">{{ __('filament.simulator.labels.raw_result') }}</summary>
                            <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs dark:bg-white/5">{{ json_encode($visionObjects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </details>
                    @endif

                    <p class="text-sm text-warning-700 dark:text-warning-400">{{ __('assistive.vision_notice') }}</p>
                </div>
            @endif
        </div>
    </x-filament::section>

    {{-- OCR --}}
    <x-filament::section
        :heading="__('filament.simulator.sections.ocr')"
        :description="__('filament.simulator.section_descriptions.ocr')"
        icon="heroicon-o-document-text"
        class="mt-6"
    >
        <div class="flex flex-col gap-4">
            <label class="flex flex-col gap-1.5 text-sm font-medium">
                {{ __('filament.simulator.fields.image') }}
                <input
                    type="file"
                    wire:model="ocrImage"
                    accept="image/jpeg,image/png,image/webp"
                    class="block w-full cursor-pointer text-sm file:me-4 file:rounded-lg file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-500"
                >
            </label>
            <div wire:loading wire:target="ocrImage" class="text-sm text-gray-500">
                {{ __('filament.simulator.messages.uploading') }}
            </div>
            @error('ocrImage')
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
            @enderror

            <label class="flex flex-col gap-1.5 text-sm font-medium">
                {{ __('filament.simulator.fields.language') }}
                <select
                    wire:model.live="ocrLanguage"
                    class="h-10 w-full max-w-xs rounded-lg border-none bg-white py-2 pe-8 ps-3 text-sm shadow-sm outline-none ring-1 ring-gray-950/10 transition focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:ring-white/10"
                >
                    <option value="en">English</option>
                    <option value="ar">العربية</option>
                </select>
            </label>

            <div class="flex flex-wrap items-center gap-3">
                <x-filament::button wire:click="extractText" wire:loading.attr="disabled" wire:target="extractText">
                    {{ __('filament.simulator.actions.extract') }}
                </x-filament::button>
                @if($ocrText !== null)
                    <x-filament::button color="gray" type="button" data-tts-text="{{ $ocrText }}" data-tts-lang="{{ $ocrLanguage }}" icon="heroicon-o-speaker-wave">
                        {{ __('filament.simulator.actions.read_aloud') }}
                    </x-filament::button>
                    <x-filament::button color="gray" type="button" data-tts-stop icon="heroicon-o-stop">
                        {{ __('filament.simulator.actions.stop_speaking') }}
                    </x-filament::button>
                @endif
                <span wire:loading wire:target="extractText" class="text-sm text-gray-500">
                    {{ __('filament.simulator.messages.processing') }}
                </span>
            </div>

            @if($ocrError !== null)
                <p class="rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger-700 ring-1 ring-inset ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400">
                    {{ $ocrError }}
                </p>
            @endif

            @if($ocrText !== null)
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <x-filament::badge color="info">{{ __('filament.simulator.labels.extracted_text') }}</x-filament::badge>
                        @if($ocrDetectedLanguage !== null)
                            <x-filament::badge color="gray">{{ __('filament.simulator.labels.detected_language').': '.$ocrDetectedLanguage }}</x-filament::badge>
                        @endif
                    </div>
                    <pre class="overflow-x-auto whitespace-pre-wrap rounded-lg bg-gray-50 p-4 text-sm dark:bg-white/5">{{ $ocrText }}</pre>
                </div>
            @endif
        </div>
    </x-filament::section>

    {{-- Navigation --}}
    <x-filament::section
        :heading="__('filament.simulator.sections.navigation')"
        :description="__('filament.simulator.section_descriptions.navigation')"
        icon="heroicon-o-map"
        class="mt-6"
    >
        <div class="space-y-4">
            <p class="rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-700 ring-1 ring-inset ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400">
                {{ __('filament.simulator.messages.simulated_notice') }}
            </p>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <label class="flex flex-col gap-1.5 text-sm font-medium">
                    {{ __('filament.simulator.fields.device') }}
                    <select
                        wire:model.live="selectedDeviceId"
                        @if($simulation !== null && ($simulation['status'] ?? null) === 'active') disabled @endif
                        class="h-10 w-full rounded-lg border-none bg-white py-2 pe-8 ps-3 text-sm shadow-sm outline-none ring-1 ring-gray-950/10 transition focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:ring-white/10 disabled:opacity-60"
                    >
                        <option value="">{{ __('filament.simulator.messages.select_device') }}</option>
                        @foreach($this->deviceOptions() as $deviceId => $label)
                            <option value="{{ $deviceId }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="flex flex-col gap-1.5 text-sm font-medium">
                    {{ __('filament.simulator.fields.language') }}
                    <select
                        wire:model.live="navigationLanguage"
                        @if($simulation !== null && ($simulation['status'] ?? null) === 'active') disabled @endif
                        class="h-10 w-full rounded-lg border-none bg-white py-2 pe-8 ps-3 text-sm shadow-sm outline-none ring-1 ring-gray-950/10 transition focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:ring-white/10 disabled:opacity-60"
                    >
                        <option value="en">English</option>
                        <option value="ar">العربية</option>
                    </select>
                </label>

                <label class="flex flex-col gap-1.5 text-sm font-medium">
                    {{ __('filament.simulator.fields.origin_latitude') }}
                    <input type="text" wire:model.live="originLatitude" inputmode="decimal" @if($simulation !== null && ($simulation['status'] ?? null) === 'active') disabled @endif
                        class="h-10 w-full rounded-lg border-none bg-white px-3 py-2 text-sm shadow-sm outline-none ring-1 ring-gray-950/10 transition focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:ring-white/10 disabled:opacity-60">
                </label>

                <label class="flex flex-col gap-1.5 text-sm font-medium">
                    {{ __('filament.simulator.fields.origin_longitude') }}
                    <input type="text" wire:model.live="originLongitude" inputmode="decimal" @if($simulation !== null && ($simulation['status'] ?? null) === 'active') disabled @endif
                        class="h-10 w-full rounded-lg border-none bg-white px-3 py-2 text-sm shadow-sm outline-none ring-1 ring-gray-950/10 transition focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:ring-white/10 disabled:opacity-60">
                </label>

                <label class="flex flex-col gap-1.5 text-sm font-medium">
                    {{ __('filament.simulator.fields.destination_latitude') }}
                    <input type="text" wire:model.live="destinationLatitude" inputmode="decimal" @if($simulation !== null && ($simulation['status'] ?? null) === 'active') disabled @endif
                        class="h-10 w-full rounded-lg border-none bg-white px-3 py-2 text-sm shadow-sm outline-none ring-1 ring-gray-950/10 transition focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:ring-white/10 disabled:opacity-60">
                </label>

                <label class="flex flex-col gap-1.5 text-sm font-medium">
                    {{ __('filament.simulator.fields.destination_longitude') }}
                    <input type="text" wire:model.live="destinationLongitude" inputmode="decimal" @if($simulation !== null && ($simulation['status'] ?? null) === 'active') disabled @endif
                        class="h-10 w-full rounded-lg border-none bg-white px-3 py-2 text-sm shadow-sm outline-none ring-1 ring-gray-950/10 transition focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:ring-white/10 disabled:opacity-60">
                </label>
            </div>

            @error('selectedDeviceId')
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
            @enderror
            @foreach(['originLatitude', 'originLongitude', 'destinationLatitude', 'destinationLongitude'] as $coordinateField)
                @error($coordinateField)
                    <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @enderror
            @endforeach

            <div class="flex flex-wrap items-center gap-3">
                <x-filament::button wire:click="startSimulation" wire:loading.attr="disabled" wire:target="startSimulation">
                    {{ __('filament.simulator.actions.start') }}
                </x-filament::button>
            </div>

            @if($navigationError !== null)
                <p class="rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger-700 ring-1 ring-inset ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400">
                    {{ $navigationError }}
                </p>
            @endif

            @if($simulation !== null)
                @php
                    $isActive = ($simulation['status'] ?? null) === 'active';
                    $repeatText = $simulation['current_instruction'] ?? null;
                    if ($repeatText === null && ($simulation['status'] ?? null) === 'completed') {
                        $repeatText = __('filament.simulator.messages.arrived', [], $simulation['language']);
                    }
                @endphp

                <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-inset ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-filament::badge color="warning">{{ __('filament.simulator.badges.simulated') }}</x-filament::badge>
                        @if(($simulation['status'] ?? null) === 'active')
                            <x-filament::badge color="success">{{ __('assistive.statuses.'.$simulation['status']) }}</x-filament::badge>
                        @elseif(($simulation['status'] ?? null) === 'completed')
                            <x-filament::badge color="info">{{ __('assistive.statuses.'.$simulation['status']) }}</x-filament::badge>
                        @else
                            <x-filament::badge color="gray">{{ __('assistive.statuses.'.$simulation['status']) }}</x-filament::badge>
                        @endif
                        @if($simulation['off_route'] ?? false)
                            <x-filament::badge color="danger">{{ __('filament.simulator.badges.off_route') }}</x-filament::badge>
                        @endif
                    </div>

                    @if($simulation['production_session_active'] ?? false)
                        <p class="mt-3 text-sm text-warning-700 dark:text-warning-400">
                            {{ __('filament.simulator.messages.production_session_active') }}
                        </p>
                    @endif

                    <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('filament.simulator.labels.current_instruction') }}</div>
                            <div class="mt-1 text-sm font-semibold">{{ $simulation['current_instruction'] ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('filament.simulator.labels.current_step') }}</div>
                            <div class="mt-1 text-sm font-semibold">
                                @if(($simulation['status'] ?? null) === 'active' || ($simulation['status'] ?? null) === 'completed')
                                    {{ __('filament.simulator.messages.step_of', [
                                        'current' => min(($simulation['current_step_index'] ?? 0) + 1, count($simulation['steps'] ?? [])),
                                        'total' => count($simulation['steps'] ?? []),
                                    ]) }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('filament.simulator.labels.total_distance') }}</div>
                            <div class="mt-1 text-sm font-semibold">{{ __('filament.simulator.messages.meters', ['count' => (int) $simulation['distance_meters']]) }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('filament.simulator.labels.estimated_duration') }}</div>
                            <div class="mt-1 text-sm font-semibold">{{ __('filament.simulator.messages.minutes', ['count' => (int) ceil($simulation['estimated_duration_seconds'] / 60)]) }}</div>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('filament.simulator.labels.destination') }}</div>
                            <div class="mt-1 text-sm">{{ $simulation['destination']['name'] ?? '' }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('filament.simulator.labels.provider') }}</div>
                            <div class="mt-1 text-sm">OSRM — {{ $simulation['attribution'] ?? '' }}</div>
                        </div>
                    </div>

                    @if(! empty($simulation['warnings']))
                        <ul class="mt-3 list-inside list-disc text-sm text-gray-500 dark:text-gray-400">
                            @foreach($simulation['warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="flex flex-col gap-1.5 text-sm font-medium">
                        <span class="flex items-center gap-2">
                            {{ __('filament.simulator.fields.current_latitude') }}
                            <x-filament::badge color="warning">{{ __('filament.simulator.badges.simulated') }}</x-filament::badge>
                        </span>
                        <input type="text" wire:model.live="currentLatitude" inputmode="decimal" @if(! $isActive) disabled @endif
                            class="h-10 w-full rounded-lg border-none bg-white px-3 py-2 text-sm shadow-sm outline-none ring-1 ring-gray-950/10 transition focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:ring-white/10 disabled:opacity-60">
                    </label>
                    <label class="flex flex-col gap-1.5 text-sm font-medium">
                        <span class="flex items-center gap-2">
                            {{ __('filament.simulator.fields.current_longitude') }}
                            <x-filament::badge color="warning">{{ __('filament.simulator.badges.simulated') }}</x-filament::badge>
                        </span>
                        <input type="text" wire:model.live="currentLongitude" inputmode="decimal" @if(! $isActive) disabled @endif
                            class="h-10 w-full rounded-lg border-none bg-white px-3 py-2 text-sm shadow-sm outline-none ring-1 ring-gray-950/10 transition focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:ring-white/10 disabled:opacity-60">
                    </label>
                </div>

                @error('currentLatitude')
                    <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @enderror
                @error('currentLongitude')
                    <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @enderror

                <div class="flex flex-wrap items-center gap-3">
                    <x-filament::button color="gray" wire:click="updateSimulatedGps" wire:loading.attr="disabled" wire:target="updateSimulatedGps" :disabled="! $isActive">
                        {{ __('filament.simulator.actions.update_gps') }}
                    </x-filament::button>
                    <x-filament::button color="gray" type="button" icon="heroicon-o-speaker-wave" :disabled="$repeatText === null || $repeatText === ''" :data-tts-text="$repeatText" :data-tts-lang="$simulation['language'] ?? null">
                        {{ __('filament.simulator.actions.read_instruction') }}
                    </x-filament::button>
                    <x-filament::button color="gray" type="button" data-tts-stop icon="heroicon-o-stop">
                        {{ __('filament.simulator.actions.stop_speaking') }}
                    </x-filament::button>
                    <x-filament::button color="danger" wire:click="stopSimulation" wire:loading.attr="disabled" wire:target="stopSimulation" :disabled="! $isActive">
                        {{ __('filament.simulator.actions.stop') }}
                    </x-filament::button>
                    <span wire:loading wire:target="startSimulation,updateSimulatedGps,stopSimulation" class="text-sm text-gray-500">
                        {{ __('filament.simulator.messages.processing') }}
                    </span>
                </div>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('filament.simulator.messages.isolated') }}
                </p>
            @endif
        </div>
    </x-filament::section>

    <script>
        (function () {
            if (window.__basiraTtsReady) {
                return;
            }
            window.__basiraTtsReady = true;

            var supported = typeof window.speechSynthesis !== 'undefined'
                && typeof window.SpeechSynthesisUtterance !== 'undefined';
            var voices = [];
            var storageKey = 'basiraTtsSettings';
            var settings = { lang: 'en', voiceURI: '', volume: 1 };

            try {
                var stored = JSON.parse(window.localStorage.getItem(storageKey) || 'null');
                if (stored && typeof stored === 'object') {
                    settings.lang = stored.lang === 'ar' ? 'ar' : 'en';
                    settings.voiceURI = typeof stored.voiceURI === 'string' ? stored.voiceURI : '';
                    if (typeof stored.volume === 'number' && !isNaN(stored.volume)) {
                        settings.volume = Math.max(0, Math.min(1, stored.volume));
                    }
                }
            } catch (error) {}

            function saveSettings() {
                try {
                    window.localStorage.setItem(storageKey, JSON.stringify(settings));
                } catch (error) {}
            }

            function localeFor(lang) {
                return lang === 'ar' ? 'ar-EG' : 'en-US';
            }

            function prefixFor(lang) {
                return lang === 'ar' ? 'ar' : 'en';
            }

            var unsupportedNotice = document.getElementById('tts-unsupported');
            function showUnsupported() {
                var notice = document.getElementById('tts-unsupported') || unsupportedNotice;
                if (notice) {
                    notice.classList.remove('hidden');
                }
            }
            if (!supported) {
                showUnsupported();
            }

            function matchingVoices(lang) {
                var prefix = prefixFor(lang);
                return voices.filter(function (voice) {
                    return String(voice.lang || '').toLowerCase().replace('_', '-').indexOf(prefix) === 0;
                });
            }

            function refreshVoices() {
                if (!supported) {
                    return;
                }
                voices = window.speechSynthesis.getVoices() || [];
                var select = document.getElementById('tts-voice');
                if (!select) {
                    return;
                }
                var options = matchingVoices(settings.lang);
                if (options.length === 0) {
                    options = voices.slice();
                }
                select.innerHTML = '';
                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = select.getAttribute('data-placeholder') || '';
                select.appendChild(placeholder);
                options.forEach(function (voice) {
                    var option = document.createElement('option');
                    option.value = voice.voiceURI;
                    option.textContent = (voice.name || voice.voiceURI) + ' (' + (voice.lang || '') + ')';
                    select.appendChild(option);
                });
                select.value = settings.voiceURI || '';
                if (select.value !== (settings.voiceURI || '')) {
                    settings.voiceURI = '';
                    saveSettings();
                }
            }

            function applyStoredSettings() {
                var lang = document.getElementById('tts-language');
                if (lang) {
                    lang.value = settings.lang;
                }
                var volume = document.getElementById('tts-volume');
                if (volume) {
                    volume.value = String(settings.volume);
                }
                refreshVoices();
            }

            function stopSpeaking() {
                if (!supported) {
                    return;
                }
                window.speechSynthesis.cancel();
            }

            function speak(text, lang) {
                if (!text) {
                    return;
                }
                if (!supported) {
                    showUnsupported();
                    return;
                }
                // Cancel first so Livewire updates can never stack overlapping audio.
                stopSpeaking();
                var targetLang = lang || settings.lang;
                var utterance = new SpeechSynthesisUtterance(text);
                utterance.lang = localeFor(targetLang);
                utterance.volume = settings.volume;

                var selected = null;
                if (settings.voiceURI) {
                    voices.some(function (voice) {
                        if (
                            voice.voiceURI === settings.voiceURI
                            && String(voice.lang || '').toLowerCase().replace('_', '-').indexOf(prefixFor(targetLang)) === 0
                        ) {
                            selected = voice;
                            return true;
                        }
                        return false;
                    });
                }
                if (!selected) {
                    var matches = matchingVoices(targetLang);
                    selected = matches.length > 0 ? matches[0] : null;
                }
                if (selected) {
                    utterance.voice = selected;
                }
                window.speechSynthesis.speak(utterance);
            }

            document.addEventListener('basira-speak', function (event) {
                var detail = event.detail || {};
                speak(detail.text, detail.lang);
            });
            document.addEventListener('basira-stop-speech', stopSpeaking);

            document.addEventListener('click', function (event) {
                var target = event.target;
                if (!target || typeof target.closest !== 'function') {
                    return;
                }
                if (target.closest('[data-tts-stop]')) {
                    stopSpeaking();
                    return;
                }
                var speaker = target.closest('[data-tts-text]');
                if (speaker) {
                    speak(speaker.getAttribute('data-tts-text'), speaker.getAttribute('data-tts-lang') || null);
                }
            });

            document.addEventListener('change', function (event) {
                var target = event.target;
                if (!target || !target.id) {
                    return;
                }
                if (target.id === 'tts-language') {
                    settings.lang = target.value === 'ar' ? 'ar' : 'en';
                    saveSettings();
                    refreshVoices();
                }
                if (target.id === 'tts-voice') {
                    settings.voiceURI = target.value || '';
                    saveSettings();
                }
            });

            document.addEventListener('input', function (event) {
                var target = event.target;
                if (target && target.id === 'tts-volume') {
                    var value = parseFloat(target.value);
                    if (!isNaN(value)) {
                        settings.volume = Math.max(0, Math.min(1, value));
                        saveSettings();
                    }
                }
            });

            if (supported) {
                refreshVoices();
                window.speechSynthesis.addEventListener('voiceschanged', refreshVoices);
            }
            applyStoredSettings();

            function bindMorphHook() {
                if (window.__basiraTtsMorphHook) {
                    return;
                }
                if (!window.Livewire || typeof window.Livewire.hook !== 'function') {
                    return;
                }
                window.__basiraTtsMorphHook = true;
                window.Livewire.hook('morph.updated', applyStoredSettings);
            }
            document.addEventListener('livewire:init', bindMorphHook);
            document.addEventListener('livewire:load', bindMorphHook);
            bindMorphHook();
        })();
    </script>
</x-filament-panels::page>
