<?php

namespace App\Filament\Pages;

use App\Enums\FamilyPermission;
use App\Enums\NavigationStatus;
use App\Exceptions\AssistiveServiceException;
use App\Models\Device;
use App\Models\User;
use App\Services\OCRService;
use App\Services\Simulator\NavigationSimulatorService;
use App\Services\Simulator\VisionSpeechDescriber;
use App\Services\VisionService;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Dashboard-only demonstration of vision, OCR, and OSRM navigation with
 * browser Text-to-Speech. All provider calls stay server-side; no device
 * bearer tokens or provider API keys reach the browser.
 */
class SmartCaneSimulator extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static string $view = 'filament.pages.smart-cane-simulator';

    public ?TemporaryUploadedFile $visionImage = null;

    public string $visionLanguage = 'en';

    /** @var list<array{label: string, confidence: float, position: string}>|null */
    public ?array $visionObjects = null;

    public ?string $visionSpeech = null;

    public ?string $visionMessage = null;

    public ?string $visionError = null;

    public ?TemporaryUploadedFile $ocrImage = null;

    public string $ocrLanguage = 'en';

    public ?string $ocrText = null;

    public ?string $ocrDetectedLanguage = null;

    public ?string $ocrError = null;

    public string|int|null $selectedDeviceId = null;

    public string $originLatitude = '30.0444';

    public string $originLongitude = '31.2357';

    public string $destinationLatitude = '30.0580';

    public string $destinationLongitude = '31.2310';

    public string $navigationLanguage = 'en';

    public string $currentLatitude = '30.0444';

    public string $currentLongitude = '31.2357';

    /** @var array<string, mixed>|null Isolated in-memory simulation state. */
    public ?array $simulation = null;

    public ?string $navigationError = null;

    public string $speechLanguage = 'en';

    public float $speechVolume = 1.0;

    public static function getNavigationLabel(): string
    {
        return __('filament.simulator.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.smart_cane');
    }

    public function getTitle(): string
    {
        return __('filament.simulator.heading');
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && ($user->isSuperAdmin() || $user->isNgoStaff());
    }

    /** @return array<int, string> */
    public function deviceOptions(): array
    {
        $user = Filament::auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        return Device::query()
            ->visibleTo($user, FamilyPermission::ViewLocation)
            ->with('blindUser')
            ->orderBy('serial_number')
            ->get()
            ->mapWithKeys(fn (Device $device): array => [
                $device->id => $device->serial_number.($device->blindUser !== null ? ' — '.$device->blindUser->name : ''),
            ])
            ->all();
    }

    public function detectVision(VisionService $vision, VisionSpeechDescriber $describer): void
    {
        $this->visionError = null;
        $file = $this->validateVisionUpload();

        try {
            $result = $vision->detectWithSettings([], $file, $this->visionLanguage);
        } catch (AssistiveServiceException $exception) {
            $this->visionError = __('assistive.errors.'.$exception->errorCode);

            return;
        } catch (Throwable) {
            $this->visionError = __('assistive.errors.provider_unavailable');

            return;
        }

        $this->visionObjects = $result['objects'];
        $this->visionMessage = $result['message'];
        $this->visionSpeech = $describer->describe($result['objects'], $this->visionLanguage);
    }

    public function extractText(OCRService $ocr): void
    {
        $this->ocrError = null;
        $file = $this->validateOcrUpload();

        try {
            $result = $ocr->read($file, $this->ocrLanguage);
        } catch (AssistiveServiceException $exception) {
            $this->ocrText = null;
            $this->ocrDetectedLanguage = null;
            $this->ocrError = __('assistive.errors.'.$exception->errorCode);

            return;
        } catch (Throwable) {
            $this->ocrText = null;
            $this->ocrDetectedLanguage = null;
            $this->ocrError = __('assistive.errors.provider_unavailable');

            return;
        }

        $this->ocrText = $result['text'];
        $this->ocrDetectedLanguage = $result['language'];
    }

    public function startSimulation(NavigationSimulatorService $simulator): void
    {
        $this->navigationError = null;
        $coordinates = $this->validateNavigationCoordinates();
        $device = $this->authorizeDevice($coordinates['selectedDeviceId']);

        if ($this->simulation !== null && ($this->simulation['status'] ?? null) === NavigationStatus::Active->value) {
            $this->navigationError = __('assistive.errors.navigation_already_active');

            return;
        }

        if (! in_array($this->navigationLanguage, ['en', 'ar'], true)) {
            $this->navigationLanguage = 'en';
        }

        try {
            $state = $simulator->start(
                (float) $coordinates['originLatitude'],
                (float) $coordinates['originLongitude'],
                (float) $coordinates['destinationLatitude'],
                (float) $coordinates['destinationLongitude'],
                $this->navigationLanguage,
            );
        } catch (AssistiveServiceException $exception) {
            $this->navigationError = __('assistive.errors.'.$exception->errorCode);

            return;
        } catch (Throwable) {
            $this->navigationError = __('assistive.errors.provider_unavailable');

            return;
        }

        $state['device_id'] = $device->id;
        $state['device_label'] = $device->serial_number.($device->blindUser !== null ? ' — '.$device->blindUser->name : '');
        $state['production_session_active'] = $device->navigationSessions()
            ->where('status', NavigationStatus::Active)->exists();
        $this->simulation = $state;
        $this->currentLatitude = $this->originLatitude;
        $this->currentLongitude = $this->originLongitude;

        $instruction = $this->simulation['current_instruction'] ?? null;
        if (is_string($instruction) && $instruction !== '') {
            $this->dispatchSpeech($instruction, (string) $this->simulation['language']);
        }
    }

    public function updateSimulatedGps(NavigationSimulatorService $simulator): void
    {
        $this->navigationError = null;
        if ($this->simulation === null || ($this->simulation['status'] ?? null) !== NavigationStatus::Active->value) {
            $this->navigationError = __('assistive.errors.navigation_not_active');

            return;
        }

        $this->authorizeSimulatedDevice();

        $validated = validator([
            'currentLatitude' => $this->currentLatitude,
            'currentLongitude' => $this->currentLongitude,
        ], [
            'currentLatitude' => ['required', 'numeric', 'between:-90,90'],
            'currentLongitude' => ['required', 'numeric', 'between:-180,180'],
        ])->validate();

        $lastSpoken = $this->simulation['last_spoken_instruction'] ?? null;
        $language = (string) $this->simulation['language'];

        $this->simulation = $simulator->updateGps(
            $this->simulation,
            (float) $validated['currentLatitude'],
            (float) $validated['currentLongitude'],
        );

        $instruction = $this->simulation['current_instruction'] ?? null;
        $completed = $this->simulation['status'] === NavigationStatus::Completed->value;

        if ($completed) {
            $this->dispatchSpeech(__('filament.simulator.messages.arrived', [], $language), $language);
        } elseif (is_string($instruction) && $instruction !== '' && $instruction !== $lastSpoken) {
            $this->dispatchSpeech($instruction, $language);
        }
    }

    public function stopSimulation(NavigationSimulatorService $simulator): void
    {
        $this->navigationError = null;
        if ($this->simulation === null) {
            return;
        }

        $this->authorizeSimulatedDevice();
        $this->simulation = $simulator->stop($this->simulation);
        $this->dispatch('basira-stop-speech');
    }

    private function validateVisionUpload(): UploadedFile
    {
        $validated = validator(['visionImage' => $this->visionImage], [
            'visionImage' => [
                'required', 'file', 'image', 'mimes:jpeg,png,webp',
                'max:'.config('services.vision.max_image_kb'),
                'dimensions:max_width=8192,max_height=8192',
            ],
        ])->validate();

        /** @var UploadedFile $file */
        $file = $validated['visionImage'];

        return $file;
    }

    private function validateOcrUpload(): UploadedFile
    {
        if (! in_array($this->ocrLanguage, ['en', 'ar'], true)) {
            $this->ocrLanguage = 'en';
        }

        $validated = validator(['ocrImage' => $this->ocrImage], [
            'ocrImage' => [
                'required', 'file', 'image', 'mimes:jpeg,png,webp',
                'max:'.config('services.ocr.max_image_kb'),
                'dimensions:max_width=8192,max_height=8192',
            ],
        ])->validate();

        /** @var UploadedFile $file */
        $file = $validated['ocrImage'];

        return $file;
    }

    /** @return array{selectedDeviceId: int, originLatitude: float, originLongitude: float, destinationLatitude: float, destinationLongitude: float} */
    private function validateNavigationCoordinates(): array
    {
        $validated = validator([
            'selectedDeviceId' => $this->selectedDeviceId,
            'originLatitude' => $this->originLatitude,
            'originLongitude' => $this->originLongitude,
            'destinationLatitude' => $this->destinationLatitude,
            'destinationLongitude' => $this->destinationLongitude,
        ], [
            'selectedDeviceId' => ['required', 'integer'],
            'originLatitude' => ['required', 'numeric', 'between:-90,90'],
            'originLongitude' => ['required', 'numeric', 'between:-180,180'],
            'destinationLatitude' => ['required', 'numeric', 'between:-90,90'],
            'destinationLongitude' => ['required', 'numeric', 'between:-180,180'],
        ])->validate();

        return [
            'selectedDeviceId' => (int) $validated['selectedDeviceId'],
            'originLatitude' => (float) $validated['originLatitude'],
            'originLongitude' => (float) $validated['originLongitude'],
            'destinationLatitude' => (float) $validated['destinationLatitude'],
            'destinationLongitude' => (float) $validated['destinationLongitude'],
        ];
    }

    private function authorizeDevice(int $deviceId): Device
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);

        $device = Device::query()
            ->visibleTo($user, FamilyPermission::ViewLocation)
            ->find($deviceId);

        if ($device === null || ! Gate::forUser($user)->allows('view', $device)) {
            throw ValidationException::withMessages([
                'selectedDeviceId' => __('filament.simulator.messages.device_not_allowed'),
            ]);
        }

        return $device->load('blindUser');
    }

    private function authorizeSimulatedDevice(): Device
    {
        $deviceId = (int) ($this->simulation['device_id'] ?? 0);

        return $this->authorizeDevice($deviceId);
    }

    private function dispatchSpeech(string $text, string $language): void
    {
        if ($this->simulation !== null) {
            $this->simulation['spoken_count'] = (int) ($this->simulation['spoken_count'] ?? 0) + 1;
            $this->simulation['last_spoken_instruction'] = $text;
        }

        $this->dispatch('basira-speak', text: $text, lang: $language);
    }
}
