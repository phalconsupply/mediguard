<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một phiên bản quy tắc. Đã dùng để chạy thì không ghi đè: mọi thay đổi tạo phiên bản mới,
 * nhờ vậy kết quả cũ vẫn đối chiếu được.
 */
#[Fillable([
    'check_rule_id', 'version', 'definition', 'required_fields', 'exceptions', 'severity',
    'status', 'effective_from', 'effective_to', 'author_id', 'approver_id', 'approved_at', 'test_cases',
])]
class CheckRuleVersion extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ANALYSING = 'analysing';

    public const STATUS_TESTING = 'testing';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_RETIRED = 'retired';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Mới ghi nhận',
        self::STATUS_ANALYSING => 'Đang phân tích',
        self::STATUS_TESTING => 'Đang thử nghiệm',
        self::STATUS_APPROVED => 'Được duyệt',
        self::STATUS_ACTIVE => 'Đang áp dụng',
        self::STATUS_PAUSED => 'Tạm dừng',
        self::STATUS_RETIRED => 'Ngừng áp dụng',
    ];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'required_fields' => 'array',
            'exceptions' => 'array',
            'test_cases' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CheckRule::class, 'check_rule_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /** Chỉ phiên bản đang áp dụng mới được chạy trên dữ liệu thật. */
    public function scopeRunnable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeEffectiveOn(Builder $query, ?string $date): Builder
    {
        if ($date === null) {
            return $query;
        }

        return $query
            ->where(fn (Builder $q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
