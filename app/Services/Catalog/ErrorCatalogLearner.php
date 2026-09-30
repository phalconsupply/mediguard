<?php

namespace App\Services\Catalog;

use App\Models\ErrorCatalog;
use App\Models\ErrorCatalogVariant;
use App\Models\SourceFile;
use App\Models\SourceRow;
use App\Models\SourceSheet;
use App\Services\Import\SheetInspector;

/**
 * Học mã lỗi và biến thể từ mỗi đợt phản hồi.
 *
 * Mã chưa có trong thư viện được ghi nhận là "lỗi mới chờ phân loại" và vẫn đi vào
 * luồng giải trình ngay trong kỳ. Cùng mã nhưng mô tả hoặc cấu trúc cột đã đổi thì
 * mở biến thể mới — không gộp tự động chỉ vì trùng mã.
 */
final class ErrorCatalogLearner
{
    /**
     * @param  list<array{internal_code:string, normalized_header:string}>  $columns
     * @param  array<string, mixed>  $stats
     */
    public function register(SourceFile $file, SourceSheet $sheet, array $columns, array $stats): ErrorCatalog
    {
        $code = $file->error_code ?? 'UNKNOWN';

        $catalog = ErrorCatalog::firstOrCreate(
            ['code' => $code],
            [
                'name' => $this->guessName($file),
                'status' => ErrorCatalog::STATUS_PENDING,
                'responsible_role' => ErrorCatalog::ROLE_UNKNOWN,
                'first_seen_period' => $file->batch?->period,
                'priority' => 3,
            ]
        );

        $sampleNote = $this->sampleNote($sheet);
        $signature = $this->signature($columns, $sampleNote);

        $variant = ErrorCatalogVariant::firstOrNew([
            'error_catalog_id' => $catalog->id,
            'signature' => $signature,
        ]);

        $isNewVariant = ! $variant->exists;

        $variant->fill([
            'period' => $file->batch?->period,
            'sample_note' => $sampleNote,
            'column_fingerprint' => [
                'hash' => SheetInspector::fingerprint($columns),
                'count' => count($columns),
                'headers' => array_column($columns, 'normalized_header'),
            ],
            'row_count' => $stats['cost_rows'] ?? 0,
            'amount_reference' => $stats['amount_total'] ?? null,
            'source_file_id' => $file->id,
            'status' => $isNewVariant ? 'new' : $variant->status,
        ])->save();

        return $catalog;
    }

    /**
     * Vân tay của một biến thể: cấu trúc cột cộng với khuôn mô tả lỗi.
     * Khuôn được lấy từ `GHI_CHU` sau khi bỏ phần thay đổi theo từng dòng
     * (tên thuốc, mã, số) để hai kỳ cùng một loại lỗi không sinh biến thể giả.
     *
     * @param  list<array{internal_code:string}>  $columns
     */
    private function signature(array $columns, ?string $sampleNote): string
    {
        return hash('sha256', SheetInspector::fingerprint($columns).'|'.$this->noteShape($sampleNote));
    }

    private function noteShape(?string $note): string
    {
        if ($note === null || $note === '') {
            return '';
        }

        $shape = preg_replace('/\[[^\]]*\]/u', '[]', $note) ?? $note;  // bỏ nội dung trong ngoặc vuông
        $shape = preg_replace('/\d+/u', '#', $shape) ?? $shape;        // bỏ mọi con số
        $shape = preg_replace('/\s+/u', ' ', $shape) ?? $shape;

        return mb_substr(trim($shape), 0, 400, 'UTF-8');
    }

    /** Lấy một ghi chú mẫu từ dòng chi phí đầu tiên để mô tả biến thể. */
    private function sampleNote(SourceSheet $sheet): ?string
    {
        return SourceRow::query()
            ->where('source_sheet_id', $sheet->id)
            ->where('row_type', SourceRow::TYPE_COST)
            ->whereNotNull('ghi_chu')
            ->orderBy('row_number')
            ->value('ghi_chu');
    }

    /**
     * Tên gợi ý lấy từ tên tệp `TACH_9065_TỪ_CHỐI_DV_...`.
     * Quản lý được đặt lại tên dễ hiểu; đây chỉ là nhãn tạm để nhận ra lỗi mới.
     */
    private function guessName(SourceFile $file): ?string
    {
        $name = pathinfo($file->original_name, PATHINFO_FILENAME);
        $name = preg_replace('/^TACH[_\-\s]*\d+[_\-\s]*/iu', '', $name) ?? $name;
        $name = preg_replace('/[_\-]+\d{4,6}$/u', '', $name) ?? $name;   // bỏ mã cơ sở ở cuối
        $name = preg_replace('/\.xlsx.*$/iu', '', $name) ?? $name;
        $name = trim(str_replace('_', ' ', $name));

        return $name === '' ? null : mb_substr($name, 0, 250, 'UTF-8');
    }
}
