<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cấu hình cột hiển thị. Chỉ tác động giao diện: không xóa dữ liệu,
 * không đổi cấu trúc tệp xuất trả.
 *
 * `columns` lưu theo `internal_code` (tiêu đề chuẩn hóa + lần xuất hiện) nên
 * chịu được việc đổi thứ tự cột giữa các kỳ.
 */
#[Fillable([
    'name', 'scope_type', 'scope_value', 'role', 'columns', 'version', 'is_default', 'created_by',
])]
class ColumnPreset extends Model
{
    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_ERROR_CODE = 'error_code';

    public const SCOPE_ERROR_GROUP = 'error_group';

    public const SCOPE_DEPARTMENT = 'department';

    public const SCOPE_BATCH = 'batch';

    /** Thứ tự ưu tiên khi nhiều cấu hình cùng áp dụng; cụ thể hơn thắng. */
    public const SCOPE_PRIORITY = [
        self::SCOPE_BATCH => 50,
        self::SCOPE_ERROR_CODE => 40,
        self::SCOPE_DEPARTMENT => 30,
        self::SCOPE_ERROR_GROUP => 20,
        self::SCOPE_GLOBAL => 10,
    ];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
