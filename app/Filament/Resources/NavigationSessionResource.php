<?php

namespace App\Filament\Resources;

use App\Enums\NavigationStatus;
use App\Filament\Resources\NavigationSessionResource\Pages;
use App\Models\NavigationSession;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NavigationSessionResource extends Resource
{
    protected static ?string $model = NavigationSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    public static function getNavigationLabel(): string
    {
        return __('assistive.navigation_history');
    }

    public static function getModelLabel(): string
    {
        return __('assistive.navigation_session');
    }

    public static function getPluralModelLabel(): string
    {
        return __('assistive.navigation_history');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.smart_cane');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('started_at', 'desc')->poll('30s')
            ->description(__('assistive.navigation_notice'))
            ->columns([
                Tables\Columns\TextColumn::make('device.serial_number')->label(__('filament.fields.device'))->searchable(),
                Tables\Columns\TextColumn::make('blindUser.name')->label(__('filament.fields.blind_user'))->searchable(),
                Tables\Columns\TextColumn::make('destination.name')->label(__('assistive.destination'))->wrap(),
                Tables\Columns\TextColumn::make('routing_provider')->label(__('assistive.routing_provider'))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'google' => 'Google Maps',
                        'osrm' => 'OSRM (OpenStreetMap)',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('originLocation.lat')->label(__('assistive.origin_latitude'))->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('originLocation.lng')->label(__('assistive.origin_longitude'))->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')->label(__('filament.fields.status'))->badge()
                    ->formatStateUsing(fn (NavigationStatus $state): string => __('assistive.statuses.'.$state->value)),
                Tables\Columns\IconColumn::make('off_route')->label(__('assistive.off_route'))->boolean(),
                Tables\Columns\TextColumn::make('distance_meters')->label(__('assistive.distance_meters')),
                Tables\Columns\TextColumn::make('estimated_duration_seconds')->label(__('assistive.duration_seconds')),
                Tables\Columns\TextColumn::make('started_at')->label(__('assistive.started_at'))->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('completed_at')->label(__('assistive.completed_at'))->dateTime(),
                Tables\Columns\TextColumn::make('cancelled_at')->label(__('assistive.cancelled_at'))->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label(__('filament.fields.status'))
                    ->options(collect(NavigationStatus::cases())->mapWithKeys(fn (NavigationStatus $status): array => [$status->value => __('assistive.statuses.'.$status->value)])->all()),
            ])
            ->actions([])->bulkActions([]);
    }

    /** @return Builder<NavigationSession> */
    public static function getEloquentQuery(): Builder
    {
        $actor = Filament::auth()->user();
        $query = NavigationSession::query()->with(['device', 'blindUser', 'originLocation']);

        return $actor instanceof User ? $query->accessibleTo($actor) : $query->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNavigationSessions::route('/')];
    }
}
