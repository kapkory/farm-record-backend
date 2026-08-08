<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Core\Farmer;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'phone',
        'email',
        'role',
        'is_superadmin',
        'status',
        'password',
        'must_change_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_superadmin' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_superadmin;
    }

    public function farmers(): BelongsToMany
    {
        return $this->belongsToMany(Farmer::class, 'farmer_users', 'user_id', 'farmer_id');
    }

    /**
     * Farms this user is pinned to. Empty means unrestricted — see the
     * farm_user migration; owners and managers normally have no rows.
     */
    public function assignedFarms(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Core\Farm::class, 'farm_user', 'user_id', 'farm_id');
    }

    /**
     * This user's role on their farmer (owner|manager|staff). Superadmins are
     * treated as owners. Null when the user belongs to no farmer at all.
     */
    public function farmerRole(): ?string
    {
        if ($this->isSuperAdmin()) {
            return 'owner';
        }

        return \App\Models\Core\FarmerUser::query()
            ->where('user_id', $this->id)
            ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'manager' THEN 2 ELSE 3 END")
            ->value('role');
    }

    /** Only owners and managers may see money: totals, sales, costs. */
    public function canViewFinances(): bool
    {
        return in_array($this->farmerRole(), ['owner', 'manager'], true);
    }

    /**
     * Farm ids this user may see, or null for "no restriction". Null rather
     * than a list so callers can skip the filter entirely for owners.
     *
     * @return array<int, int>|null
     */
    public function allowedFarmIds(): ?array
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        $ids = \Illuminate\Support\Facades\DB::table('farm_user')
            ->where('user_id', $this->id)
            ->pluck('farm_id')
            ->all();

        return $ids === [] ? null : $ids;
    }
}
