<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ánh xạ mã nhân sự trong dữ liệu nguồn sang tài khoản sử dụng hệ thống.
 * Có ngày hiệu lực vì nhân sự chuyển khoa giữa các kỳ.
 */
#[Fillable([
    'staff_code', 'full_name', 'user_id', 'department_id',
    'can_order', 'can_perform', 'effective_from', 'effective_to',
])]
class StaffDirectory extends Model
{
    protected $table = 'staff_directory';

    protected function casts(): array
    {
        return [
            'can_order' => 'boolean',
            'can_perform' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Bản ghi có hiệu lực tại một mốc thời gian, dùng để tra đúng theo kỳ quyết toán. */
    public function scopeEffectiveOn(Builder $query, ?string $date): Builder
    {
        if ($date === null) {
            return $query;
        }

        return $query
            ->where(fn (Builder $q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }
}
