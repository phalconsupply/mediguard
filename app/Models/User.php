<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'staff_code', 'department_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_BHYT_MANAGER = 'bhyt_manager';

    public const ROLE_DEPT_HEAD = 'dept_head';

    public const ROLE_STAFF = 'staff';

    public const ROLE_VIEWER = 'viewer';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignedCases(): HasMany
    {
        return $this->hasMany(IssueCase::class, 'assignee_id');
    }

    /** Quản lý BHYT và quản trị xem được toàn đợt. */
    public function isManager(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_BHYT_MANAGER], true);
    }

    /** Trưởng khoa duyệt giải trình trong phạm vi khoa mình. */
    public function canApprove(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_BHYT_MANAGER, self::ROLE_DEPT_HEAD], true);
    }

    public function isViewer(): bool
    {
        return $this->role === self::ROLE_VIEWER;
    }
}
