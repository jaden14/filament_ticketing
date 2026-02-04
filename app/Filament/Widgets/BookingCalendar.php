<?php

namespace App\Filament\Widgets;

use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\Event;
use Guava\Calendar\ValueObjects\FetchInfo;
use Guava\Calendar\ValueObjects\EventClickInfo;
use Guava\Calendar\ValueObjects\EventDropInfo;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Guava\Calendar\ValueObjects\DateClickInfo;
use Guava\Calendar\Attributes\CalendarSchema;
use Guava\Calendar\ValueObjects\CalendarResource;
use Guava\Calendar\ValueObjects\DateSelectInfo;
use Guava\Calendar\Filament\Actions\CreateAction;
use App\Models\Booking;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class BookingCalendar extends CalendarWidget
{   
    public Model | string | null $model = Booking::class;

    protected static ?int $sort = 1;

    protected bool $eventClickEnabled = true;

    public function getHeaderActions(): Array 
    {
        return [
            CreateAction::make()
                ->label("Create Booking")
                ->modal(true)
                ->model(Booking::class)
                ->action(function (array $data)
                {
                    $this->model::create($data);
                })
        ];
    }

    protected function getEvents(FetchInfo $info): Collection | array | Builder 
    {
         return Booking::query()
            ->whereNotNull('booked_at')
            ->where('booked_at', '>=', $info->start)
            ->where('booked_at', '<=', $info->end)
            ->with(['service', 'office'])
            ->get()
            ->map(function (Booking $booking) {
                $service = $booking->service?->service_type ?? 'Service';
                $office = $booking->office?->officename ?? 'Office';
                $status = $booking->status;
                $title = "{$service} - {$office} - {$status}";
                
                $color = match($booking->status) {
                    'Pending' => '#f59e0b',
                    'Approved' => '#10b981',
                    'Released' => '#3b82f6',
                    'Returned' => '#ef4444',
                    default => '#8b5cf6',
                };
                
                return CalendarEvent::make($booking)
                    ->title($title)
                    ->start($booking->booked_at)
                    ->end($booking->returned_at ?? $booking->booked_at)
                    ->backgroundColor($color)
                    ->textColor('#ffffff')
                    ->allDay();
            })
            ->toArray();
    }

    protected function getEventClickContextMenuActions(): array
    {
        return [
            $this->editAction(),
            $this->deleteAction()
            ->disabled(fn ($record): bool => $record->status !== 'Pending')
            ->tooltip(fn ($record): ?string => 
                $record->status !== 'Pending' 
                    ? 'Cannot delete records that are not pending' 
                    : null
            ),
        ];
    }
}
