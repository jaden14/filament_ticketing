<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Checkrequest extends Model
{ 
    
    protected $guarded = [];
    protected $primaryKey ="id";

    protected $casts = [
        'end_user_time' => 'datetime',
        'process_datetime' => 'datetime',
        'start_pause' => 'integer',
        'time' => 'integer',
        'seconds_time' => 'integer',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'formrequest_id', 'id');
    }

    public function assignuser()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function getFormattedTimeAttribute(): string
    {
        $minutes = $this->time ?? 0;
        $seconds = $this->seconds_time ?? 0;
        
        return "{$minutes} min {$seconds} sec";
    }

}
