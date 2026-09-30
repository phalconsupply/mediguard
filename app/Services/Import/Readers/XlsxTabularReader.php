<?php

namespace App\Services\Import\Readers;

use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Đọc XLSX theo luồng bằng OpenSpout.
 *
 * Giữ dòng rỗng để số dòng trả về luôn khớp số dòng trong tệp gốc — đây là
 * điều kiện để dòng nguồn có khóa bất biến và để bước xuất ghép lại đúng vị trí.
 */
final class XlsxTabularReader implements TabularReader
{
    public function __construct(private readonly string $path) {}

    public function sheets(): array
    {
        $reader = $this->open();
        $sheets = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $sheets[] = [
                    'index' => $sheet->getIndex(),
                    'name' => $sheet->getName(),
                ];
            }
        } finally {
            $reader->close();
        }

        return $sheets;
    }

    public function rows(int $sheetIndex = 0): \Generator
    {
        $reader = $this->open();

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getIndex() !== $sheetIndex) {
                    continue;
                }

                foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                    yield $rowNumber => $this->flatten($row);
                }

                return;
            }
        } finally {
            $reader->close();
        }
    }

    private function open(): Reader
    {
        $options = new Options;
        // Giữ nguyên chuỗi ngày/số như trong tệp; việc diễn giải do Normalizer đảm nhận.
        $options->SHOULD_FORMAT_DATES = false;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        $reader = new Reader($options);
        $reader->open($this->path);

        return $reader;
    }

    /** @return list<string> */
    private function flatten(Row $row): array
    {
        return array_map(static function (mixed $value): string {
            if ($value === null) {
                return '';
            }
            if ($value instanceof DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            return (string) $value;
        }, $row->toArray());
    }
}
