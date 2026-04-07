<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Office extends Model
{
     use SoftDeletes; 
    
    protected $guarded = [];
    protected $primaryKey ="id";

    public function user()
    {
        return $this->hasMany(User::class, 'office_id', 'id');
    }
}
