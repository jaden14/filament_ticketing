<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use STS\FilamentImpersonate\Actions\Impersonate;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('office.officename')
                    ->searchable()
                    ->sortable(),
               TextColumn::make('full_name')
                    ->label('Full Name')
                    ->searchable(['firstname', 'middlename', 'lastname'])
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->orderBy('lastname', $direction)
                            ->orderBy('firstname', $direction)
                            ->orderBy('middlename', $direction);
                    }),
                TextColumn::make('username')
                    ->searchable(),
                TextColumn::make('position')
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(','),
                TextColumn::make('cats')
                    ->searchable(),
                ToggleColumn::make('status'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Impersonate::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
