<?php

namespace App\Filament\Resources\Requests\Pages;

use App\Filament\Resources\Requests\RequestResource;
use App\Filament\Resources\Requests\Schemas\CheckRequestForm;
use App\Models\Checkrequest;
use App\Models\Request;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ViewAssignment extends Page
{
    protected static string $resource = RequestResource::class;

    // Remove static keyword
    public function getView(): string
    {
        return 'filament.resources.requests.pages.view-my-assignment';
    }

    public ?array $data = [];
    
    public Request $record;
    
    protected $checkRequest;

    public function mount(): void
    {

        $this->record->load(['office', 'service']);

        // Store checkRequest as property
        $this->checkRequest = Checkrequest::where('formrequest_id', $this->record->id)
            ->where('user_id', auth()->id())
            ->first();
        
        // Handle unauthorized access
        if (!$this->checkRequest) {
            Notification::make()
                ->danger()
                ->title('Access Denied')
                ->body('You are not assigned to this request.')
                ->send();
            
            $this->redirect(RequestResource::getUrl('index'));
            return;
        }

        $this->fillFormData();
    }

    protected function fillFormData(): void
    {
        $this->form->fill([
            'status' => $this->checkRequest->status,
            'remark' => $this->checkRequest->remark,
            'resolution' => $this->checkRequest->resolution,
            'testing' => $this->checkRequest->testing,
            'end_user' => $this->checkRequest->end_user,
            'end_user_time' => $this->checkRequest->end_user_time,
            'time' => $this->checkRequest->time ?? 0,
            'start_pause' => $this->checkRequest->start_pause ?? 0,
            'process_datetime' => $this->checkRequest->process_datetime,
            'seconds_time' => $this->checkRequest->seconds_time ?? 0,
            'test_scenario' => $this->checkRequest->test_scenario,
            'ipcr_code_id' => $this->checkRequest->ipcr_code_id,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema(CheckRequestForm::make($this->record))
            ->statePath('data');
    }

    public function proceedToWork(): void
    {
        $this->checkRequest = Checkrequest::where('formrequest_id', $this->record->id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$this->checkRequest) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Assignment not found.')
                ->send();
            return;
        }
        // Update database immediately
        $this->checkRequest->update([
            'start_pause' => 1,
            'status' => 'On Process',
            'process_datetime' => now(),
        ]);

         $this->checkRequest->refresh();
        
        // Update form state
        $this->fillFormData();
        
        Notification::make()
            ->success()
            ->title('Process Started')
            ->body('Status changed to "On Process". You can now enter your work time.')
            ->send();
    }

    public function save(): void
    {
        $this->checkRequest = Checkrequest::where('formrequest_id', $this->record->id)
            ->where('user_id', auth()->id())
            ->first();

        $data = $this->form->getState();

        if (!$this->checkRequest) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Assignment not found.')
                ->send();
            return;
        }

         // Check if this is a time tracking save (start_pause == 1)
        $isTimeSave = $this->checkRequest->start_pause == 1 && $this->checkRequest->status == 'On Process';

        $willComplete = $this->checkRequest->status == 'On Process' 
        && $this->checkRequest->start_pause == 0
        && isset($data['start_pause']) && $data['start_pause'] == 1;
        

        if ($isTimeSave) {
            if (($data['time'] ?? 0) == 0 && ($data['seconds_time'] ?? 0) == 0) {
                Notification::make()
                    ->danger()
                    ->title('Validation Error')
                    ->body('Please enter work time before saving.')
                    ->send();
                return;
            }
        }
        
        $updateData = [
            'status' => $data['status'] ?? $this->checkRequest->status,
            'remark' => $data['remark'] ?? null,
            'resolution' => $data['resolution'] ?? null,
            'testing' => $data['testing'] ?? null,
            'test_scenario' => $data['test_scenario'] ?? null,
            'time' => $this->getTimeValue('time', $data),
            'seconds_time' => $this->getTimeValue('seconds_time', $data),
            'process_datetime' => $data['process_datetime'] ?? $this->checkRequest->process_datetime,
            'ipcr_code_id' => $data['ipcr_code_id'] ?? null,
        ];

        if ($isTimeSave) {
            $updateData['start_pause'] = 0;
        } else {
            $updateData['start_pause'] = $data['start_pause'] ?? 0;
        }

        if ($this->checkRequest->status == 'On Process' && $this->checkRequest->start_pause == 0 && !$isTimeSave) {
            $updateData['status'] = 'Completed';
        }
        
        $this->checkRequest->update($updateData);

        $this->checkRequest->refresh();

        if ($updateData['status'] == 'Completed') {
            $this->submitDailyAccomplishment($data);
        }

        $this->fillFormData();

        if ($isTimeSave) {
            Notification::make()
                ->success()
                ->title('Time Saved')
                ->body('Your work time has been saved. Click "Next" to continue.')
                ->send();
        } elseif ($willComplete || $updateData['status'] == 'Completed') {
            Notification::make()
                ->success()
                ->title('Assignment Completed')
                ->body('Your assignment has been completed successfully.')
                ->send();
            
            // Redirect after completion
            $this->js('setTimeout(() => window.location.href = "' . RequestResource::getUrl('index') . '", 1500)');
        } else {
            Notification::make()
                ->success()
                ->title('Assignment Saved')
                ->body('Your assignment has been saved successfully.')
                ->send();
        }
    }

     protected function submitDailyAccomplishment(array $data): void
    {
        try {
            // Load the assigned user with cats
            $this->checkRequest->load('assignuser');
            
            if (!$this->checkRequest->assignuser || !$this->checkRequest->assignuser->cats) {
                Log::error('Daily Accomplishment: Employee code not found for user ' . auth()->id());
                return;
            }

            $empCode = $this->checkRequest->assignuser->cats;
            $ipcrCodeId = $data['ipcr_code_id'] ?? $this->checkRequest->ipcr_code_id;

            if (!$ipcrCodeId) {
                Log::error('Daily Accomplishment: IPCR Code ID not found');
                return;
            }

            // Fetch IPCR details from API
            $ipcrResponse = Http::get(
                "https://ipcr.davaodeoro.gov.ph/ipcr-code",
                ['emp_code' => $empCode]
            );

            if (!$ipcrResponse->successful()) {
                Log::error('Daily Accomplishment: Failed to fetch IPCR data');
                return;
            }

            $ipcrData = collect($ipcrResponse->json())->firstWhere('id', $ipcrCodeId);

            if (!$ipcrData) {
                Log::error('Daily Accomplishment: IPCR Code not found in API response');
                return;
            }

            // Prepare payload
            $payload = [
                'date' => now()->format('Y-m-d'),
                'description' => $data['resolution'] ?? $this->checkRequest->resolution ?? 'Ticket resolved',
                'emp_code' => $empCode,
                'individual_final_output_id' => $ipcrData['individual_final_output_id'],
                'individual_output' => $ipcrData['individual_output'],
                'sem_id' => $ipcrData['sem_id'] ?? null,
            ];

            // Submit to Daily Accomplishment API
            $response = Http::post(
                'https://ipcr.davaodeoro.gov.ph/Daily_Accomplishment/ticketing/api',
                $payload
            );

            if ($response->successful()) {
                Log::info('Daily Accomplishment submitted successfully', [
                    'ticket_id' => $this->record->id,
                    'user_id' => auth()->id(),
                    'payload' => $payload
                ]);
            } else {
                Log::error('Daily Accomplishment submission failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'payload' => $payload
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Daily Accomplishment exception: ' . $e->getMessage(), [
                'ticket_id' => $this->record->id,
                'user_id' => auth()->id()
            ]);
        }
    }

    protected function getStatus(): string
    {
        if ($this->checkRequest->status == 'On Process' && $this->checkRequest->start_pause == 0) {
            return 'Completed';
        }
        
        return $this->checkRequest->status;
    }

    protected function getTimeValue(string $field, array $data)
    {
        $isProcessOngoing = $this->checkRequest->status == 'On Process' 
            && $this->checkRequest->start_pause == 0;
        
        if ($isProcessOngoing) {
            return $this->checkRequest->{$field};
        }
        
        return $data[$field] ?? $this->checkRequest->{$field};
    }

    protected function getHeaderActions(): array
    {
        return [
            
            Action::make('back')
                ->label('Back to Requests')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(RequestResource::getUrl('index')),
        ];
    }

    public function getTitle(): string
    {
        return 'My Assignment - Ticket #' . $this->record->id;
    }
}