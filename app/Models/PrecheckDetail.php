<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dòng chi tiết đã chuẩn hóa: thuốc (XML2), DVKT/VTYT (XML3), cận lâm sàng (XML4), diễn biến (XML5). */
#[Fillable([
    'import_batch_id', 'source_file_id', 'precheck_encounter_id', 'ma_lk', 'detail_kind', 'loai_ho_so',
    'stt', 'ma_dich_vu', 'ma_vat_tu', 'ma_thuoc', 'ten', 'ma_chi_so', 'gia_tri', 'ma_bac_si',
    'nguoi_thuc_hien', 'ma_may', 'ma_khoa', 'so_luong', 'thanh_tien',
    'ngay_yl', 'ngay_th_yl', 'ngay_kq', 'time_precision', 'locator', 'extra',
])]
class PrecheckDetail extends Model
{
    public const KIND_THUOC = 'thuoc';

    public const KIND_DVKT = 'dvkt';

    public const KIND_CLS = 'cls';

    public const KIND_DIEN_BIEN = 'dien_bien';

    public const KIND_KHAC = 'khac';

    protected function casts(): array
    {
        return [
            'so_luong' => 'decimal:4',
            'thanh_tien' => 'decimal:2',
            'ngay_yl' => 'datetime',
            'ngay_th_yl' => 'datetime',
            'ngay_kq' => 'datetime',
            'locator' => 'array',
            'extra' => 'array',
        ];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(PrecheckEncounter::class, 'precheck_encounter_id');
    }
}
