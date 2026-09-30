<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nội dung phản hồi gửi đi. Đây không phải kết quả BHYT đã chấp thuận;
 * bản được duyệt là nội dung ghi vào cột `ĐÃ GIẢI TRÌNH` khi xuất trả.
 */
#[Fillable([
    'issue_case_id', 'version', 'body', 'resolution', 'status',
    'author_id', 'approver_id', 'submitted_at', 'approved_at', 'return_reason',
])]
class Explanation extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_RETURNED = 'returned';

    public const RESOLUTIONS = [
        'de_nghi_chap_nhan' => 'Đề nghị chấp nhận',
        'chap_nhan_xuat_toan' => 'Chấp nhận xuất toán',
        'can_chinh_du_lieu' => 'Cần chỉnh dữ liệu',
        'chuyen_don_vi' => 'Chuyển đơn vị khác',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function issueCase(): BelongsTo
    {
        return $this->belongsTo(IssueCase::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function resolutionLabel(): ?string
    {
        return $this->resolution ? (self::RESOLUTIONS[$this->resolution] ?? $this->resolution) : null;
    }
}
