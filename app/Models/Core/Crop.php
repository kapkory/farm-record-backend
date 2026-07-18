<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class Crop extends Model
{
    protected $fillable = ['uuid', 'name', 'description', 'type', 'status'];
}
