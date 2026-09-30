<?php

namespace App\Services\Import;

use App\Services\Import\Readers\TabularReader;
use App\Support\Normalizer;

/**
 * Nhận diện trang tính dữ liệu và dòng tiêu đề trước khi nạp.
 *
 * Kết quả luôn được đưa ra màn hình xem trước để quản lý xác nhận hoặc chọn lại;
 * hệ thống không tự nạp khi nhận diện không chắc chắn.
 */
final class SheetInspector
{
    /** Cột dùng để nhận ra trang dữ liệu phản hồi BHYT. */
    private const RESPONSE_MARKERS = ['ID_CP', 'MA_LK', 'KET_LUAN', 'GHI_CHU', 'MA_CSKCB', 'KY_QT'];

    private const MAX_HEADER_SCAN_ROWS = 20;

    /**
     * @return array{
     *     sheet_index: int,
     *     sheet_name: string,
     *     header_row_number: int,
     *     confidence: string,
     *     columns: list<array{position:int, raw_header:string, normalized_header:string, occurrence:int, internal_code:string}>,
     *     candidates: list<array{sheet_index:int, sheet_name:string, header_row_number:int, score:int}>
     * }
     */
    public function inspect(TabularReader $reader): array
    {
        $candidates = [];

        foreach ($reader->sheets() as $sheet) {
            $best = $this->bestHeaderRow($reader, $sheet['index']);
            if ($best === null) {
                continue;
            }

            $candidates[] = [
                'sheet_index' => $sheet['index'],
                'sheet_name' => $sheet['name'],
                'header_row_number' => $best['row_number'],
                'score' => $best['score'],
                'cells' => $best['cells'],
            ];
        }

        if ($candidates === []) {
            throw new \RuntimeException('Không nhận diện được trang tính dữ liệu hoặc dòng tiêu đề.');
        }

        usort($candidates, static fn (array $a, array $b) => $b['score'] <=> $a['score']);
        $winner = $candidates[0];
        $runnerUp = $candidates[1]['score'] ?? 0;

        return [
            'sheet_index' => $winner['sheet_index'],
            'sheet_name' => $winner['sheet_name'],
            'header_row_number' => $winner['header_row_number'],
            'confidence' => $this->confidence($winner['score'], $runnerUp),
            'columns' => self::buildColumns($winner['cells']),
            'candidates' => array_map(
                static fn (array $c) => [
                    'sheet_index' => $c['sheet_index'],
                    'sheet_name' => $c['sheet_name'],
                    'header_row_number' => $c['header_row_number'],
                    'score' => $c['score'],
                ],
                $candidates
            ),
        ];
    }

    /**
     * Đọc lại tiêu đề của một trang tính tại dòng do người dùng chỉ định.
     *
     * @return list<array{position:int, raw_header:string, normalized_header:string, occurrence:int, internal_code:string}>
     */
    public function columnsAt(TabularReader $reader, int $sheetIndex, int $headerRowNumber): array
    {
        foreach ($reader->rows($sheetIndex) as $rowNumber => $cells) {
            if ($rowNumber === $headerRowNumber) {
                return self::buildColumns($cells);
            }
            if ($rowNumber > $headerRowNumber) {
                break;
            }
        }

        throw new \RuntimeException("Không đọc được dòng tiêu đề số {$headerRowNumber}.");
    }

    /**
     * Giữ nguyên thứ tự và tiêu đề gốc; tiêu đề trùng được đánh số lần xuất hiện
     * để cấu hình cột và bảng ánh xạ phân biệt được `NGAY_YL` thứ nhất với thứ hai.
     *
     * @param  list<string>  $cells
     * @return list<array{position:int, raw_header:string, normalized_header:string, occurrence:int, internal_code:string}>
     */
    public static function buildColumns(array $cells): array
    {
        $seen = [];
        $columns = [];

        foreach ($cells as $position => $raw) {
            $normalized = Normalizer::header($raw);
            $seen[$normalized] = ($seen[$normalized] ?? 0) + 1;
            $occurrence = $seen[$normalized];

            $columns[] = [
                'position' => $position,
                'raw_header' => (string) $raw,
                'normalized_header' => $normalized,
                'occurrence' => $occurrence,
                'internal_code' => Normalizer::internalCode($normalized, $occurrence),
            ];
        }

        return $columns;
    }

    /**
     * Vân tay cấu trúc cột của một trang tính, dùng để phát hiện biến thể biểu mẫu
     * giữa các kỳ mà không cần so từng cột một.
     *
     * @param  list<array{internal_code:string}>  $columns
     */
    public static function fingerprint(array $columns): string
    {
        return hash('sha256', implode('|', array_column($columns, 'internal_code')));
    }

    /** @return array{row_number:int, score:int, cells:list<string>}|null */
    private function bestHeaderRow(TabularReader $reader, int $sheetIndex): ?array
    {
        $best = null;

        foreach ($reader->rows($sheetIndex) as $rowNumber => $cells) {
            if ($rowNumber > self::MAX_HEADER_SCAN_ROWS) {
                break;
            }

            $score = $this->scoreHeaderRow($cells);
            if ($score > 0 && ($best === null || $score > $best['score'])) {
                $best = ['row_number' => $rowNumber, 'score' => $score, 'cells' => $cells];
            }
        }

        return $best;
    }

    /** @param list<string> $cells */
    private function scoreHeaderRow(array $cells): int
    {
        $normalized = array_map(static fn ($c) => Normalizer::header($c), $cells);
        $filled = count(array_filter($normalized, static fn ($c) => $c !== ''));
        if ($filled < 5) {
            return 0;
        }

        $markers = count(array_intersect(self::RESPONSE_MARKERS, $normalized));

        // Tiêu đề thường là chuỗi mã in hoa, không phải số.
        $codeLike = count(array_filter(
            $normalized,
            static fn ($c) => $c !== '' && preg_match('/^[A-Z0-9_ ]+$/', $c) === 1 && ! is_numeric($c)
        ));

        return $markers * 20 + $codeLike + $filled;
    }

    private function confidence(int $winner, int $runnerUp): string
    {
        if ($winner >= 100 && $winner >= $runnerUp * 2) {
            return 'high';
        }

        return $winner > $runnerUp ? 'medium' : 'low';
    }
}
