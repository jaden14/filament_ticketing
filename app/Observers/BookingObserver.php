<?php

namespace App\Observers;

use App\Models\Booking;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class BookingObserver
{
    /**
     * Handle the Booking "created" event.
     */
    public function created(Booking $booking): void
    {
         if ($booking->ipcr_code_id) {
            $this->submitDailyAccomplishment($booking);
        }
    }

    /**
     * Handle the Booking "updated" event.
     */
    public function updated(Booking $booking): void
    {
        if ($booking->wasChanged('ipcr_code_id') && $booking->ipcr_code_id) {
            $this->submitDailyAccomplishment($booking);
        }
    }

    /**
     * Handle the Booking "deleted" event.
     */
    public function deleted(Booking $booking): void
    {
        //
    }

    /**
     * Handle the Booking "restored" event.
     */
    public function restored(Booking $booking): void
    {
        //
    }

    /**
     * Handle the Booking "force deleted" event.
     */
    public function forceDeleted(Booking $booking): void
    {
        //
    }

    protected function submitDailyAccomplishment(Booking $booking): void
    {
        try {
            $user = auth()->user();
            
            if (!$user || !$user->cats) {
                Log::error('Daily Accomplishment: Employee code not found', [
                    'booking_id' => $booking->id,
                    'user_id' => $user?->id ?? null
                ]);
                
                Notification::make()
                    ->title('Error')
                    ->body('Employee code not found')
                    ->danger()
                    ->send();
                return;
            }
            
            $empCode = $user->cats;
            
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
            
            $ipcrData = collect($ipcrResponse->json())->firstWhere('id', $booking->ipcr_code_id);
            
            if (!$ipcrData) {
                Log::error('Daily Accomplishment: IPCR Code not found in API response', [
                    'ipcr_code_id' => $booking->ipcr_code_id
                ]);
                
                Notification::make()
                    ->title('Error')
                    ->body('IPCR Code not found')
                    ->danger()
                    ->send();
                return;
            }
            
            // Prepare payload
            $payload = [
                'date' => now()->format('Y-m-d'),
                'description' => $booking->purpose ?? 'Booking completed',
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
                    'booking_id' => $booking->id,
                    'user_id' => $user->id,
                    'ipcr_code_id' => $booking->ipcr_code_id,
                    'payload' => $payload
                ]);
                
                Notification::make()
                    ->title('Success')
                    ->body('Successfully added to IPCR')
                    ->success()
                    ->send();
            } else {
                Log::error('Daily Accomplishment submission failed', [
                    'booking_id' => $booking->id,
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'payload' => $payload
                ]);
                
                Notification::make()
                    ->title('Error')
                    ->body('Failed to submit to IPCR: ' . $response->body())
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Log::error('Daily Accomplishment exception: ' . $e->getMessage(), [
                'booking_id' => $booking->id,
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            Notification::make()
                ->title('Error')
                ->body('An error occurred: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
