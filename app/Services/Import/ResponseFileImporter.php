<?php

namespace App\Services\Import;

use App\Models\ErrorCatalog;
use App\Models\SourceColumn;
use App\Models\SourceFile;
use App\Models\SourceRow;
use App\Models\SourceSheet;
use App\Services\Catalog\ErrorCatalogLearner;
use App\Services\Import\Readers\CsvTabularReader;
use App\Services\Import\Readers\TabularReader;
use App\Services\Import\Readers\XlsxTabularReader;
use App\Support\Normalizer;
use Illuminate\Support\Facades\DB;

/**
 * Nạp một tệp phản hồi BHYT (XLSX hoặc CSV) vào bảng dòng nguồn.
 *
 * Nguyên tắc:
 *  - Giữ nguyên mọi ô, mọi cột và đúng số dòng gốc. Không gộp theo tên cột.
 *  - Dòng tổng hợp (không có ID_CP nhưng có số liệu) được đánh dấu riêng, vẫn giữ lại.
 *  - Chỉ tách thêm một số trường phục vụ lọc; phần còn lại nằm trong mảng `cells`.
 */
final class ResponseFileImporter
{
    /** Số dòng ghi mỗi lần để tệp 80 MB không nằm hết trong RAM. */
    private const CHUNK = 500;

    public function __construct(
        private readonly SheetInspector $inspector,
        private readonly ErrorCatalogLearner $learner,
    ) {}

    /**
     * @param  array{sheet_index?: int, header_row_number?: int}  $overrides  lựa chọn của quản lý khi nhận diện sai
     * @return array{rows: int, cost_rows: int, summary_rows: int, columns: int, catalog: ErrorCatalog}
     */
    public function import(SourceFile $file, string $absolutePath, array $overrides = []): array
    {
        $reader = $this->readerFor($file, $absolutePath);

        $sheetIndex = $overrides['sheet_index'] ?? null;
        $headerRow = $overrides['header_row_number'] ?? null;

        if ($sheetIndex === null || $headerRow === null) {
            $detected = $this->inspector->inspect($reader);
            $sheetIndex ??= $detected['sheet_index'];
            $headerRow ??= $detected['header_row_number'];
            $columns = $detected['columns'];
            $sheetName = $detected['sheet_name'];
            $file->detected = $detected;
        } else {
            $columns = $this->inspector->columnsAt($reader, $sheetIndex, $headerRow);
            $sheetName = collect($reader->sheets())->firstWhere('index', $sheetIndex)['name'] ?? null;
            $file->detected = array_merge($file->detected ?? [], [
                'overridden_by_user' => true,
                'sheet_index' => $sheetIndex,
                'header_row_number' => $headerRow,
            ]);
        }

        $sheet = SourceSheet::create([
            'source_file_id' => $file->id,
            'sheet_index' => $sheetIndex,
            'sheet_name' => $sheetName,
            'header_row_number' => $headerRow,
            'column_count' => count($columns),
            'row_count' => 0,
            'is_data_sheet' => true,
            'column_fingerprint' => SheetInspector::fingerprint($columns),
        ]);

        SourceColumn::insert(array_map(
            static fn (array $c) => $c + ['source_sheet_id' => $sheet->id, 'created_at' => now(), 'updated_at' => now()],
            $columns
        ));

        $index = ColumnIndex::fromColumns($columns);
        $stats = $this->loadRows($file, $sheet, $reader, $sheetIndex, $headerRow, $index);

        $sheet->update(['row_count' => $stats['rows']]);

        $catalog = $this->learner->register($file, $sheet, $columns, $stats);

        $file->fill([
            'status' => 'ready',
            'ingested_at' => now(),
            'detected' => array_merge($file->detected ?? [], ['stats' => $stats]),
        ])->save();

        return $stats + ['columns' => count($columns), 'catalog' => $catalog];
    }

