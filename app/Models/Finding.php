<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kết quả tự kiểm tra trên một bản ghi.
 *
 * Ba trạng thái tách bạch: phát hiện nguy cơ, không phát hiện theo quy tắc đã chạy,
 * và không đủ dữ liệu để kiểm tra. Thiếu dữ liệu không bao giờ được tính là đạt.
 */
#[Fillable([
    'precheck_run_id', 'check_rule_version_id', 'rule_code', 'result', 'ma_lk',
    'precheck_detail_id', 'locator', 'values', 'reason', 'missing_fields',
    'assignee_id', 'department_id', 'handling_status', 'handling_note',
])]
class Finding extends Model
{
    public const RESULT_DETECTED = 'detected';

    public const RESULT_NOT_DETECTED = 'not_detected';

    public const RESULT_INSUFFICIENT = 'insufficient_data';

    public const RESULT_LABELS = [
        self::RESULT_DETECTED => 'Phát hiện nguy cơ',
        self::RESULT_NOT_DETECTED => 'Không phát hiện theo quy tắc đã chạy',
        self::RESULT_INSUFFICIENT => 'Không đủ dữ liệu để kiểm tra',
    ];

    public const HANDLING_LABELS = [
        'open' => 'Chưa xử lý',
        'fixed' => 'Đã sửa dữ liệu nguồn',
        'accepted_risk' => 'Giữ nguyên, có lý do',
        'not_applicable' => 'Không áp dụng',
    ];

    protected function casts(): array
    {
        return [
            'locator' => 'array',
            'values' => 'array',
            'missing_fields' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PrecheckRun::class, 'precheck_run_id');
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(CheckRuleVersion::class, 'check_rule_version_id');
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(PrecheckDetail::class, 'precheck_detail_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function resultLabel(): string
    {
        return self::RESULT_LABELS[$this->result] ?? $this->result;
    }

    public function handlingLabel(): string
    {
        return self::HANDLING_LABELS[$this->handling_status] ?? $this->handling_status;
    }
}
