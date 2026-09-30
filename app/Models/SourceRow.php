<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Một dòng của trang dữ liệu nguồn.
 *
 * `cells` giữ đủ 115–137 ô theo đúng thứ tự cột gốc nên quản lý tick chọn hiển thị
 * cột nào cũng được mà không cần đổi lược đồ; các cột lọc thường dùng được tách riêng.
 */
#[Fillable([
    'source_sheet_id', 'import_batch_id', 'row_number', 'row_type', 'cells',
    'id_cp', 'ma_lk', 'ma_khoa', 'ma_bac_si', 'ma_bsi_thuc_hien', 'ma_cp', 'loai_cp',
    'ket_luan', 'ghi_chu', 'tien_de_nghi_kiem_tra', 'thanh_tien_bh', 't_bhtt_dtl',
])]
class SourceRow extends Model
{
    public const TYPE_COST = 'cost';

    public const TYPE_SUMMARY = 'summary';

    public const TYPE_BLANK = 'blank';

    protected function casts(): array
    {
        return [
            'cells' => 'array',
            'tien_de_nghi_kiem_tra' => 'decimal:2',
            'thanh_tien_bh' => 'decimal:2',
            't_bhtt_dtl' => 'decimal:2',
        ];
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(SourceSheet::class, 'source_sheet_id');
    }

    public function issueCase(): HasOne
    {
        return $this->hasOne(IssueCase::class);
    }

    public function cellAt(int $position): ?string
    {
        $cells = $this->cells ?? [];

        return $cells[$position] ?? null;
    }
}
