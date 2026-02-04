<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $guarded = [];
    protected $primaryKey ="id";

    protected $casts = [
        'booked_at' => 'date',
        'returned_at' => 'datetime',
        'released_at' => 'datetime',
        'status' => 'string',
    ];

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id', 'id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    public function scopeReleased($query)
    {
        return $query->where('status', 'Released');
    }

    public function scopeReturned($query)
    {
        return $query->where('status', 'Returned');
    }
}
