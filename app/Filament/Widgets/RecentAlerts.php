<?php

namespace App\Filament\Widgets;

use App\Models\Alert;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentAlerts extends BaseWidget
{
    protected static ?string $pollingInterval = '15s';

    public function table(Table $table): Table
    {
        $actor = Filament::auth()->user();

        return $table
            ->heading(__('filament.widgets.recent_alerts'))
            ->query($actor instanceof User
                ? Alert::query()->visibleTo($actor)->with(['blindUser', 'device'])->latest()
                : Alert::query()->whereRaw('1 = 0'))
            ->poll('15s')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label(__('filament.fields.created_at'))->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('blindUser.name')->label(__('filament.fields.blind_user')),
                Tables\Columns\TextColumn::make('type')->label(__('filament.fields.type'))->badge(),
                Tables\Columns\TextColumn::make('severity')->label(__('filament.fields.severity'))->badge(),
                Tables\Columns\IconColumn::make('resolved')
                    ->label(__('filament.filters.resolved'))
                    ->state(fn (Alert $record): bool => $record->resolved_at !== null)
                    ->boolean(),
            ]);
    }
}
