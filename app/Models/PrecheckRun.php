<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một lần chạy tự kiểm tra.
 *
 * `ruleset_snapshot` và `input_snapshot` chụp lại phiên bản quy tắc và hash tệp
 * để lần chạy này đọc lại được nguyên trạng dù thư viện đổi sau đó.
 */
#[Fillable([
    'import_batch_id', 'status', 'ruleset_snapshot', 'input_snapshot',
    'encounters_total', 'details_total', 'detected_count', 'insufficient_count',
    'coverage', 'failure_reason', 'started_by', 'started_at', 'finished_at',
])]
class PrecheckRun extends Model
{
    protected function casts(): array
    {
        return [
            'ruleset_snapshot' => 'array',
            'input_snapshot' => 'array',
            'coverage' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}
