<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nhật ký truy cập và thay đổi. Chỉ ghi thêm, không sửa. */
#[Fillable(['user_id', 'action', 'subject_type', 'subject_id', 'meta', 'ip', 'created_at'])]
class AuditEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
