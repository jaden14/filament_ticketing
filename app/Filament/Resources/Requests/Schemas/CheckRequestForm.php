<?php
namespace App\Filament\Resources\Requests\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Models\Request;
use App\Models\Checkrequest;
use Carbon\Carbon;
use Livewire\Component;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;

class CheckRequestForm
{
    public static function make(Request $request): array
    {
        return [
            Wizard::make([
                Step::make('Request Details')
                    ->schema([
                        Section::make('Client request information')
                            ->schema([
                                Placeholder::make('ticket_no')
                                    ->label('Ticket No.')
                                    ->content($request->id),
                                
                                Placeholder::make('office')
                                    ->label('Office')
                                    ->content($request->office?->officename ?? 'N/A'),
                                
                                Placeholder::make('category')
                                    ->label('Category')
                                    ->content($request->service?->classification ?? 'N/A'),
                                
                                Placeholder::make('service')
                                    ->label('Service')
                                    ->content($request->service?->service_type ?? 'N/A'),
                                
                                Placeholder::make('requesting_personnel')
                                    ->label('Requesting Personnel')
                                    ->content($request->requestor?->FullName ?? $request->name ?? 'N/A'),
                                
                                Placeholder::make('priority')
                                    ->label('Priority')
                                    ->content(match($request->prio) {
                                        'p1' => 'P1 - 4 hours',
                                        'p2' => 'P2 - 8 hours',
                                        'p3' => 'P3 - As agreed',
                                        default => 'N/A'
                                    }),
                                
                                Placeholder::make('date_requested')
                                    ->label('Date Requested')
                                    ->content($request->created_at?->format('M d, Y h:i A') ?? 'N/A'),
                                
                                Placeholder::make('no_affected')
                                    ->label('No. of Affected Users')
                                    ->content($request->no_of_affected ?? 'N/A'),
                                
                                Placeholder::make('problem_issue')
                                    ->label('Problem/Issue')
                                    ->content($request->remarks ?? 'N/A')
                                    ->columnSpanFull(),
                                
                                Placeholder::make('control_no')
                                    ->label('Control No.')
                                    ->content($request->control_no ?? 'N/A')
                                    ->visible(!empty($request->control_no)),
                                
                                Placeholder::make('details')
                                    ->label('Details')
                                    ->content($request->details ?? 'N/A')
                                    ->columnSpanFull()
                                    ->visible(!empty($request->details)),
                            ])
                            ->columns(2),
                    ]),
                Step::make('Time Tracking')
                    ->schema([
                        Section::make('Track your work time')
                            ->schema([
                                // Proceed Button - Show when not started
                                Placeholder::make('proceed_button_placeholder')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('
                                        <div class="text-center py-4">
                                            <p class="text-sm text-gray-600 mb-4">Click "Proceed" to start working on this request</p>
                                        </div>
                                    '))
                                    ->visible(function (Get $get): bool {
                                        return $get('start_pause') != 1 && $get('status') =='Pending';
                                    })
                                    ->columnSpanFull(),

                                // Time Summary Display - Show when coming back after entering time
                                Placeholder::make('time_summary')
                                    ->hiddenLabel()
                                    ->content(function (Get $get): HtmlString {
                                        $minutes = $get('time') ?? 0;
                                        $seconds = $get('seconds_time') ?? 0;
                                        
                                        return new HtmlString('
                                            <div class="bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-700 rounded-lg p-4">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Total Work Time</p>
                                                        <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">' 
                                                            . $minutes . ' min ' . $seconds . ' sec
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        ');
                                    })
                                    ->visible(function (Get $get): bool {
                                        // Show when:
                                        // - Status is "On Process" 
                                        // - start_pause is 0 (time has been saved)
                                        // - Time has been entered
                                        $hasTime = ($get('time') > 0 || $get('seconds_time') > 0);
                                        $isOnProcess = $get('status') == 'On Process';
                                        $timeSaved = $get('start_pause') == 0;
                                        
                                        return $isOnProcess && $timeSaved && $hasTime;
                                    })
                                    ->columnSpanFull(),
                                
                                // Time input fields - Show only after proceeding
                                TextInput::make('time')
                                    ->label('Time (Minutes)')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->minValue(0)
                                    ->suffix('min')
                                    ->visible(function (Get $get): bool {
                                        return $get('start_pause') == 1;
                                    }),
                                
                                TextInput::make('seconds_time')
                                    ->label('Seconds')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->minValue(0)
                                    ->maxValue(59)
                                    ->suffix('sec')
                                    ->visible(function (Get $get): bool {
                                        return $get('start_pause') == 1;
                                    }),
                                
                                // Status indicator after proceeding
                                Placeholder::make('work_status_indicator')
                                    ->hiddenLabel()
                                    ->content(new HtmlString('<div class="flex items-center gap-2 text-success-600">
                                        <span class="font-semibold">✅ Work in Progress - Enter your time and click Save</span>
                                    </div>'))
                                    ->visible(function (Get $get): bool {
                                        return $get('start_pause') == 1;
                                    })
                                    ->columnSpanFull(),
                                
                                // Hidden field
                                TextInput::make('start_pause')
                                    ->default(0)
                                    ->hidden()
                                    ->dehydrated(),
                            ])
                            ->columns(2)
                            ->footerActions([
                                // Proceed Button
                                Action::make('proceedWork')
                                    ->label('Proceed')
                                    ->icon('heroicon-o-arrow-right')
                                    ->color('primary')
                                    ->size('lg')
                                    ->visible(function (Get $get): bool {
                                        return $get('start_pause') != 1 && $get('status') =='Pending';
                                    })
                                    ->requiresConfirmation()
                                    ->modalHeading('Proceed to Work?')
                                    ->modalDescription('This will change the status to "On Process" and allow you to enter work time.')
                                    ->action(function (Component $livewire) {
                                        $livewire->proceedToWork(); // Call the method that updates database
                                    }),

                                Action::make('saveWork')
                                    ->label('Save')
                                    ->icon('heroicon-o-check')
                                    ->color('success')
                                    ->size('lg')
                                    ->visible(function (Get $get): bool {
                                        return $get('start_pause') == 1;
                                    })
                                    ->action(function (Component $livewire) {
                                        $livewire->save();
                                    }),
                            ]),

                    ])
                    ->afterValidation(function (Get $get) {
                        $status = $get('status');
                        $startPause = $get('start_pause');
                        $time = $get('time') ?? 0;
                        $secondsTime = $get('seconds_time') ?? 0;
                        
                        // Prevent proceeding if status is not "On Process"
                        if ($status != 'On Process') {
                            throw new \Filament\Support\Exceptions\Halt('Please click "Proceed" to start working on this request.');
                        }
                        
                        // Prevent proceeding if currently entering time
                        if ($startPause == 1) {
                            throw new \Filament\Support\Exceptions\Halt('Please save your time before proceeding to the next step.');
                        }
                        
                        // Prevent proceeding if no time entered
                        if ($time == 0 && $secondsTime == 0) {
                            throw new \Filament\Support\Exceptions\Halt('Please enter and save your work time before proceeding.');
                        }
                    }),

                Step::make('Work Details')
                    ->schema([
                        Section::make('Document your work and resolution')
                            ->schema([
                                Textarea::make('remark')
                                    ->label('Findings')
                                    ->rows(3)
                                    ->placeholder('Add your findings here...')
                                    ->required()
                                    ->columnSpanFull(),
                                
                                Textarea::make('resolution')
                                    ->label('Resolution')
                                    ->rows(3)
                                    ->placeholder('Describe the resolution...')
                                    ->required()
                                    ->columnSpanFull(),
                                
                                Textarea::make('testing')
                                    ->label('Testing')
                                    ->rows(3)
                                    ->placeholder('Testing details...')
                                    ->required()
                                    ->columnSpanFull(),
                                
                                Textarea::make('test_scenario')
                                    ->label('Test Scenario')
                                    ->rows(3)
                                    ->placeholder('Test scenario details...')
                                    ->required()
                                    ->columnSpanFull(),

                                Select::make('ipcr_code_id')
                                    ->label('IPCR Output')
                                    ->placeholder('Select IPCR Output')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->options(function () use ($request) {
                                        try {
                                            // Get the user assigned to this request
                                            $checkRequest = Checkrequest::where('formrequest_id', $request->id)
                                                ->where('user_id', auth()->id())
                                                ->with('assignuser')
                                                ->first();
                                            
                                            if (!$checkRequest || !$checkRequest->assignuser || !$checkRequest->assignuser->cats) {
                                                return [];
                                            }
                                            
                                            $empCode = $checkRequest->assignuser->cats;
                                                
                                            // Fetch data from API
                                            $response = \Illuminate\Support\Facades\Http::get(
                                                "https://ipcr.davaodeoro.gov.ph/ipcr-code",
                                                ['emp_code' => $empCode]
                                            );
                                            
                                            if (!$response->successful()) {
                                                return [];
                                            }
                                            
                                            $data = $response->json();
                                            
                                            // Format options: id => individual_output
                                            return collect($data)->pluck('individual_output', 'id')->toArray();
                                            
                                        } catch (\Exception $e) {
                                            \Illuminate\Support\Facades\Log::error('IPCR Code fetch failed: ' . $e->getMessage());
                                            return [];
                                        }
                                    })
                                    ->helperText('Select the IPCR Output related to this work')
                                    ->columnSpanFull(),
                            ])
                            ->visible(function (Get $get): bool {
                                return $get('start_pause') == 0 && $get('status') =='On Process'; 
                            })
                            ->collapsible()
                            ->collapsed(false)
                            ->footerActions([
                                Action::make('saveWork')
                                    ->label('Save')
                                    ->color('primary')
                                    ->size('lg')
                                    ->visible(function (Get $get): bool {
                                        return $get('start_pause') == 0 && $get('status') =='On Process';
                                    })
                                    ->action(function (Component $livewire) {
                                        $livewire->save();
                                    }),
                            ]),
                    ]),
            ])
            ->persistStepInQueryString()
            ->startOnStep(2)
            // Work Details Section - Only visible after starting
           
        ];
    }
}