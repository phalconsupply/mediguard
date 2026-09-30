<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một việc giải trình gắn với đúng một dòng nguồn.
 *
 * Cùng một `ID_CP` bị nêu ở hai tệp sẽ tạo hai vụ việc riêng — đây là yêu cầu
 * nghiệp vụ, không phải dữ liệu trùng.
 */
#[Fillable([
    'import_batch_id', 'source_file_id', 'source_row_id', 'error_catalog_id', 'error_code',
    'conclusion', 'requires_explanation', 'amount_reference', 'status', 'assignee_id',
    'department_id', 'assignment_source', 'priority', 'due_on',
])]
class IssueCase extends Model
{
    public const STATUS_UNASSIGNED = 'unassigned';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_EXPORTED = 'exported';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_NEEDS_COORDINATION = 'needs_coordination';

    /** Nhãn tiếng Việt dùng trên giao diện và báo cáo. */
    public const STATUS_LABELS = [
        self::STATUS_UNASSIGNED => 'Chưa phân công',
        self::STATUS_ASSIGNED => 'Đã giao',
        self::STATUS_IN_PROGRESS => 'Đang giải trình',
        self::STATUS_PENDING_APPROVAL => 'Chờ duyệt',
        self::STATUS_APPROVED => 'Đã duyệt',
        self::STATUS_EXPORTED => 'Đã xuất',
        self::STATUS_RETURNED => 'Trả lại',
        self::STATUS_NEEDS_COORDINATION => 'Cần điều phối',
    ];

    protected function casts(): array
    {
        return [
            'requires_explanation' => 'boolean',
            'amount_reference' => 'decimal:2',
            'due_on' => 'date',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(SourceFile::class);
    }

    public function sourceRow(): BelongsTo
    {
        return $this->belongsTo(SourceRow::class);
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(ErrorCatalog::class, 'error_catalog_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class)->latest('id');
    }

    public function explanations(): HasMany
    {
        return $this->hasMany(Explanation::class)->orderByDesc('version');
    }

    public function approvedExplanation(): ?Explanation
    {
        return $this->explanations()->where('status', Explanation::STATUS_APPROVED)->first();
    }

    public function currentExplanation(): ?Explanation
    {
        return $this->explanations()->first();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Giới hạn danh sách theo phạm vi của người dùng ngay tại truy vấn.
     *
     * Đây là chốt chặn thật của phân quyền: ẩn nút trên giao diện không đủ,
     * mọi màn hình danh sách và mọi lần xuất đều phải đi qua đây.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isManager()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('assignee_id', $user->id);

            if ($user->department_id !== null) {
                $q->orWhere('department_id', $user->department_id);
            }
        });
    }
}
