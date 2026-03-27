<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Farm extends Model
{
    use HasFactory;

    protected $table = 'farms';

    protected $fillable = [
        'uuid',
        'name',
        'location',
        'size',
        'size_unit',
        'established_date',
        'description',
        'type',
        'ownership_type',
        'status',
        'farmer_id',
    ];

    protected $casts = [
        'established_date' => 'date',
        'size' => 'double',
    ];

    /**
     * The user who owns / created the farm
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function plantings()
    {
        return $this->hasMany(Planting::class);
    }

    public function fields()
    {
        return $this->hasMany(Field::class);
    }

    /**
     * Scope a filter farms based on farm users.
     */
    #[Scope]
    protected function farmerOwned(Builder $query,$userId): void{
         $query->whereIn('farmer_id',function ($query) use ($userId){
            $query->select('farmer_id')
                ->from('farmer_users')
                ->where('user_id',$userId);
        });
    }
}

