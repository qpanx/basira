<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NgoResource\Pages;
use App\Models\Ngo;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NgoResource extends Resource
{
    protected static ?string $model = Ngo::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    public static function shouldRegisterNavigation(): bool
    {
        return Filament::auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.ngos.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('filament.ngos.singular');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.administration');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label(__('filament.fields.name'))->required()->maxLength(255),
            Forms\Components\TextInput::make('contact_name')->label(__('filament.fields.contact_name'))->maxLength(255),
            Forms\Components\TextInput::make('contact_email')->label(__('filament.fields.contact_email'))->email()->maxLength(255),
            Forms\Components\TextInput::make('contact_phone')->label(__('filament.fields.contact_phone'))->tel()->maxLength(32),
            Forms\Components\Textarea::make('address')->label(__('filament.fields.address'))->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')->label(__('filament.fields.active'))->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('filament.fields.name'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('contact_name')->label(__('filament.fields.contact_name')),
                Tables\Columns\TextColumn::make('contact_email')->label(__('filament.fields.contact_email')),
                Tables\Columns\TextColumn::make('contact_phone')->label(__('filament.fields.contact_phone')),
                Tables\Columns\IconColumn::make('is_active')->label(__('filament.fields.active'))->boolean(),
                Tables\Columns\TextColumn::make('blind_users_count')->counts('blindUsers')->label(__('filament.fields.blind_users')),
            ])
            ->filters([Tables\Filters\TernaryFilter::make('is_active')->label(__('filament.fields.active'))])
            ->actions([Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNgos::route('/'),
            'create' => Pages\CreateNgo::route('/create'),
            'view' => Pages\ViewNgo::route('/{record}'),
            'edit' => Pages\EditNgo::route('/{record}/edit'),
        ];
    }
}
