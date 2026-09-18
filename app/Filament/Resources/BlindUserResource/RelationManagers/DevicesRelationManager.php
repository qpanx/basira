<?php

namespace App\Filament\Resources\BlindUserResource\RelationManagers;

use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DevicesRelationManager extends RelationManager
{
    protected static string $relationship = 'devices';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('serial_number')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('serial_number')
            ->columns([
                Tables\Columns\TextColumn::make('serial_number')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('battery_level')->suffix('%'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn (): bool => ! (Filament::auth()->user()?->isFamily() ?? false)),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => ! (Filament::auth()->user()?->isFamily() ?? false)),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => Filament::auth()->user()?->isSuperAdmin() ?? false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => Filament::auth()->user()?->isSuperAdmin() ?? false),
                ]),
            ]);
    }
}
