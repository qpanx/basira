<?php

namespace App\Filament\Resources;

use App\Enums\Language;
use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Models\Ngo;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function getNavigationLabel(): string
    {
        return __('filament.users.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('filament.users.singular');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.administration');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label(__('filament.fields.name'))->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->label(__('filament.fields.email'))->email()->required()->maxLength(255)->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('phone')->label(__('filament.fields.phone'))->tel()->maxLength(32),
            Forms\Components\Select::make('role')
                ->label(__('filament.fields.role'))
                ->options(function (): array {
                    $actor = Filament::auth()->user();
                    $roles = $actor instanceof User && $actor->isSuperAdmin()
                        ? UserRole::cases()
                        : [UserRole::Family];

                    return collect($roles)->mapWithKeys(fn (UserRole $role): array => [$role->value => __('filament.roles.'.$role->value)])->all();
                })
                ->required(),
            Forms\Components\Select::make('ngo_id')
                ->label(__('filament.fields.ngo'))
                ->options(fn (): array => Ngo::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->visible(fn (): bool => (Filament::auth()->user()?->isSuperAdmin() ?? false)),
            Forms\Components\Select::make('preferred_language')
                ->label(__('filament.fields.language'))
                ->options(collect(Language::cases())->mapWithKeys(fn (Language $language): array => [$language->value => $language->value])->all())
                ->default(Language::English->value)
                ->required(),
            Forms\Components\TextInput::make('password')
                ->label(__('filament.fields.password'))
                ->password()
                ->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state)),
            Forms\Components\Toggle::make('is_active')->label(__('filament.fields.active'))->default(true),
            Forms\Components\DateTimePicker::make('email_verified_at')->label(__('filament.fields.email_verified_at')),
            Forms\Components\DateTimePicker::make('phone_verified_at')->label(__('filament.fields.phone_verified_at')),
            Forms\Components\DateTimePicker::make('sms_opt_in_at')->label(__('filament.fields.sms_opt_in_at')),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('filament.fields.name'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->label(__('filament.fields.email'))->searchable(),
                Tables\Columns\TextColumn::make('phone')->label(__('filament.fields.phone')),
                Tables\Columns\TextColumn::make('role')->label(__('filament.fields.role'))->badge()->formatStateUsing(fn (UserRole|string|null $state): string => __('filament.roles.'.($state instanceof UserRole ? $state->value : (string) $state))),
                Tables\Columns\TextColumn::make('ngo.name')->label(__('filament.fields.ngo')),
                Tables\Columns\IconColumn::make('is_active')->label(__('filament.fields.active'))->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')->options(collect(UserRole::cases())->mapWithKeys(fn (UserRole $role): array => [$role->value => __('filament.roles.'.$role->value)])->all()),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('filament.fields.active')),
            ])
            ->actions([Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getEloquentQuery(): Builder
    {
        $actor = Filament::auth()->user();

        return parent::getEloquentQuery()->when(
            $actor instanceof User,
            fn (Builder $query): Builder => $query->visibleTo($actor),
            fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
