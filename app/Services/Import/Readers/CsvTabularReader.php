<?php

namespace App\Services\Import\Readers;

use OpenSpout\Common\Helper\EncodingHelper;
use OpenSpout\Reader\CSV\Options;
use OpenSpout\Reader\CSV\Reader;

/**
 * Đọc CSV theo luồng.
 *
 * Mã hóa và dấu phân cách là tham số, vì mỗi lần xuất từ HIS có thể khác nhau;
 * khi không chỉ định, hệ thống dò dấu phân cách trên dòng đầu.
 */
final class CsvTabularReader implements TabularReader
{
    public function __construct(
        private readonly string $path,
        private readonly ?string $delimiter = null,
        private readonly string $encoding = EncodingHelper::ENCODING_UTF8,
        private readonly string $enclosure = '"',
    ) {}

    public function sheets(): array
    {
        return [['index' => 0, 'name' => basename($this->path)]];
    }

    public function rows(int $sheetIndex = 0): \Generator
    {
        if ($sheetIndex !== 0) {
            return;
        }

        $options = new Options;
        $options->FIELD_DELIMITER = $this->delimiter ?? self::detectDelimiter($this->path);
        $options->FIELD_ENCLOSURE = $this->enclosure;
        $options->ENCODING = $this->encoding;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        $reader = new Reader($options);
        $reader->open($this->path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                    yield $rowNumber => array_map(
                        static fn (mixed $value): string => $value === null ? '' : (string) $value,
                        $row->toArray()
                    );
                }

                return;
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * Dò dấu phân cách bằng dòng đầu tiên. Chỉ nhận các dấu thường gặp
     * và trả về dấu phẩy khi không phân biệt được, để lỗi hiện ra ở bước xem trước
     * thay vì tách nhầm cột trong im lặng.
     */
    public static function detectDelimiter(string $path): string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return ',';
        }

        $line = fgets($handle, 65536) ?: '';
        fclose($handle);
        $line = str_replace("\u{FEFF}", '', $line);

        $best = ',';
        $bestCount = 0;
        foreach ([',', ';', "\t", '|'] as $candidate) {
            $count = substr_count($line, $candidate);
            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }
}
