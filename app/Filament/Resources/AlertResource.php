<?php

namespace App\Filament\Resources;

use App\Actions\MarkAlertFalseAlarm;
use App\Actions\ResolveAlert;
use App\Enums\AlertResolution;
use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Filament\Resources\AlertResource\Pages;
use App\Models\Alert;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AlertResource extends Resource
{
    protected static ?string $model = Alert::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    public static function getNavigationLabel(): string
    {
        return __('filament.alerts.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('filament.alerts.singular');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.smart_cane');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('type')
                ->label(__('filament.fields.type'))
                ->options(collect(AlertType::cases())->mapWithKeys(fn (AlertType $type): array => [$type->value => __('filament.alert_types.'.$type->value)])->all())
                ->required(),
            Forms\Components\Select::make('severity')
                ->label(__('filament.fields.severity'))
                ->options(collect(AlertSeverity::cases())->mapWithKeys(fn (AlertSeverity $severity): array => [$severity->value => __('filament.severities.'.$severity->value)])->all())
                ->required(),
            Forms\Components\TextInput::make('distance_cm')
                ->label(__('filament.fields.distance_cm'))
                ->numeric()
                ->minValue(0)
                ->maxValue(65535),
            Forms\Components\TextInput::make('lat')->numeric()->minValue(-90)->maxValue(90),
            Forms\Components\TextInput::make('lng')->numeric()->minValue(-180)->maxValue(180),
            Forms\Components\DateTimePicker::make('occurred_at')->label(__('filament.fields.occurred_at')),
            Forms\Components\Select::make('resolution')
                ->label(__('filament.fields.resolution'))
                ->options(collect(AlertResolution::cases())->mapWithKeys(fn (AlertResolution $resolution): array => [$resolution->value => __('filament.resolutions.'.$resolution->value)])->all()),
            Forms\Components\Textarea::make('resolution_notes')->label(__('filament.fields.resolution_notes'))->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label(__('filament.fields.created_at'))->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('blindUser.name')->label(__('filament.fields.blind_user'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('device.serial_number')->label(__('filament.fields.device'))->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('filament.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (AlertType|string|null $state): string => __('filament.alert_types.'.($state instanceof AlertType ? $state->value : (string) $state))),
                Tables\Columns\TextColumn::make('severity')
                    ->label(__('filament.fields.severity'))
                    ->badge()
                    ->formatStateUsing(fn (AlertSeverity|string|null $state): string => __('filament.severities.'.($state instanceof AlertSeverity ? $state->value : (string) $state)))
                    ->color(fn (AlertSeverity|string|null $state): string => match ($state instanceof AlertSeverity ? $state : AlertSeverity::tryFrom((string) $state)) {
                        AlertSeverity::Critical => 'danger',
                        AlertSeverity::Warning => 'warning',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('distance_cm')->label(__('filament.fields.distance_cm'))->suffix(' cm'),
                Tables\Columns\TextColumn::make('resolution')
                    ->label(__('filament.fields.resolution'))
                    ->placeholder(__('filament.statuses.unresolved'))
                    ->formatStateUsing(fn (AlertResolution|string|null $state): string => $state instanceof AlertResolution ? __('filament.resolutions.'.$state->value) : __('filament.statuses.unresolved')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(collect(AlertType::cases())->mapWithKeys(fn (AlertType $type): array => [$type->value => __('filament.alert_types.'.$type->value)])->all()),
                Tables\Filters\SelectFilter::make('severity')->options(collect(AlertSeverity::cases())->mapWithKeys(fn (AlertSeverity $severity): array => [$severity->value => __('filament.severities.'.$severity->value)])->all()),
                Tables\Filters\SelectFilter::make('resolution')->options(collect(AlertResolution::cases())->mapWithKeys(fn (AlertResolution $resolution): array => [$resolution->value => __('filament.resolutions.'.$resolution->value)])->all()),
                Tables\Filters\TernaryFilter::make('resolved_at')->label(__('filament.filters.resolved'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('resolved_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('resolved_at'),
                    ),
                Tables\Filters\Filter::make('created_at')->form([
                    Forms\Components\DatePicker::make('from'),
                    Forms\Components\DatePicker::make('until'),
                ])->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date));
                }),
            ])
            ->actions([
                Tables\Actions\Action::make('resolve')
                    ->label(__('filament.actions.resolve'))
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Alert $record): bool => ! $record->isResolved())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('filament.fields.notes'))
                            ->maxLength(5000),
                    ])
                    ->action(function (Alert $record, array $data): void {
                        $actor = Filament::auth()->user();
                        if ($actor instanceof User) {
                            app(ResolveAlert::class)->handle($record, $actor, $data['notes'] ?? null);
                        }
                    }),
                Tables\Actions\Action::make('false_alarm')
                    ->label(__('filament.actions.false_alarm'))
                    ->icon('heroicon-o-flag')
                    ->visible(fn (Alert $record): bool => ! $record->isResolved() && (Filament::auth()->user()?->isFamily() ?? false))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('filament.fields.notes'))
                            ->maxLength(5000),
                    ])
                    ->action(function (Alert $record, array $data): void {
                        $actor = Filament::auth()->user();
                        if ($actor instanceof User) {
                            app(MarkAlertFalseAlarm::class)->handle($record, $actor, $data['notes'] ?? null);
                        }
                    }),
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListAlerts::route('/'),
            'create' => Pages\CreateAlert::route('/create'),
            'view' => Pages\ViewAlert::route('/{record}'),
            'edit' => Pages\EditAlert::route('/{record}/edit'),
        ];
    }
}
