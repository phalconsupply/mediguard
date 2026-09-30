<?php

namespace App\Services\Export;

use App\Models\IssueCase;
use App\Models\SourceFile;
use App\Models\SourceRow;
use App\Models\SourceSheet;
use App\Services\Import\Readers\CsvTabularReader;
use App\Services\Import\Readers\XlsxTabularReader;
use App\Support\Normalizer;
use Illuminate\Support\Facades\DB;

/**
 * Kiểm soát chất lượng bắt buộc quanh việc xuất trả.
 *
 * Tách làm hai bước: `preflight()` chạy trước khi sinh tệp (đủ giải trình chưa),
 * `verify()` đọc lại chính tệp vừa sinh để đối chiếu số dòng, số cột và từng ô nguồn.
 * Lỗi "chặn" dừng việc công bố; "cảnh báo" chỉ ghi lại để quản lý xem.
 */
final class ExportVerifier
{
    /** Số dòng lấy mẫu để so từng ô khi tệp lớn. */
    private const SAMPLE_ROWS = 200;

    /**
     * @return array{blocking: list<string>, warnings: list<string>, counters: array<string, int>}
     */
    public function preflight(SourceFile $file): array
    {
        $blocking = [];
        $warnings = [];

        $counters = DB::table('issue_cases')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN requires_explanation = 1 THEN 1 ELSE 0 END) as needs')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved', [IssueCase::STATUS_APPROVED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as exported', [IssueCase::STATUS_EXPORTED])
            ->where('source_file_id', $file->id)
            ->first();

        $needs = (int) ($counters->needs ?? 0);
        $settled = (int) ($counters->approved ?? 0) + (int) ($counters->exported ?? 0);

        // Mỗi dòng từ chối/cảnh báo phải có đúng một bản giải trình được duyệt.
        // 503 dòng "Chấp nhận" của 5073 và các dòng tổng hợp không nằm trong số này.
        if ($settled < $needs) {
            $blocking[] = sprintf(
                'Còn %d dòng thuộc diện phản hồi chưa có bản giải trình được duyệt (%d/%d).',
                $needs - $settled,
                $settled,
                $needs
            );
        }

        $duplicateApprovals = DB::table('explanations as e')
            ->join('issue_cases as c', 'c.id', '=', 'e.issue_case_id')
            ->where('c.source_file_id', $file->id)
            ->where('e.status', 'approved')
            ->groupBy('e.issue_case_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicateApprovals > 0) {
            $blocking[] = "Có {$duplicateApprovals} vụ việc mang nhiều hơn một bản giải trình được duyệt.";
        }

        $sheet = $file->dataSheet();
        if ($sheet === null) {
            $blocking[] = 'Tệp chưa nạp xong trang dữ liệu.';
        } elseif ($sheet->row_count === 0) {
            $warnings[] = 'Trang dữ liệu không có dòng nào sau dòng tiêu đề.';
        }

        return [
            'blocking' => $blocking,
            'warnings' => $warnings,
            'counters' => [
                'cases_total' => (int) ($counters->total ?? 0),
                'cases_need_explanation' => $needs,
                'cases_settled' => $settled,
            ],
        ];
    }

