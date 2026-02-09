<?php

namespace App\Filament\Resources\Requests\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Table;
use App\Models\Request;
use App\Models\Checkrequest;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\Requests\Schemas\CheckRequestFormSchema;
use App\Filament\Resources\Requests\RequestResource;

class RequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label("Ticket No.")
                    ->searchable()
                    ->sortable(),
               TextColumn::make('office.officename')
                    ->label("Office")
                    ->searchable()
                    ->sortable()
                    ->description(fn (Request $record): string => $record->name ?? 'N/A'),
               TextColumn::make('remarks')
                    ->label("Problem/issue")
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2) // Limit to 2 lines
                    ->tooltip(fn ($record) => $record->remarks),
                TextColumn::make('created_at')
                    ->label("Date")
                    ->date()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('prio')
                    ->label("Priority")
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'p1' => 'danger',
                        'p2' => 'success',
                        'p3' => 'secondary',
                    }),
                TextColumn::make('checkrequest.assignuser.lastname')
                    ->label('Assigned To')
                    ->badge(),

                TextColumn::make('status')
                    ->label("Status")
                    ->badge()
                    ->state(function (Request $record): string {
                        if (is_null($record->service_id) || 
                            is_null($record->prio) || 
                            is_null($record->category_id) || 
                            is_null($record->no_of_affected)) {
                            return 'Update Required';
                        }

                        $checkRequests = $record->checkrequest;
                        
                        // If no check requests, use main request status
                        if ($checkRequests->isEmpty()) {
                            return $record->status;
                        }
                        
                        // Determine status based on check requests
                        $total = $checkRequests->count();
                        $completed = $checkRequests->where('status', 'Completed')->count();
                        $onProcess = $checkRequests->where('status', 'On Process')->count();
                        
                        if ($completed === $total) {
                            return 'Closed';
                        } elseif ($onProcess > 0) {
                            return 'On Process';
                
                        } else {
                            return 'Pending';
                        }
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Pending' => 'success',
                        'On Process' => 'secondary',
                        'Closed' => 'primary',
                        'Update Required' => 'danger',
                        default => 'gray',
                    })
                    ->description(function (Request $record): ?string {
                        $checkRequests = $record->checkrequest;
                        
                        if ($checkRequests->isEmpty()) {
                            return null;
                        }
                        
                        $statusCounts = $checkRequests->countBy('status');
                        
                        $parts = [];
                        
                        if ($statusCounts->get('Pending')) {
                            $parts[] = "Pending: " . $statusCounts->get('Pending');
                        }
                        
                        if ($statusCounts->get('On Process')) {
                            $parts[] = "On Process: " . $statusCounts->get('On Process');
                        }
                        
                        if ($statusCounts->get('Completed')) {
                            $parts[] = "Closed: " . $statusCounts->get('Completed');
                        }
                        
                        return implode(' | ', $parts);
                    })
                    ->wrap(),

                TextColumn::make('service_time')
                    ->label('Service Time')
                    ->html()
                    ->extraAttributes(['class' => 'whitespace-pre-line'])
                    ->getStateUsing(function (Request $record): string {
                        return $record->formatted_service_time ?? 'N/A';
                    })
                    ->badge()
                    ->color(fn (Request $record): string => 
                        !$record->badge_service_time 
                            ? 'gray'
                            : ($record->badge_service_time > 3600 
                                ? 'danger' 
                                : ($record->badge_service_time > 1800 
                                    ? 'warning' 
                                    : 'success'
                                )
                            )
                    )
                    ->sortable(query: function ($query, $direction) {
                        // Sort by the shortest service time among assigned users
                        return $query->orderBy('total_service_time', $direction);
                    })
                    ->placeholder('N/A')
                    ->tooltip(function (Request $record): string {
                        $serviceTimes = $record->individual_service_times;
                        
                        if (empty($serviceTimes)) {
                            return 'No completed assignments yet';
                        }
                        
                        // Build detailed tooltip with each user's service time
                        $lines = ['Service time per assigned personnel:'];
                        
                        foreach ($serviceTimes as $time) {
                            $lines[] = "• {$time['user_fullname']}: {$time['formatted']}";
                        }
                        
                        $lines[] = '';
                        $lines[] = 'Time measured from request creation to completion by each user.';
                        
                        return implode("\n", $lines);
                    })
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->wrap(),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $query->withoutTrashed()
                      ->orderByStatusAndPriority(); // This now includes priority ordering
            })
            ->filters([
            ])
            ->recordActions([
                ActionGroup::make([
                    // View Details Action - Navigate to separate page
                    Action::make('viewDetails')
                        ->label('View Details')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->visible(function (Request $record): bool {
                            // Check if there are any checkrequests
                            $totalCheckrequests = $record->checkrequest()->count();
                            
                            // If no checkrequests exist, don't show the button
                            if ($totalCheckrequests === 0) {
                                return false;
                            }
                            
                            // Count completed checkrequests
                            $completedCheckrequests = $record->checkrequest()
                                ->where('status', 'Completed')
                                ->count();
                            
                            // Only show if ALL checkrequests are completed
                            return $totalCheckrequests === $completedCheckrequests;
                        })
                        ->url(fn (Request $record): string => 
                            RequestResource::getUrl('view-request', ['record' => $record])
                        ),

                     // My Assignment Action
                     Action::make('myAssignment')
                        ->label('My Assignment')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('info')
                        ->visible(function (Request $record): bool {
                              $assignment = Checkrequest::where('formrequest_id', $record->id)
                                    ->where('user_id', auth()->id())
                                    ->first();
                            return $assignment && $assignment->status !== 'Completed';
                        })
                        ->url(fn (Request $record): string => 
                            RequestResource::getUrl('my-assignment', ['record' => $record]) // Use $record->id
                        ),
                    
                    // Return By Action - NEW
                    Action::make('returnBy')
                        ->label('Return By')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('success')
                        ->modalHeading('Update Return By')
                        ->modalWidth('md')
                        ->visible(function (Request $record): bool {
                            // Check if user has any of these roles

                            if (!auth()->user()->hasAnyRole(['admin', 'super_admin'])) {
                                return false;
                            }
                            
                            // Only show if there are completed checkrequests
                            return $record->checkrequest()
                                ->where('status', 'Completed')
                                ->exists();
                        })
                        ->fillForm(function (Request $record): array {
                            $completedCheckRequest = $record->checkrequest()
                                ->where('status', 'Completed')
                                ->first();
                            
                            return [
                                'return_by' => $completedCheckRequest?->return_by ?? '',
                            ];
                        })
                        ->form([
                            TextInput::make('return_by')
                                ->label('Return By')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Enter return by information'),
                        ])
                        ->action(function (array $data, Request $record): void {
                            // Update all completed checkrequests WITHOUT updating timestamps
                            $completedCheckrequests = Checkrequest::where('formrequest_id', $record->id)
                                ->where('status', 'Completed')
                                ->get();
                            
                            foreach ($completedCheckrequests as $checkrequest) {
                                // Disable timestamps temporarily
                                $checkrequest->timestamps = false;
                                $checkrequest->update(['return_by' => $data['return_by']]);
                                $checkrequest->timestamps = true;
                            }
                        })
                        ->successNotificationTitle('Return By updated successfully'),

                    Action::make('assignReassign')
                        ->label('Assign')
                        ->icon('heroicon-o-user-plus')
                        ->modalHeading('ASSIGN / REASSIGN PERSONNEL')
                        ->modalWidth('md')
                        ->visible(function (Request $record): bool {
                            if (is_null($record->service_id) || 
                                is_null($record->prio) || 
                                is_null($record->category_id) || 
                                is_null($record->no_of_affected)) {
                                return false;
                            }

                            // Main conditions
                            if ($record->status !== 'Pending') {
                                return false;
                            }
                            
                           /* if (!auth()->user()->can('assignRequest', $record)) {
                                return false;
                            }*/
                            
                            // Check CheckRequest statuses
                            $checkRequests = CheckRequest::where('formrequest_id', $record->id)->get();
                            
                            if ($checkRequests->isEmpty()) {
                                // No assignments yet, can assign
                                return true;
                            }
                            
                            // Check if ANY assignment is in progress or completed
                            foreach ($checkRequests as $checkRequest) {
                                if (in_array($checkRequest->status, ['On Process', 'Completed'])) {
                                    return false;
                                }
                            }
                            
                            return true;
                        })
                        ->fillForm(fn (Request $record): array => [
                            'assigned_to' => $record->assigned_to,
                        ])
                        ->fillForm(fn (Request $record): array => [
                            // Get all assigned user IDs from CheckRequest table
                            'assigned_to' => CheckRequest::where('formrequest_id', $record->id)
                                ->pluck('user_id')
                                ->toArray(),
                        ])
                        ->form([
                            Select::make('assigned_to')
                                ->label('Assign To')
                                ->multiple()
                                ->required()
                                ->searchable()
                                ->preload()
                                ->options(User::query()
                                        ->where('status', 1)
                                        ->get()
                                        ->mapWithKeys(fn ($user) => [$user->id => $user->FullName])
                                        ->toArray()
                                )
                                ->placeholder('Select a user')
                                ->helperText('Previously assigned users will be pre-selected. You can add or remove users.'),
                        ])
                        ->action(function (array $data, Request $record): void {
                            $selectedUserIds = $data['assigned_to'];

                            $currentUserIds = CheckRequest::where('formrequest_id', $record->id)
                                ->pluck('user_id')
                                ->toArray();
                            
                            // Find users to add (new assignments)
                            $usersToAdd = array_diff($selectedUserIds, $currentUserIds);
                            
                            // Find users to remove (unassigned)
                            $usersToRemove = array_diff($currentUserIds, $selectedUserIds);

                            // Remove unassigned users
                            if (!empty($usersToRemove)) {
                                CheckRequest::where('formrequest_id', $record->id)
                                    ->whereIn('user_id', $usersToRemove)
                                    ->delete();
                            }

                            $addedCount = 0;
                            foreach ($usersToAdd as $userId) {
                                CheckRequest::create([
                                    'formrequest_id' => $record->id,
                                    'status' => 'Pending',
                                    'user_id' => $userId,
                                    'start_pause' => 0,
                                    'time' => 0,
                                    'seconds_time' => 0,
                                ]);
                                $addedCount++;
                            }

                            $message = '';
                            if ($addedCount > 0) {
                                $message .= "$addedCount user(s) added. ";
                            }
                            if (count($usersToRemove) > 0) {
                                $message .= count($usersToRemove) . " user(s) removed.";
                            }
                            if ($addedCount === 0 && count($usersToRemove) === 0) {
                                $message = "No changes made.";
                            }
                            
                            Notification::make()
                            ->success()
                            ->title('Assignment Updated')
                            ->body($message)
                            ->send();
                        })
                        ->successNotificationTitle('Personnel assignment updated'),
                    EditAction::make()
                        /*->visible(function (Request $record): bool {
                            // Only allow editing if:
                            // 1. Main status is 'Pending'
                            // 2. No CheckRequest is 'On Process' or 'Completed'
                            
                            if ($record->status !== 'Pending') {
                                return false;
                            }
                            
                            $hasActiveOrCompleted = CheckRequest::where('formrequest_id', $record->id)
                                ->whereIn('status', ['On Process', 'Completed'])
                                ->exists();
                                
                            return !$hasActiveOrCompleted;
                        }),*/
                ])
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->size('sm'),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $query->withoutTrashed()
                      ->orderByStatusPriority()
                      ->orderBy('created_at', 'desc');
            })
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
