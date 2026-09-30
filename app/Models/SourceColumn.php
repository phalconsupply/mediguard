<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một cột của trang dữ liệu, giữ nguyên tiêu đề gốc và vị trí trong tệp. */
#[Fillable([
    'source_sheet_id', 'position', 'raw_header', 'normalized_header', 'occurrence', 'internal_code',
])]
class SourceColumn extends Model
{
    public function sheet(): BelongsTo
    {
        return $this->belongsTo(SourceSheet::class, 'source_sheet_id');
    }

    /** Nhãn hiển thị; tiêu đề trùng kèm vị trí gốc để người dùng phân biệt. */
    public function label(): string
    {
        $base = trim((string) $this->raw_header) !== '' ? trim((string) $this->raw_header) : '(không tiêu đề)';

        return $this->occurrence > 1 ? sprintf('%s (cột %d)', $base, $this->position + 1) : $base;
    }
}
