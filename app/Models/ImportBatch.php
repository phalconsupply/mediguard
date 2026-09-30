<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Một kỳ tiếp nhận: phản hồi BHYT (`response`) hoặc tự kiểm tra trước khi gửi (`precheck`). */
#[Fillable(['code', 'kind', 'period', 'facility_code', 'title', 'status', 'created_by', 'closed_at', 'stats'])]
class ImportBatch extends Model
{
    public const KIND_RESPONSE = 'response';

    public const KIND_PRECHECK = 'precheck';

    protected function casts(): array
    {
        return [
            'stats' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    public function files(): HasMany
    {
        return $this->hasMany(SourceFile::class);
    }

    public function issueCases(): HasMany
    {
        return $this->hasMany(IssueCase::class);
    }

    public function precheckRuns(): HasMany
    {
        return $this->hasMany(PrecheckRun::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /**
     * Mốc thời gian dùng để tra danh mục có ngày hiệu lực.
     * Kỳ `202607` được hiểu là ngày cuối tháng 7/2026.
     */
    public function effectiveDate(): ?string
    {
        if (! $this->period || ! preg_match('/^(\d{4})(\d{2})$/', $this->period, $m)) {
            return null;
        }

        return date('Y-m-t', mktime(0, 0, 0, (int) $m[2], 1, (int) $m[1]));
    }
}
