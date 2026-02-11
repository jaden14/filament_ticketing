<?php

namespace App\Filament\Resources\Requests\Pages;

use App\Filament\Resources\Requests\RequestResource;
use App\Filament\Resources\Requests\Schemas\DetailsFormSchema;
use Filament\Resources\Pages\Page;
use App\Models\Request;
use App\Models\Checkrequest;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class ViewRequest extends Page
{

    protected static string $resource = RequestResource::class;

    public Request $record;

    public ?array $data = [];

    public function mount(): void
    {

        $this->form->fill();
    }

    public function getView(): string
    {
       return 'filament.resources.requests.pages.view-request';
    }

    public function getTitle(): string
    {
        return 'Details - Ticket #' . $this->record->id;
    }

     public function form(Schema $schema): Schema
    {
        return $schema
            ->schema(DetailsFormSchema::make($this->record))
            ->statePath('data');
    }

    public function saveIpcr(int $checkRequestId): void
    {
        $formData = $this->form->getState();
        
        // Get the IPCR code ID from the specific tab
        $ipcrCodeId = $formData['ipcr_code_id_' . $checkRequestId] ?? null;
        $userId = $formData['user_id_' . $checkRequestId] ?? null;
        $formRequestId = $formData['formrequest_id_' . $checkRequestId] ?? null;
        
        if (!$ipcrCodeId || !$userId || !$formRequestId) {
            Notification::make()
                ->title('Error')
                ->body('Missing required data')
                ->danger()
                ->send();
            return;
        }
        
        $this->submitDailyAccomplishment($ipcrCodeId, $userId, $formRequestId);
    }

    protected function submitDailyAccomplishment(int $ipcrCodeId, int $userId, int $formRequestId): void
    {
        try {
            // Get the specific check request using formrequest_id and user_id
            $checkRequest = Checkrequest::where('formrequest_id', $formRequestId)
                ->where('user_id', $userId)
                ->with('assignuser')
                ->first();
            
            if (!$checkRequest) {
                Notification::make()
                    ->title('Error')
                    ->body('Check request not found')
                    ->danger()
                    ->send();
                return;
            }
            
            if (!$checkRequest->assignuser || !$checkRequest->assignuser->cats) {
                Log::error('Daily Accomplishment: Employee code not found', [
                    'formrequest_id' => $formRequestId,
                    'user_id' => $userId
                ]);
                Notification::make()
                    ->title('Error')
                    ->body('Employee code not found')
                    ->danger()
                    ->send();
                return;
            }
            
            $empCode = $checkRequest->assignuser->cats;
            
            // Fetch IPCR details from API
            $ipcrResponse = Http::get(
                "https://ipcr.davaodeoro.gov.ph/ipcr-code",
                ['emp_code' => $empCode]
            );
            
            if (!$ipcrResponse->successful()) {
                Log::error('Daily Accomplishment: Failed to fetch IPCR data');
                Notification::make()
                    ->title('Error')
                    ->body('Failed to fetch IPCR data')
                    ->danger()
                    ->send();
                return;
            }
            
            $ipcrData = collect($ipcrResponse->json())->firstWhere('id', $ipcrCodeId);
            
            if (!$ipcrData) {
                Log::error('Daily Accomplishment: IPCR Code not found in API response');
                Notification::make()
                    ->title('Error')
                    ->body('IPCR Code not found')
                    ->danger()
                    ->send();
                return;
            }
            
            // Prepare payload
            $payload = [
                'date' => $checkRequest->updated_at->format('Y-m-d'),
                'description' => $checkRequest->resolution ?? 'Ticket resolved',
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
                // Update the check request using formrequest_id and user_id
                DB::table('checkrequests')
                ->where('formrequest_id', $formRequestId)
                ->where('user_id', $userId)
                ->update(['ipcr_code_id' => $ipcrCodeId]);
                
                Log::info('Daily Accomplishment submitted successfully', [
                    'ticket_id' => $this->record->id,
                    'formrequest_id' => $formRequestId,
                    'user_id' => $userId,
                    'ipcr_code_id' => $ipcrCodeId,
                    'payload' => $payload
                ]);
                
                Notification::make()
                    ->title('Success')
                    ->body('Successfully added to IPCR')
                    ->success()
                    ->send();
                
                // Refresh the page
                $this->dispatch('$refresh');
            } else {
                Log::error('Daily Accomplishment submission failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'payload' => $payload
                ]);
                
                Notification::make()
                    ->title('Error')
                    ->body('Failed to submit to IPCR')
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Log::error('Daily Accomplishment exception: ' . $e->getMessage(), [
                'ticket_id' => $this->record->id,
                'formrequest_id' => $formRequestId,
                'user_id' => $userId
            ]);
            
            Notification::make()
                ->title('Error')
                ->body('An error occurred: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
