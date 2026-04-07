<?php

namespace App\Filament\Resources\Requests\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Hidden;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Models\Request;
use App\Models\Service;
use App\Models\Office;
use App\Models\User;

class RequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                    Select::make('category_id')
                            ->label('Category')
                            ->options(Service::query()
                                ->where('category',0)
                                ->select('classification_code', 'classification')
                                ->distinct()
                                ->orderBy('classification')
                                ->pluck('classification', 'classification_code'))
                            ->live() // Add live() to trigger updates
                            ->afterStateUpdated(fn ($state, $set) => $set('service_id', null))
                            ->required(),

                    Select::make('service_id')
                            ->label('Services')
                            ->searchable()
                            ->options(function (Get $get): array {
                                $classificationCode = $get('category_id');
                                
                                if (!$classificationCode) {
                                    return []; // Return empty array if no classification selected
                                }
                                
                                return Service::query()
                                    ->where('classification_code', $classificationCode)
                                    ->where('category', 0)
                                    ->orderBy('service_type')
                                    ->pluck('service_type', 'id')
                                    ->toArray();
                            })
                            ->disabled(fn (Get $get): bool => !$get('category_id'))
                            ->required(),

                    Select::make('office_id')
                        ->label("Offices")
                        ->options(Office::query()->orderBy('officename')->pluck('officename', 'id'))
                        ->searchable()
                        ->required()
                        ->live() // Add live() to trigger updates
                        ->afterStateUpdated(fn ($state, $set) => $set('name', null)),

                    Grid::make(2)
                        ->schema([
                            TextInput::make('cats_no')
                                ->label('Cats #')
                                ->required(),
                            TextInput::make('name')
                                ->label('Requesting Personnel')
                                ->required(),
                        ]),

                    Textarea::make('remarks')
                        ->label('Problem/Issue')
                        ->required(),

                    Grid::make(2)
                        ->schema([
                            TextInput::make('no_of_affected')
                                ->label('No. of Affected User/s')
                                ->required(),
                            Select::make('prio')
                            ->options([
                                'p1' => 'P1 = 4 hours',
                                'p2' => 'P2 = 8 hours',
                                'p3' => 'P3 = as agreed with End User'
                            ])
                            ->live()
                            ->required(),

                            DateTimePicker::make('p3_agreed')
                                ->label('Date and Time Agreed')
                                ->columnSpanFull()
                                ->visible(fn (Get $get): bool => $get('prio') === 'p3') // Show only when prio is p3
                                ->required(fn (Get $get): bool => $get('prio') === 'p3'), // Required only when prio is p3
                        ]),

                    Toggle::make('checked')
                        ->label('(Check for adding control No. or Details)')
                        ->columnSpanFull()
                        ->onIcon(Heroicon::PlusCircle)
                        ->offIcon(Heroicon::ShieldExclamation)
                        ->onColor('success')
                        ->offColor('danger')
                        ->live()
                        ->dehydrated(false),

                    Section::make('Additional Input')
                        ->description('Fill this section')
                        ->schema([
                            TextInput::make('control_no')
                                ->label('Control No.')
                                ->maxLength(255) // Conditionally required
                                ->visible(fn (Get $get): bool => $get('checked')),
                            Textarea::make('details')
                                ->label('Details')
                                ->required(fn (Get $get): bool => $get('checked'))
                                ->visible(fn (Get $get): bool => $get('checked')),
                        ])
                        ->visible(fn (Get $get): bool => $get('checked')),

                    Hidden::make('status')
                        ->default("Pending"),
                     Hidden::make('users_id')
                        ->default(auth()->id()),
            ]);
    }
}