    /** @return array{rows:int, cost_rows:int, summary_rows:int, blank_rows:int, conclusions: array<string,int>, amount_total: float} */
    private function loadRows(
        SourceFile $file,
        SourceSheet $sheet,
        TabularReader $reader,
        int $sheetIndex,
        int $headerRow,
        ColumnIndex $index,
    ): array {
        $buffer = [];
        $stats = [
            'rows' => 0,
            'cost_rows' => 0,
            'summary_rows' => 0,
            'blank_rows' => 0,
            'conclusions' => [],
            'amount_total' => 0.0,
        ];

        foreach ($reader->rows($sheetIndex) as $rowNumber => $cells) {
            if ($rowNumber <= $headerRow) {
                continue;
            }

            // Cắt/đệm về đúng số cột đã khai báo để mảng ô luôn khớp thứ tự cột.
            $cells = array_slice($cells, 0, $sheet->column_count);
            $cells = array_pad($cells, $sheet->column_count, '');

            $rowType = $this->classifyRow($cells, $index);
            $stats['rows']++;
            $stats[$rowType === SourceRow::TYPE_COST ? 'cost_rows' : ($rowType === SourceRow::TYPE_SUMMARY ? 'summary_rows' : 'blank_rows')]++;

            $ketLuan = $index->value($cells, 'KET_LUAN');
            $conclusion = Normalizer::conclusion($ketLuan);
            $stats['conclusions'][$conclusion] = ($stats['conclusions'][$conclusion] ?? 0) + 1;

            $amount = Normalizer::money($index->value($cells, 'TIEN_DE_NGHI_KIEM_TRA'));
            if ($rowType === SourceRow::TYPE_COST && $amount !== null) {
                $stats['amount_total'] += $amount;
            }

            $buffer[] = [
                'source_sheet_id' => $sheet->id,
                'import_batch_id' => $file->import_batch_id,
                'row_number' => $rowNumber,
                'row_type' => $rowType,
                'cells' => json_encode($cells, JSON_UNESCAPED_UNICODE),
                'id_cp' => Normalizer::text($index->value($cells, 'ID_CP')),
                'ma_lk' => Normalizer::text($index->value($cells, 'MA_LK')),
                'ma_khoa' => Normalizer::code($index->value($cells, 'MA_KHOA')),
                'ma_bac_si' => Normalizer::code($index->value($cells, 'MA_BAC_SI')),
                'ma_bsi_thuc_hien' => Normalizer::code($index->value($cells, 'MA_BSI_THUC_HIEN')),
                'ma_cp' => Normalizer::code($index->value($cells, 'MA_CP')),
                'loai_cp' => Normalizer::text($index->value($cells, 'LOAI_CP')),
                'ket_luan' => Normalizer::text($ketLuan),
                'ghi_chu' => Normalizer::text($index->value($cells, 'GHI_CHU')),
                'tien_de_nghi_kiem_tra' => $amount,
                'thanh_tien_bh' => Normalizer::money($index->value($cells, 'THANH_TIEN_BH')),
                't_bhtt_dtl' => Normalizer::money($index->value($cells, 'T_BHTT_DTL')),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($buffer) >= self::CHUNK) {
                DB::table('source_rows')->insert($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            DB::table('source_rows')->insert($buffer);
        }

        return $stats;
    }

    /**
     * Ba loại dòng trong mẫu khảo sát: dòng chi phí, dòng tổng hợp cuối tệp (không có ID_CP)
     * và dòng rỗng. Dòng tổng hợp không phải việc giải trình nhưng phải giữ nguyên khi xuất lại.
     *
     * @param  list<string>  $cells
     */
    private function classifyRow(array $cells, ColumnIndex $index): string
    {
        $filled = array_filter($cells, static fn ($c) => trim((string) $c) !== '');
        if ($filled === []) {
            return SourceRow::TYPE_BLANK;
        }

        $idCp = trim((string) $index->value($cells, 'ID_CP'));

        return $idCp === '' ? SourceRow::TYPE_SUMMARY : SourceRow::TYPE_COST;
    }

    private function readerFor(SourceFile $file, string $path): TabularReader
    {
        return match ($file->format) {
            'xlsx' => new XlsxTabularReader($path),
            'csv' => new CsvTabularReader($path),
            default => throw new \InvalidArgumentException("Định dạng {$file->format} không dùng cho luồng phản hồi BHYT."),
        };
    }
}
