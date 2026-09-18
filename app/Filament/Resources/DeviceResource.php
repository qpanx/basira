<?php

namespace App\Filament\Resources;

use App\Enums\DeviceStatus;
use App\Enums\Language;
use App\Filament\Resources\DeviceResource\Pages;
use App\Models\BlindUser;
use App\Models\Device;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    public static function getNavigationLabel(): string
    {
        return __('filament.devices.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('filament.devices.singular');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.smart_cane');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('blind_user_id')
                ->label(__('filament.fields.blind_user'))
                ->options(fn (): array => BlindUser::query()
                    ->accessibleTo(Filament::auth()->user())
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->required()
                ->visible(fn (): bool => ! (Filament::auth()->user()?->isFamily() ?? true)),
            Forms\Components\TextInput::make('serial_number')
                ->label(__('filament.fields.serial_number'))
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('firmware_version')
                ->label(__('filament.fields.firmware_version'))
                ->maxLength(50),
            Forms\Components\TextInput::make('battery_level')
                ->label(__('filament.fields.battery_level'))
                ->numeric()
                ->minValue(0)
                ->maxValue(100),
            Forms\Components\Select::make('preferred_language')
                ->label(__('filament.fields.language'))
                ->options(collect(Language::cases())->mapWithKeys(fn (Language $language): array => [$language->value => $language->value])->all())
                ->default(Language::English->value)
                ->required(),
            Forms\Components\Select::make('status')
                ->label(__('filament.fields.status'))
                ->options(collect(DeviceStatus::cases())->mapWithKeys(fn (DeviceStatus $status): array => [$status->value => __('filament.statuses.'.$status->value)])->all())
                ->default(DeviceStatus::Offline->value)
                ->required(),
            Forms\Components\Toggle::make('is_enabled')
                ->label(__('filament.fields.enabled'))
                ->default(true),
            Forms\Components\TextInput::make('configuration_version')
                ->label(__('filament.fields.configuration_version'))
                ->numeric()
                ->minValue(1)
                ->default(1)
                ->required(),
            Forms\Components\KeyValue::make('settings')
                ->label(__('filament.fields.settings'))
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('serial_number')
                    ->label(__('filament.fields.serial_number'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('blindUser.name')
                    ->label(__('filament.fields.blind_user'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('firmware_version')
                    ->label(__('filament.fields.firmware_version')),
                Tables\Columns\TextColumn::make('battery_level')
                    ->label(__('filament.fields.battery_level'))
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('filament.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (DeviceStatus|string|null $state): string => $state instanceof DeviceStatus ? __('filament.statuses.'.$state->value) : __('filament.statuses.'.(string) $state))
                    ->color(fn (DeviceStatus|string|null $state): string => match ($state instanceof DeviceStatus ? $state : DeviceStatus::tryFrom((string) $state)) {
                        DeviceStatus::Active => 'success',
                        DeviceStatus::Maintenance => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->label(__('filament.fields.enabled'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label(__('filament.fields.last_seen'))
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(DeviceStatus::cases())->mapWithKeys(fn (DeviceStatus $status): array => [$status->value => __('filament.statuses.'.$status->value)])->all()),
                Tables\Filters\TernaryFilter::make('is_enabled')->label(__('filament.fields.enabled')),
            ])
            ->actions([
                Tables\Actions\Action::make('live_map')
                    ->label(__('filament.actions.live_map'))
                    ->icon('heroicon-o-map')
                    ->url(fn (Device $record): string => static::getUrl('live-map', ['record' => $record])),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $actor = Filament::auth()->user();

        return parent::getEloquentQuery()->when(
            $actor instanceof User,
            fn (Builder $query): Builder => $query->accessibleTo($actor),
            fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevices::route('/'),
            'create' => Pages\CreateDevice::route('/create'),
            'view' => Pages\ViewDevice::route('/{record}'),
            'edit' => Pages\EditDevice::route('/{record}/edit'),
            'live-map' => Pages\LiveMap::route('/{record}/live-map'),
        ];
    }
}