    /**
     * Đọc lại tệp vừa sinh và đối chiếu với dữ liệu nguồn đã lưu.
     *
     * @param  array{rows_seen:int, rows_written:int, skipped_rows: list<int>}  $writeResult
     * @param  array<int, string>  $explanations
     * @param  array{blocking: list<string>, warnings: list<string>, counters: array<string,int>}  $preflight
     * @return array{blocking: list<string>, warnings: list<string>, checks: array<string, mixed>}
     */
    public function verify(
        SourceFile $file,
        SourceSheet $sheet,
        string $exportedPath,
        array $writeResult,
        array $explanations,
        array $preflight,
    ): array {
        $blocking = $preflight['blocking'];
        $warnings = $preflight['warnings'];

        if ($writeResult['skipped_rows'] !== []) {
            $blocking[] = sprintf(
                'Không chèn được nội dung giải trình vào %d dòng vì trang tính đã có ô ở vị trí cột mới: %s.',
                count($writeResult['skipped_rows']),
                implode(', ', array_slice($writeResult['skipped_rows'], 0, 10))
            );
        }

        $reader = $file->format === 'xlsx'
            ? new XlsxTabularReader($exportedPath)
            : new CsvTabularReader($exportedPath);

        $expectedColumns = $sheet->column_count + 1;
        $headerChecked = false;
        $rowsSeen = 0;
        $valuesWritten = 0;
        $cellMismatches = [];
        $widthProblems = 0;

        $sampleRows = $this->sampleRowNumbers($sheet);
        $samples = $this->loadSamples($sheet, $sampleRows);

        foreach ($reader->rows($sheet->sheet_index) as $rowNumber => $cells) {
            if ($rowNumber === $sheet->header_row_number) {
                $headerChecked = true;
                if (Normalizer::header($cells[$sheet->column_count] ?? '') !== Normalizer::header(ExplanationExporter::COLUMN_HEADER)) {
                    $blocking[] = 'Cột cuối của trang dữ liệu không mang đúng tiêu đề "'.ExplanationExporter::COLUMN_HEADER.'".';
                }
            }

            if ($rowNumber <= $sheet->header_row_number) {
                continue;
            }

            $rowsSeen++;

            if (count($cells) > $expectedColumns) {
                $widthProblems++;
            }

            $appended = $cells[$sheet->column_count] ?? '';
            if (trim((string) $appended) !== '') {
                $valuesWritten++;
            }

            if (isset($samples[$rowNumber])) {
                $mismatch = $this->compareCells($samples[$rowNumber], $cells, $sheet->column_count);
                if ($mismatch !== null) {
                    $cellMismatches[] = "dòng {$rowNumber}: {$mismatch}";
                }
                unset($samples[$rowNumber]);
            }
        }

        if (! $headerChecked) {
            $blocking[] = 'Không đọc lại được dòng tiêu đề trong tệp xuất.';
        }

        if ($rowsSeen !== $sheet->row_count) {
            $blocking[] = sprintf(
                'Số dòng dữ liệu trong tệp xuất (%d) khác số dòng đã nạp (%d).',
                $rowsSeen,
                $sheet->row_count
            );
        }

        $expectedValues = count($explanations);
        if ($valuesWritten !== $expectedValues) {
            $blocking[] = sprintf(
                'Số ô giải trình ghi được (%d) khác số bản giải trình đã duyệt (%d).',
                $valuesWritten,
                $expectedValues
            );
        }

        if ($cellMismatches !== []) {
            $blocking[] = 'Giá trị ô nguồn bị thay đổi ở '.count($cellMismatches).' dòng lấy mẫu: '
                .implode('; ', array_slice($cellMismatches, 0, 5)).'.';
        }

        if ($widthProblems > 0) {
            $warnings[] = "Có {$widthProblems} dòng rộng hơn số cột dự kiến; cần xem lại trang tính nguồn.";
        }

        if ($samples !== []) {
            $warnings[] = 'Không đọc lại được '.count($samples).' dòng lấy mẫu trong tệp xuất.';
        }

        return [
            'blocking' => array_values(array_unique($blocking)),
            'warnings' => array_values(array_unique($warnings)),
            'checks' => [
                'source_rows' => $sheet->row_count,
                'exported_rows' => $rowsSeen,
                'source_columns' => $sheet->column_count,
                'exported_columns' => $expectedColumns,
                'explanations_expected' => $expectedValues,
                'explanations_written' => $valuesWritten,
                'sampled_rows' => count($sampleRows),
                'counters' => $preflight['counters'],
                'sha256' => hash_file('sha256', $exportedPath),
            ],
        ];
    }

    /**
     * Lấy mẫu đầu, giữa và cuối trang dữ liệu để so từng ô mà không phải đọc lại toàn bộ.
     *
     * @return list<int>
     */
    private function sampleRowNumbers(SourceSheet $sheet): array
    {
        $first = $sheet->header_row_number + 1;
        $last = $sheet->header_row_number + $sheet->row_count;
        if ($last < $first) {
            return [];
        }

        $total = $last - $first + 1;
        if ($total <= self::SAMPLE_ROWS) {
            return range($first, $last);
        }

        $step = (int) max(1, floor($total / self::SAMPLE_ROWS));
        $rows = [];
        for ($row = $first; $row <= $last && count($rows) < self::SAMPLE_ROWS; $row += $step) {
            $rows[] = $row;
        }
        $rows[] = $last;

        return array_values(array_unique($rows));
    }

    /**
     * @param  list<int>  $rowNumbers
     * @return array<int, list<string>>
     */
    private function loadSamples(SourceSheet $sheet, array $rowNumbers): array
    {
        if ($rowNumbers === []) {
            return [];
        }

        return SourceRow::query()
            ->where('source_sheet_id', $sheet->id)
            ->whereIn('row_number', $rowNumbers)
            ->get(['row_number', 'cells'])
            ->mapWithKeys(fn (SourceRow $row) => [$row->row_number => $row->cells ?? []])
            ->all();
    }

    /**
     * @param  list<string>  $expected
     * @param  list<string>  $actual
     */
    private function compareCells(array $expected, array $actual, int $columnCount): ?string
    {
        for ($i = 0; $i < $columnCount; $i++) {
            $left = trim((string) ($expected[$i] ?? ''));
            $right = trim((string) ($actual[$i] ?? ''));

            if ($left !== $right) {
                return sprintf('cột %d ("%s" -> "%s")', $i + 1, mb_strimwidth($left, 0, 40, '…'), mb_strimwidth($right, 0, 40, '…'));
            }
        }

        return null;
    }
}
