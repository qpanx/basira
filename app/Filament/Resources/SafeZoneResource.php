<?php

namespace App\Filament\Resources;

use App\Enums\FamilyPermission;
use App\Filament\Resources\SafeZoneResource\Pages;
use App\Forms\Components\SafeZoneMapPicker;
use App\Models\BlindUser;
use App\Models\SafeZone;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SafeZoneResource extends Resource
{
    protected static ?string $model = SafeZone::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    public static function getNavigationLabel(): string
    {
        return __('filament.safe_zones.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('filament.safe_zones.singular');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.smart_cane');
    }

    public static function canCreate(): bool
    {
        $actor = Filament::auth()->user();

        return $actor instanceof User
            && ($actor->isSuperAdmin()
                || $actor->isNgoStaff()
                || ($actor->isFamily() && BlindUser::query()->accessibleTo($actor, FamilyPermission::ManageSafeZones)->exists()));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('blind_user_id')
                ->label(__('filament.fields.blind_user'))
                ->options(function (): array {
                    $actor = Filament::auth()->user();
                    $query = BlindUser::query();

                    if ($actor instanceof User && $actor->isFamily()) {
                        $query->accessibleTo($actor, FamilyPermission::ManageSafeZones);
                    } elseif ($actor instanceof User) {
                        $query->accessibleTo($actor);
                    } else {
                        return [];
                    }

                    return $query->orderBy('name')->pluck('name', 'id')->all();
                })
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('name')
                ->label(__('filament.fields.name'))
                ->required()
                ->maxLength(255),
            SafeZoneMapPicker::make('map')
                ->label(__('filament.fields.map'))
                ->dehydrated(false)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('center_lat')
                ->label(__('filament.fields.center_lat'))
                ->numeric()
                ->minValue(-90)
                ->maxValue(90)
                ->required(),
            Forms\Components\TextInput::make('center_lng')
                ->label(__('filament.fields.center_lng'))
                ->numeric()
                ->minValue(-180)
                ->maxValue(180)
                ->required(),
            Forms\Components\TextInput::make('radius_meters')
                ->label(__('filament.fields.radius_meters'))
                ->numeric()
                ->minValue(1)
                ->maxValue(100000)
                ->required(),
            Forms\Components\Toggle::make('is_active')
                ->label(__('filament.fields.active'))
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('filament.fields.name'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('blindUser.name')->label(__('filament.fields.blind_user'))->sortable(),
                Tables\Columns\TextColumn::make('radius_meters')->label(__('filament.fields.radius_meters'))->suffix(' m')->sortable(),
                Tables\Columns\TextColumn::make('center_lat')->label(__('filament.fields.center_lat')),
                Tables\Columns\TextColumn::make('center_lng')->label(__('filament.fields.center_lng')),
                Tables\Columns\IconColumn::make('is_active')->label(__('filament.fields.active'))->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label(__('filament.fields.active')),
            ])
            ->actions([
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
            'index' => Pages\ListSafeZones::route('/'),
            'create' => Pages\CreateSafeZone::route('/create'),
            'view' => Pages\ViewSafeZone::route('/{record}'),
            'edit' => Pages\EditSafeZone::route('/{record}/edit'),
        ];
    }
}
