<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use SoftDeletes; 

    protected $guarded = [];
    protected $primaryKey ="id";

    protected static function boot()
    {
        parent::boot();
        
        static::saving(function ($model) {
            $codes = [
                'Application Development Services' => 6,
                'Application Managed Services' => 1,
                'Communication Services' => 8,
                'Connectivity Management Services' => 2,
                'Equipment/Tool Borrowing' => 7,
                'ICT Support Repair' => 4,
                'Other Technical Services' => 5,
                'Preventive Maintenance' => 3,
            ];
            
            if (isset($codes[$model->classification])) {
                $model->classification_code = $codes[$model->classification];
            }
        });
    }
}
