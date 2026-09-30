<?php

namespace App\Services\Import\Readers;

/**
 * Hợp đồng chung cho bộ đọc XLSX và CSV.
 *
 * Bộ đọc luôn trả về dòng theo luồng (Generator) để tệp 80 MB không nằm hết trong RAM,
 * và luôn giữ nguyên số dòng gốc để dòng nguồn có khóa bất biến.
 */
interface TabularReader
{
    /**
     * Liệt kê các trang tính của tệp.
     *
     * @return list<array{index: int, name: string}>
     */
    public function sheets(): array;

    /**
     * Đọc tuần tự từng dòng thô của một trang tính, kể cả dòng tiêu đề.
     *
     * @return \Generator<int, list<string>> khóa là số dòng gốc (1-based)
     */
    public function rows(int $sheetIndex = 0): \Generator;
}
