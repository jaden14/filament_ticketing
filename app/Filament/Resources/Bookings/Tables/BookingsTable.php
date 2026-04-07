<?php

namespace App\Filament\Resources\Bookings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('service.service_type')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('office.officename')
                    ->searchable(),
                TextColumn::make('purpose')
                    ->searchable(),
                TextColumn::make('booked_at')
                    ->label("Book On")
                    ->date()
                    ->sortable(),
                TextColumn::make('released_at')
                    ->label("Release On")
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('returned_at')
                    ->label("Return On")
                    ->date()
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record): ?string => 
                        $record?->status === 'Returned' 
                            ? $state?->format('M d, Y') // Format as date
                            : null // Show empty when not returned
                    ),
                TextColumn::make('status')
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
               /* EditAction::make(),*/
            ])
            ->toolbarActions([
               /* BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),*/
            ]);
    }
}
