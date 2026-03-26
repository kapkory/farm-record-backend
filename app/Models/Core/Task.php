<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    // ─── Status integers ──────────────────────────────────────────────────────
    public const STATUS_PENDING     = 1;
    public const STATUS_IN_PROGRESS = 2;
    public const STATUS_ON_HOLD     = 3;
    public const STATUS_COMPLETED   = 4;
    public const STATUS_CANCELLED   = 5;

    public const STATUS_LABELS = [
        self::STATUS_PENDING     => 'pending',
        self::STATUS_IN_PROGRESS => 'in_progress',
        self::STATUS_ON_HOLD     => 'on_hold',
        self::STATUS_COMPLETED   => 'completed',
        self::STATUS_CANCELLED   => 'cancelled',
    ];

    // ─── Priority integers ────────────────────────────────────────────────────
    public const PRIORITY_LOW      = 1;
    public const PRIORITY_MEDIUM   = 2;
    public const PRIORITY_HIGH     = 3;
    public const PRIORITY_CRITICAL = 4;

    public const PRIORITY_LABELS = [
        self::PRIORITY_LOW      => 'low',
        self::PRIORITY_MEDIUM   => 'medium',
        self::PRIORITY_HIGH     => 'high',
        self::PRIORITY_CRITICAL => 'critical',
    ];

    // ─── Keep for backward-compat with request validation ─────────────────────
    /** @deprecated use STATUS_LABELS keys instead */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_ON_HOLD,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'uuid',
        'title',
        'description',
        'user_id',
        'assigned_to_user_id',
        'due_date',
        'priority',
        'task_status',
        'parent_task_id',
        'taskable_type',
        'taskable_id',
    ];

    protected $casts = [
        'due_date'            => 'date',
        'priority'            => 'integer',
        'task_status'         => 'integer',
        'assigned_to_user_id' => 'integer',
        'parent_task_id'      => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to_user_id');
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function subTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('assigned_to_user_id', $userId);
        });
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('task_status', self::STATUS_PENDING);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('due_date', '<', now()->toDateString())
            ->whereNotIn('task_status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function scopeForTaskable(Builder $query, string $type, int $id): Builder
    {
        return $query->where('taskable_type', $type)->where('taskable_id', $id);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->task_status] ?? 'pending';
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITY_LABELS[$this->priority] ?? 'medium';
    }
}

