<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mã dịch vụ -> đơn vị thực hiện, dùng khi dòng chi phí thiếu mã người thực hiện. */
#[Fillable(['service_code', 'service_name', 'department_id', 'effective_from', 'effective_to'])]
class ServiceOwner extends Model
{
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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
