<?php
namespace App\Filament\Resources\Requests\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use App\Models\Request;
use App\Models\Checkrequest;
use Carbon\Carbon;
use Filament\Forms\Components\Hidden;

class DetailsFormSchema
{
	public static function make(Request $request): array
    {
    	return [
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
    		Tabs::make('Tabs')
				    ->tabs(function () use ($request) {
			        $checkRequests = Checkrequest::with('assignuser', 'request')->where('formrequest_id', $request->id)->get();
			        
			        // Group by user
			        $groupedByUser = $checkRequests->groupBy('user_id');
			        
			        return $groupedByUser->map(function ($userCheckRequests, $userId) use ($request) {
			            $user = $userCheckRequests->first()->assignuser;
			            $data = $userCheckRequests->first();
			            $checkRequestId = $data->id;

			            return Tab::make($user->FullName)
			                ->schema([
				                   Placeholder::make('time_summary_'. $checkRequestId)
                                    ->hiddenLabel()
                                    ->content(function () use ($data): HtmlString {
                                        $minutes = $data->time ?? 0;
                                        $seconds = $data->seconds_time ?? 0;
                                        
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
                                    ->columnSpanFull(),

                                    Section::make('Work Details')
                                    		->schema([
                                    			Placeholder::make('remark_' . $checkRequestId)
				                                    ->label('Findings')
				                                    ->content($data->remark),

				                                Placeholder::make('resolution_' . $checkRequestId)
				                                    ->label('Resolution')
				                                    ->content($data->resolution),

				                                Placeholder::make('testing_' . $checkRequestId)
				                                    ->label('Testing')
				                                    ->content($data->testing),
				                                    
				                                Placeholder::make('test_scenario_' . $checkRequestId)
				                                    ->label('Test Scenario')
				                                    ->content($data->test_scenario),
                                    		])
                                    		->columns(2),

                                   	Placeholder::make('ipcr_status_' . $checkRequestId)
                                        ->label('IPCR Status')
                                        ->content(new HtmlString('
                                            <div class="bg-success-50 dark:bg-success-900/20 border border-success-200 dark:border-success-700 rounded-lg px-3 py-2 inline-block">
                                                <span class="text-success-700 dark:text-success-400 font-semibold">✓ ADDED to IPCR</span>
                                            </div>
                                        '))
                                        ->visible(!is_null($data->ipcr_code_id))
                                        ->columnSpanFull(),

                                    Section::make('IPCR Output')
                                        ->schema([

                                            Select::make('ipcr_code_id_' . $checkRequestId)
                                                ->label('IPCR Output')
                                                ->placeholder('Select IPCR Output')
                                                ->searchable()
                                                ->preload()
                                                ->options(function () use ($data) {
                                                    try {
			                                            // Get the user assigned to this request
			                                            	
			                                            if (!$data || !$data->assignuser || !$data->assignuser->cats) {
			                                                return [];
			                                            }
			                                            
			                                            $empCode = $data->assignuser->cats;
			                                
			                                            // Fetch data from API
			                                            $response = \Illuminate\Support\Facades\Http::get(
			                                                "https://ipcr.davaodeoro.gov.ph/ipcr-code",
			                                                ['emp_code' => $empCode]
			                                            );
			                                            
			                                            if (!$response->successful()) {
			                                                return [];
			                                            }
			                                          
			                                            $datas = $response->json();
			                                            
			                                            // Format options: id => individual_output
			                                            return collect($datas)->pluck('individual_output', 'id')->toArray();
			                                            
			                                        } catch (\Exception $e) {
			                                            \Illuminate\Support\Facades\Log::error('IPCR Code fetch failed: ' . $e->getMessage());
			                                            return [];
			                                        }
                                                })
                                                ->helperText('Select the IPCR Output related to this work')
                                                ->live()
                                                ->hintAction(
                                                    Action::make('save_ipcr_' . $checkRequestId)
                                                        ->label('Save to IPCR')
                                                        ->icon('heroicon-o-check-circle')
                                                        ->color('success')
                                                        ->requiresConfirmation()
                                                        ->modalHeading('Save to IPCR')
                                                        ->modalDescription('Are you sure you want to add this to IPCR?')
					                                    ->action(function ($livewire) use ($checkRequestId) {
					                                        $livewire->saveIpcr($checkRequestId);
					                                    })
                                                        ->visible(fn (Get $get): bool => !empty($get('ipcr_code_id_' . $checkRequestId)))
                                                ),

                                                Hidden::make('formrequest_id_' . $checkRequestId)
                                                ->default($data->formrequest_id),
                                                
	                                            Hidden::make('user_id_' . $checkRequestId)
	                                                ->default($data->user_id),
	                                                
	                                            Hidden::make('checkrequest_id_' . $checkRequestId)
	                                                ->default($data->id),
                                        ])
                                        ->visible(is_null($data->ipcr_code_id))
                                        ->columnSpanFull(),
			                ]);
			        })->toArray();
			    })
    	];

    }
}