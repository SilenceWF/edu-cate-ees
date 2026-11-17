<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLES = ['employee', 'supervisor', 'hr_auditor', 'admin'];

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TrainingGroup::class, 'training_group_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function trackOverride(): BelongsTo
    {
        return $this->belongsTo(Track::class, 'track_id');
    }

    /** The track that applies to this person: their own override, else their group's. */
    public function effectiveTrack(): ?Track
    {
        return $this->trackOverride ?? $this->group?->track;
    }

    public function acknowledgments(): HasMany
    {
        return $this->hasMany(PolicyAcknowledgment::class);
    }

    public function declarations(): HasMany
    {
        return $this->hasMany(PolicyDeclaration::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(ModuleCompletion::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(SessionAttendance::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isStaffViewer(): bool
    {
        return $this->hasRole('admin', 'hr_auditor');
    }

    public function canLaunchKiosk(): bool
    {
        return $this->hasRole('admin', 'hr_auditor', 'supervisor');
    }
}
