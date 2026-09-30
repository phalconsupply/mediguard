<?php

namespace App\Services\Import;

use App\Models\SourceSheet;
use App\Support\Normalizer;

/**
 * Tra vị trí cột theo tên chuẩn hóa.
 *
 * Tiêu đề trùng (`NGAY_YL`, `NGAY_RA`, `MOI_MA_CP` xuất hiện nhiều lần) được giữ đủ:
 * `positionsOf()` trả về mọi vị trí, còn `value()` lấy lần xuất hiện đầu để lọc nhanh.
 * Không có cột nào bị bỏ đi — mảng ô gốc vẫn đủ 115–137 phần tử.
 */
final class ColumnIndex
{
    /** @param array<string, list<int>> $positions tiêu đề chuẩn hóa -> danh sách vị trí */
    private function __construct(private readonly array $positions) {}

    /** @param list<array{position:int, normalized_header:string}> $columns */
    public static function fromColumns(array $columns): self
    {
        $positions = [];
        foreach ($columns as $column) {
            $positions[$column['normalized_header']][] = $column['position'];
        }

        return new self($positions);
    }

    public static function fromSheet(SourceSheet $sheet): self
    {
        $positions = [];
        foreach ($sheet->columns as $column) {
            $positions[$column->normalized_header][] = $column->position;
        }

        return new self($positions);
    }

    public function has(string $header): bool
    {
        return isset($this->positions[Normalizer::header($header)]);
    }

    /** @return list<int> */
    public function positionsOf(string $header): array
    {
        return $this->positions[Normalizer::header($header)] ?? [];
    }

    public function positionOf(string $header, int $occurrence = 1): ?int
    {
        return $this->positionsOf($header)[$occurrence - 1] ?? null;
    }

    /**
     * Giá trị ô theo tên cột. Trả về null khi cột không tồn tại trong tệp này —
     * các kỳ có bộ cột khác nhau nên gọi bên ngoài phải chịu được null.
     *
     * @param  list<string>  $cells
     */
    public function value(array $cells, string $header, int $occurrence = 1): ?string
    {
        $position = $this->positionOf($header, $occurrence);
        if ($position === null) {
            return null;
        }

        return $cells[$position] ?? null;
    }

    /**
     * Giá trị đầu tiên tìm được trong danh sách tên cột ứng viên.
     * Dùng cho các trường đổi tên giữa các kỳ, ví dụ `MA_VAT_TU` và `MA_VATTU`.
     *
     * @param  list<string>  $cells
     * @param  list<string>  $headers
     */
    public function firstValue(array $cells, array $headers): ?string
    {
        foreach ($headers as $header) {
            $value = $this->value($cells, $header);
            if ($value !== null && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @return list<string> tên cột chuẩn hóa có trong tệp */
    public function headers(): array
    {
        return array_keys($this->positions);
    }
}
