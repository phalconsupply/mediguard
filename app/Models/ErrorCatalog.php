<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Danh mục lỗi. Giữ được cả lỗi chưa viết thành quy tắc máy:
 * trạng thái `pending_review` vẫn phân công và giải trình bình thường.
 */
#[Fillable([
    'code', 'name', 'description', 'group', 'responsible_role', 'status',
    'first_seen_period', 'priority', 'default_columns', 'explanation_template', 'owner_user_id',
])]
class ErrorCatalog extends Model
{
    public const ROLE_ORDERER = 'orderer';      // người chỉ định / kê đơn

    public const ROLE_PERFORMER = 'performer';  // người thực hiện dịch vụ

    public const ROLE_BOTH = 'both';

    public const ROLE_UNKNOWN = 'unknown';

    public const STATUS_PENDING = 'pending_review';

    protected function casts(): array
    {
        return ['default_columns' => 'array'];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ErrorCatalogVariant::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(CheckRule::class);
    }

    public function displayName(): string
    {
        return $this->name ? "{$this->code} — {$this->name}" : $this->code;
    }
}
