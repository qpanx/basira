<?php

namespace App\Forms\Components;

use Filament\Forms\Components\Field;

class SafeZoneMapPicker extends Field
{
    protected string $view = 'forms.components.safe-zone-map-picker';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([
            'lat' => 30.0444,
            'lng' => 31.2357,
            'radius' => 200,
        ]);
    }
}
