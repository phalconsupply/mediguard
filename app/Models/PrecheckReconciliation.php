<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Đối chiếu phát hiện tự kiểm tra với phản hồi BHYT của cùng kỳ.
 *
 * Kết quả dùng để ưu tiên sửa thư viện quy tắc, không dùng như độ chính xác tuyệt đối
 * khi dữ liệu đầu vào hoặc quy định áp dụng đã thay đổi.
 */
#[Fillable([
    'precheck_run_id', 'response_batch_id', 'finding_id', 'issue_case_id',
    'outcome', 'note', 'confirmed_by',
])]
class PrecheckReconciliation extends Model
{
    public const OUTCOME_TRUE_POSITIVE = 'true_positive';

    public const OUTCOME_FALSE_POSITIVE = 'false_positive';

    public const OUTCOME_MISSED = 'missed';

    public const OUTCOME_NOT_CHECKABLE = 'not_checkable';

    public const OUTCOME_AMBIGUOUS = 'ambiguous';

    public const OUTCOME_LABELS = [
        self::OUTCOME_TRUE_POSITIVE => 'Bắt đúng',
        self::OUTCOME_FALSE_POSITIVE => 'Cảnh báo sai',
        self::OUTCOME_MISSED => 'Lỗi lọt',
        self::OUTCOME_NOT_CHECKABLE => 'Không kiểm tra được',
        self::OUTCOME_AMBIGUOUS => 'Chờ xác nhận ghép',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PrecheckRun::class, 'precheck_run_id');
    }

    public function responseBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'response_batch_id');
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    public function issueCase(): BelongsTo
    {
        return $this->belongsTo(IssueCase::class);
    }

    public function outcomeLabel(): string
    {
        return self::OUTCOME_LABELS[$this->outcome] ?? $this->outcome;
    }
}
