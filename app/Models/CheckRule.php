<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Quy tắc tự kiểm tra. Logic thật nằm ở từng phiên bản, không nằm ở đây. */
#[Fillable(['code', 'name', 'description', 'error_catalog_id', 'scope'])]
class CheckRule extends Model
{
    public function catalog(): BelongsTo
    {
        return $this->belongsTo(ErrorCatalog::class, 'error_catalog_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CheckRuleVersion::class)->orderByDesc('version');
    }

    public function activeVersion(): ?CheckRuleVersion
    {
        return $this->versions()->where('status', CheckRuleVersion::STATUS_ACTIVE)->first();
    }
}
