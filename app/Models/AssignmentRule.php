<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mã lỗi -> thứ tự chọn người chịu trách nhiệm, có ngày hiệu lực theo kỳ. */
#[Fillable([
    'error_code', 'resolver_chain', 'fallback_department_id',
    'outpatient_mode', 'due_days', 'effective_from', 'effective_to',
])]
class AssignmentRule extends Model
{
    public const MODE_PER_PERSON = 'per_person';

    public const MODE_POOL = 'pool';

    protected function casts(): array
    {
        return [
            'resolver_chain' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function fallbackDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'fallback_department_id');
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
}
