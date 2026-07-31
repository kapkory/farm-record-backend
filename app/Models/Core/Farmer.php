<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Farmer extends Model
{
    protected $fillable = ['uuid', 'display_name', 'type', 'status'];

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }
}
