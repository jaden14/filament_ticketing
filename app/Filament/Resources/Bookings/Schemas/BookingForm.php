<?php

namespace App\Filament\Resources\Bookings\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\HtmlString;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Schema;
use App\Models\Service;
use App\Models\Office;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('service_id')
                    ->label("Services")
                    ->options(Service::query()->where('category',1)->orderBy('service_type')->pluck('service_type', 'id'))
                    ->searchable()
                    ->required()
                    ->disabled(fn (Get $get): bool => $get('released') === true)
                    ->getOptionLabelUsing(fn ($value): ?string => Service::find($value)?->service_type),
                Select::make('office_id')
                    ->label("Offices")
                    ->options(Office::query()->orderBy('officename')->pluck('officename', 'id'))
                    ->searchable()
                    ->required()
                    ->disabled(fn (Get $get): bool => $get('released') === true),
                Grid::make(1)
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('purpose')
                            ->required()
                            ->disabled(fn (Get $get): bool => $get('released') === true),
                    ]),
                FileUpload::make('files')
                    ->columnSpanFull()
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(3024),

                DatePicker::make('booked_at')
                    ->label("Book On")
                    ->required()
                    ->reactive()
                    ->maxDate(fn (Get $get) => $get('returned_at'))
                    ->disabled(fn (Get $get): bool => $get('released') === true),
                DatePicker::make('returned_at')
                    ->label("Return On")
                    ->reactive()
                    ->minDate(fn (Get $get) => $get('booked_at'))
                    ->disabled(fn (Get $get): bool => $get('released') === true),

                Placeholder::make('ipcr_code_id')
                        ->label('IPCR Output')
                        ->content(new HtmlString('
                            <div class="bg-success-50 dark:bg-success-900/20 border border-success-200 dark:border-success-700 rounded-lg px-3 py-2 inline-block">
                                <span class="text-success-700 dark:text-success-400 font-semibold">✓ ADDED to IPCR</span>
                            </div>
                        '))
                        ->visible(function ($record): bool {

                                if ($record?->ipcr_code_id != null) {
                                    return true;
                                }
                                
                                return false;
                        })
                        ->columnSpanFull(),

                    Select::make('ipcr_code_id')
                            ->label('IPCR Output')
                            ->placeholder('Select IPCR Output')
                            ->searchable()
                            ->preload()
                            ->options(function () {
                                try {                
                                    $empCode = auth()->user()->cats;
                                    
                                    $response = \Illuminate\Support\Facades\Http::get(
                                        "https://ipcr.davaodeoro.gov.ph/ipcr-code",
                                        ['emp_code' => $empCode]
                                    );
                                    
                                    if (!$response->successful()) {
                                        return [];
                                    }
                                    
                                    $datas = $response->json();
                                    
                                    return collect($datas)->pluck('individual_output', 'id')->toArray();
                                    
                                } catch (\Exception $e) {
                                    \Illuminate\Support\Facades\Log::error('IPCR Code fetch failed: ' . $e->getMessage());
                                    return [];
                                }
                            })
                            ->helperText('Select the IPCR Output related to this work')
                            ->columnSpanFull()
                            ->visible(function ($record): bool {
                                if ($record?->ipcr_code_id == null) {
                                    return true;
                                }
                                return false;
                            }),

                Hidden::make('status')
                    ->default("Pending"),
                Hidden::make('released_at')
                    ->default(null),
                Toggle::make('released')
                    ->label('Mark as Released')
                    ->onIcon(Heroicon::Bolt)
                    ->offIcon(Heroicon::ArrowLongRight)
                    ->onColor('success')
                    ->offColor('danger')
                    ->reactive()
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Toggle $component, $state, $record) {
                        $component->state($record?->status === 'Released');
                    })
                    ->visible(fn ($operation): bool => $operation === 'edit') 
                    ->afterStateUpdated(function ($state, Set $set) {
                        // When toggled on, set status and timestamp
                        if ($state) {
                            $set('status', 'Released');
                            $set('released_at', now());
                        } else {
                            $set('status', 'Pending');
                            $set('released_at', null);
                            // Also clear the release fields when toggled off
                            $set('release_to', null);
                            $set('release_by', null);
                        }
                    }),
                 Section::make('Release Information')
                    ->description('Fill this section when releasing the item')
                    ->schema([
                        TextInput::make('release_to')
                            ->label('Release To')
                            ->placeholder('Enter recipient name')
                            ->maxLength(255)
                            ->required(fn (Get $get): bool => $get('released') === true)
                            ->disabled(fn (Get $get): bool => $get('returned') === true),
                            
                        TextInput::make('release_by')
                            ->label('Release By')
                            ->placeholder('Enter releaser name')
                            ->maxLength(255)
                            ->required(fn (Get $get): bool => $get('released') === true)
                            ->disabled(fn (Get $get): bool => $get('returned') === true),
                    ])
                    ->visible(function (Get $get, $operation, $record): bool {
                        // Always show if status is 'Released' (for view mode)
                        if ($record?->status === 'Released' || $get('status') === 'Released') {
                            return true;
                        }
                        
                        // Show in edit mode when toggle is ON
                        if ($operation === 'edit' && ($get('released') === true || $record->status =='Returned')) {
                            return true;
                        }
                        
                        return false;
                    }),
                Toggle::make('returned')
                    ->label('Mark as Return')
                    ->onIcon(Heroicon::UserMinus)
                    ->offIcon(Heroicon::Bolt)
                    ->onColor('success')
                    ->offColor('danger')
                    ->reactive()
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Toggle $component, $state, $record) {
                        $component->state($record?->status === 'Returned');
                    })
                    ->visible(fn ($operation, $record): bool => $operation === 'edit' && ($record->status =='Released' || $record->status =='Returned')) 
                    ->afterStateUpdated(function ($state, Set $set) {
                        // When toggled on, set status and timestamp
                        if ($state) {
                            $set('status', 'Returned');
                        } else {
                            $set('status', 'Released');
                            $set('return_by', null);
                        }
                    }),
                Section::make('Return Information')
                    ->description('Fill this section when Returning the item')
                    ->schema([
                        TextInput::make('return_by')
                            ->label('Return By')
                            ->placeholder('Enter recipient name')
                            ->maxLength(255)
                            ->required(fn (Get $get): bool => $get('returned') === true),
                    ])
                    ->visible(function (Get $get, $operation, $record): bool {
                        // Always show if status is 'Released' (for view mode)
                        if ($record?->status === 'Returned' || $get('status') === 'Returned') {
                            return true;
                        }
                        
                        // Show in edit mode when toggle is ON
                        if ($operation === 'edit' && $get('returned') === true) {
                            return true;
                        }
                        
                        return false;
                    }),
            ]);
    }
}
