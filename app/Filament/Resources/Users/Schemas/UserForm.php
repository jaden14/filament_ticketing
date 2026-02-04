<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\CheckboxList;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Schema;
use App\Models\Office;
use App\Models\Ethnicity;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->columnSpanFull()
                    ->schema([

                        // LEFT COLUMN (Checkboxes)
                        Grid::make(1)
                            ->schema([
                                Select::make('office_id')
                                    ->label('Office')
                                    ->options(Office::query()->pluck('officename', 'id'))
                                    ->searchable()
                                    ->required(),
                            ]),

                        // RIGHT COLUMN (Inputs)
                        Grid::make(2)
                            ->schema([
                                TextInput::make('username')
                                    ->required(),
                                TextInput::make('password')
                                    ->password()
                                    ->revealable()
                                    ->default("ddogold")
                                    ->password()
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->hidden(fn (string $operation): bool => $operation === 'edit')
                                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                                    ->required(),
                            ]),
                    ]),
                 Grid::make(2)
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('lastname')
                                    ->required(),
                                TextInput::make('firstname')
                                    ->required(),
                                TextInput::make('middlename'),
                            ]),
                         Grid::make(2)
                            ->schema([
                                Toggle::make('status')
                                    ->onIcon(Heroicon::Bolt)
                                    ->offIcon(Heroicon::User)
                                    ->onColor('success')
                                    ->offColor('danger')
                                    ->inline(false)
                                    ->helperText('Toggle to activate or deactivate user account')
                                    ->required(),
                                Select::make('ethnicity_id')
                                    ->label('Ethnicity')
                                    ->options(Ethnicity::query()->pluck('ethnicity', 'id'))
                                    ->searchable(),
                            ]),
                    ]),
                Grid::make(2)
                    ->schema([
                        TextInput::make('cats')
                            ->unique()
                            ->required(),
                        Select::make('gender')
                            ->options([
                                'male' => 'male',
                                'female' => 'female',
                            ])
                            ->required()
                    ]),
                TextInput::make('position'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('telno')
                    ->label('Office Number'),
                Hidden::make('user_type')
                    ->default("client"),
                CheckboxList::make('roles')
                    ->relationship('roles', 'name')
                    ->searchable(),
                
            ]);
    }
}
