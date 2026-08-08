<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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

    public function animalGroups()
    {
        return $this->hasMany(AnimalGroup::class);
    }

    public function animals()
    {
        return $this->hasMany(Animal::class);
    }

    public function standaloneAnimals()
    {
        return $this->hasMany(Animal::class)->whereNull('animal_group_id');
    }

    public function animalBreedings()
    {
        return $this->hasMany(AnimalBreeding::class);
    }

    /**
     * Scope a filter farms based on farm users.
     *
     * Two layers: the user must belong to the farm's farmer, and — if they
     * have explicit farm assignments (see the farm_user table) — the farm must
     * be one of them. Users with no assignments are unrestricted, which keeps
     * owners and managers working untouched.
     *
     * Every livestock, hive, input, planting, sale and transaction listing
     * derives its farm ids from this scope, so pinning a staff login to a farm
     * here limits them everywhere at once.
     */
    #[Scope]
    protected function farmerOwned(Builder $query, $userId): void
    {
        $query->whereIn('farmer_id', function ($query) use ($userId) {
            $query->select('farmer_id')
                ->from('farmer_users')
                ->where('user_id', $userId);
        });

        $assignedFarmIds = DB::table('farm_user')->where('user_id', $userId)->pluck('farm_id');

        if ($assignedFarmIds->isNotEmpty()) {
            $query->whereIn('farms.id', $assignedFarmIds);
        }
    }
}
