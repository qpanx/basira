<?php

namespace App\Filament\Resources\BlindUserResource\RelationManagers;

use App\Enums\FamilyPermission;
use App\Models\FamilyBlindUser;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FamilyMembersRelationManager extends RelationManager
{
    protected static string $relationship = 'familyMembers';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('pivot.relationship')->label(__('filament.fields.relationship')),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->visible(fn (): bool => ! (Filament::auth()->user()?->isFamily() ?? false))
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\TextInput::make('relationship')
                            ->label(__('filament.fields.relationship'))
                            ->default('caregiver')
                            ->required()
                            ->maxLength(64),
                        Forms\Components\Toggle::make(FamilyPermission::ViewLocation->value)
                            ->label(__('filament.permissions.can_view_location'))
                            ->default(true),
                        Forms\Components\Toggle::make(FamilyPermission::ReceiveAlerts->value)
                            ->label(__('filament.permissions.can_receive_alerts'))
                            ->default(true),
                        Forms\Components\Toggle::make(FamilyPermission::ManageSafeZones->value)
                            ->label(__('filament.permissions.can_manage_safe_zones'))
                            ->default(false),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        return [
                            ...$data,
                            'permissions' => [
                                FamilyPermission::ViewLocation->value => (bool) ($data[FamilyPermission::ViewLocation->value] ?? true),
                                FamilyPermission::ReceiveAlerts->value => (bool) ($data[FamilyPermission::ReceiveAlerts->value] ?? true),
                                FamilyPermission::ManageSafeZones->value => (bool) ($data[FamilyPermission::ManageSafeZones->value] ?? false),
                            ],
                        ];
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_membership')
                    ->label(__('filament.actions.edit_membership'))
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => ! (Filament::auth()->user()?->isFamily() ?? false))
                    ->form([
                        Forms\Components\TextInput::make('relationship')
                            ->label(__('filament.fields.relationship'))
                            ->required()
                            ->maxLength(64),
                        Forms\Components\Toggle::make(FamilyPermission::ViewLocation->value)
                            ->label(__('filament.permissions.can_view_location')),
                        Forms\Components\Toggle::make(FamilyPermission::ReceiveAlerts->value)
                            ->label(__('filament.permissions.can_receive_alerts')),
                        Forms\Components\Toggle::make(FamilyPermission::ManageSafeZones->value)
                            ->label(__('filament.permissions.can_manage_safe_zones')),
                    ])
                    ->fillForm(fn (User $record): array => [
                        'relationship' => $record->pivot?->relationship ?? 'caregiver',
                        FamilyPermission::ViewLocation->value => $record->pivot instanceof FamilyBlindUser
                            ? $record->pivot->allows(FamilyPermission::ViewLocation)
                            : true,
                        FamilyPermission::ReceiveAlerts->value => $record->pivot instanceof FamilyBlindUser
                            ? $record->pivot->allows(FamilyPermission::ReceiveAlerts)
                            : true,
                        FamilyPermission::ManageSafeZones->value => $record->pivot instanceof FamilyBlindUser
                            && $record->pivot->allows(FamilyPermission::ManageSafeZones),
                    ])
                    ->action(function (User $record, array $data): void {
                        if (! $record->pivot instanceof FamilyBlindUser) {
                            return;
                        }

                        $record->pivot->forceFill([
                            'relationship' => $data['relationship'],
                            'permissions' => [
                                FamilyPermission::ViewLocation->value => (bool) ($data[FamilyPermission::ViewLocation->value] ?? false),
                                FamilyPermission::ReceiveAlerts->value => (bool) ($data[FamilyPermission::ReceiveAlerts->value] ?? false),
                                FamilyPermission::ManageSafeZones->value => (bool) ($data[FamilyPermission::ManageSafeZones->value] ?? false),
                            ],
                        ])->save();
                    }),
                Tables\Actions\DetachAction::make()
                    ->visible(fn (): bool => ! (Filament::auth()->user()?->isFamily() ?? false)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->visible(fn (): bool => ! (Filament::auth()->user()?->isFamily() ?? false)),
                ]),
            ]);
    }
}
