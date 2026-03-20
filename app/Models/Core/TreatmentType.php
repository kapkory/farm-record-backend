<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreatmentType extends Model
{
    use SoftDeletes;

    protected $fillable = ['uuid', 'name', 'description', 'status', 'type'];
}
