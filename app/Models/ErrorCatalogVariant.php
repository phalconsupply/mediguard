<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cùng mã lỗi nhưng mô tả hoặc cấu trúc cột đã đổi -> biến thể riêng, không gộp tự động. */
#[Fillable([
    'error_catalog_id', 'signature', 'period', 'sample_note', 'column_fingerprint',
    'row_count', 'amount_reference', 'status', 'source_file_id',
])]
class ErrorCatalogVariant extends Model
{
    protected function casts(): array
    {
        return [
            'column_fingerprint' => 'array',
            'amount_reference' => 'decimal:2',
        ];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(ErrorCatalog::class, 'error_catalog_id');
    }

    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(SourceFile::class);
    }
}
