<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Hồ sơ cấp lượt KCB đã chuẩn hóa (XML1/TONG_HOP hoặc bảng tương đương của XLSX/CSV). */
#[Fillable([
    'import_batch_id', 'source_file_id', 'ma_lk', 'ma_bn', 'ma_the', 'ma_khoa',
    'ma_benh_chinh', 'ma_benh_kt', 'ma_loai_kcb', 'ngay_vao', 'ngay_ra',
    't_tongchi', 't_bhtt', 'locator', 'extra',
])]
class PrecheckEncounter extends Model
{
    protected function casts(): array
    {
        return [
            'ngay_vao' => 'datetime',
            'ngay_ra' => 'datetime',
            't_tongchi' => 'decimal:2',
            't_bhtt' => 'decimal:2',
            'locator' => 'array',
            'extra' => 'array',
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(PrecheckDetail::class);
    }

    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(SourceFile::class);
    }
}
