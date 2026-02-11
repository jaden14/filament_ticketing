<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Request extends Model
{
    use SoftDeletes; 
    
    protected $table = 'formrequests';
    protected $guarded = [];
    protected $primaryKey ="id";

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id', 'id');
    }

    public function checkrequest()
    {
        return $this->hasMany(Checkrequest::class, 'formrequest_id', 'id');
    }

    public function myCheckRequest()
    {
        return $this->hasOne(CheckRequest::class, 'formrequest_id')
            ->where('user_id', auth()->id());
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function scopeOrderByStatusPriority(Builder $query): Builder
    {
        return $query->orderByRaw("
            CASE 
                WHEN status = 'Pending' THEN 1
                WHEN status = 'On Process' THEN 2
                WHEN status = 'Completed' THEN 3
                ELSE 4
            END
        ");
    }

    public function getIndividualServiceTimesAttribute(): array
    {
        $serviceTimes = [];
        
        $completedCheckRequests = $this->checkrequest()
            ->where('status', 'Completed')
            ->with('assignuser')
            ->get();
        
        if ($completedCheckRequests->isEmpty()) {
            return [];
        }
        
        $createdAt = Carbon::parse($this->created_at);
        
        foreach ($completedCheckRequests as $checkRequest) {
            if ($checkRequest->updated_at) {
                $completedAt = Carbon::parse($checkRequest->updated_at);
                $seconds = $createdAt->diffInSeconds($completedAt);
                
                $serviceTimes[] = [
                    'user_id' => $checkRequest->user_id,
                    'user_name' => $checkRequest->assignuser->lastname ?? 'Unknown',
                    'user_fullname' => $checkRequest->assignuser->FullName ?? 'Unknown',
                    'seconds' => $seconds,
                    'formatted' => $this->formatSeconds($seconds),
                    'completed_at' => $checkRequest->updated_at,
                ];
            }
        }
        
        return $serviceTimes;
    }
    
    /**
     * Get total service time in seconds (longest service time among all users)
     */
    public function getTotalServiceTimeAttribute(): ?int
    {
        $serviceTimes = $this->individual_service_times;
        
        if (empty($serviceTimes)) {
            return null;
        }
        
        // Return the maximum service time (longest completion time)
        return collect($serviceTimes)->max('seconds');
    }
    
    /**
     * Get formatted service time string for display in table
     * Shows all users and their individual service times
     */
    public function getFormattedServiceTimeAttribute(): string
    {
        $serviceTimes = $this->individual_service_times;
        
        if (empty($serviceTimes)) {
            return 'N/A';
        }
        
        // If only one user, return simple format
        if (count($serviceTimes) === 1) {
            return $serviceTimes[0]['formatted'];
        }
        
        // Multiple users: show each user's time
        $parts = [];
        foreach ($serviceTimes as $time) {
            $parts[] = $time['user_name'] . ': ' . $time['formatted'];
        }
        
        return implode('<br>', $parts);
    }
    
    /**
     * Get formatted service time for badge display (shortest time for color coding)
     */
    public function getBadgeServiceTimeAttribute(): ?int
    {
        $serviceTimes = $this->individual_service_times;
        
        if (empty($serviceTimes)) {
            return null;
        }
        
        // Return the minimum service time for badge color (fastest response)
        return collect($serviceTimes)->min('seconds');
    }
    
    /**
     * Helper method to format seconds
     */
    private function formatSeconds(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $secs);
        } else {
            return sprintf('%ds', $secs);
        }
    }

    public function scopeOrderByStatusAndPriority(Builder $query): Builder
    {
        return $query->selectRaw('
            formrequests.*,
            CASE
                -- Update Required: ONLY if no checkrequests exist AND missing required fields
                WHEN NOT EXISTS (
                    SELECT 1 FROM checkrequests 
                    WHERE checkrequests.formrequest_id = formrequests.id
                ) AND (service_id IS NULL OR prio IS NULL OR category_id IS NULL OR no_of_affected IS NULL) 
                THEN 1
                
                -- Pending: No checkrequests exist yet (and has all required fields)
                WHEN NOT EXISTS (
                    SELECT 1 FROM checkrequests 
                    WHERE checkrequests.formrequest_id = formrequests.id
                ) 
                THEN 2
                
                -- Pending: Checkrequests exist but all are Pending
                WHEN EXISTS (
                    SELECT 1 FROM checkrequests 
                    WHERE checkrequests.formrequest_id = formrequests.id
                ) AND NOT EXISTS (
                    SELECT 1 FROM checkrequests 
                    WHERE checkrequests.formrequest_id = formrequests.id 
                    AND checkrequests.status IN ("On Process", "Completed")
                ) 
                THEN 2
                
                -- On Process: At least one checkrequest is On Process
                WHEN EXISTS (
                    SELECT 1 FROM checkrequests 
                    WHERE checkrequests.formrequest_id = formrequests.id 
                    AND checkrequests.status = "On Process"
                ) 
                THEN 3
                
                -- Closed: All checkrequests are Completed
                WHEN EXISTS (
                    SELECT 1 FROM checkrequests 
                    WHERE checkrequests.formrequest_id = formrequests.id
                ) AND NOT EXISTS (
                    SELECT 1 FROM checkrequests 
                    WHERE checkrequests.formrequest_id = formrequests.id 
                    AND checkrequests.status != "Completed"
                ) 
                THEN 4
                
                ELSE 5
            END as status_priority
        ')
        ->orderBy('status_priority', 'asc')
        ->orderBy('created_at', 'desc');
    }
}
