<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlindUserResource\Pages;
use App\Filament\Resources\BlindUserResource\RelationManagers\DevicesRelationManager;
use App\Filament\Resources\BlindUserResource\RelationManagers\FamilyMembersRelationManager;
use App\Models\BlindUser;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BlindUserResource extends Resource
{
    protected static ?string $model = BlindUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-eye-slash';

    public static function getNavigationLabel(): string
    {
        return __('filament.blind_users.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('filament.blind_users.singular');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.smart_cane');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label(__('filament.fields.name'))
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('phone')
                ->label(__('filament.fields.phone'))
                ->tel()
                ->maxLength(32),
            Forms\Components\Select::make('ngo_id')
                ->label(__('filament.fields.ngo'))
                ->relationship('ngo', 'name')
                ->searchable()
                ->preload()
                ->visible(fn (): bool => Filament::auth()->user()?->isSuperAdmin() ?? false),
            Forms\Components\Textarea::make('emergency_notes')
                ->label(__('filament.fields.emergency_notes'))
                ->rows(4)
                ->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')
                ->label(__('filament.fields.active'))
                ->default(true),
            Forms\Components\Hidden::make('created_by')
                ->default(fn (): ?int => Filament::auth()->id()),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament.fields.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('filament.fields.phone'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('ngo.name')
                    ->label(__('filament.fields.ngo'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('devices_count')
                    ->counts('devices')
                    ->label(__('filament.fields.devices')),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('filament.fields.active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label(__('filament.fields.active')),
                Tables\Filters\SelectFilter::make('ngo_id')
                    ->label(__('filament.fields.ngo'))
                    ->relationship('ngo', 'name'),
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

    public static function getRelations(): array
    {
        return [DevicesRelationManager::class, FamilyMembersRelationManager::class];
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
            'index' => Pages\ListBlindUsers::route('/'),
            'create' => Pages\CreateBlindUser::route('/create'),
            'view' => Pages\ViewBlindUser::route('/{record}'),
            'edit' => Pages\EditBlindUser::route('/{record}/edit'),
        ];
    }
}
