<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tệp nguồn được lưu bất biến trong kho; DB chỉ giữ đường dẫn, hash và kết quả nhận diện. */
#[Fillable([
    'import_batch_id', 'original_name', 'stored_path', 'sha256', 'size_bytes', 'format',
    'error_code', 'status', 'failure_reason', 'detected', 'ingested_at',
])]
class SourceFile extends Model
{
    protected function casts(): array
    {
        return [
            'detected' => 'array',
            'ingested_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function sheets(): HasMany
    {
        return $this->hasMany(SourceSheet::class);
    }

    public function dataSheet(): ?SourceSheet
    {
        return $this->sheets()->where('is_data_sheet', true)->first();
    }

    public function issueCases(): HasMany
    {
        return $this->hasMany(IssueCase::class);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(ExportArtifact::class);
    }

    /**
     * Mã lỗi nằm trong tên tệp theo dạng `TACH_9065_...`.
     * Trả về null khi tên tệp không theo mẫu; khi đó quản lý phải chọn mã thủ công.
     */
    public static function detectErrorCode(string $fileName): ?string
    {
        return preg_match('/TACH[_\-\s]*(\d{3,5})/i', $fileName, $m) ? $m[1] : null;
    }
}
