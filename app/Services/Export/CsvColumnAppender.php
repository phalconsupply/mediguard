<?php

namespace App\Services\Export;

/**
 * Nối một cột vào cuối tệp CSV, giữ nguyên từng byte của bản ghi gốc.
 *
 * Không đọc rồi ghi lại bằng bộ tạo CSV vì như vậy dấu nháy, khoảng trắng và
 * cách xuống dòng của tệp gốc sẽ đổi. Thay vào đó bộ này dò biên bản ghi bằng
 * trạng thái trong/ngoài dấu nháy rồi chèn thêm một trường trước ký tự xuống dòng.
 */
final class CsvColumnAppender
{
    private const READ_CHUNK = 262144;

    /**
     * @param  callable(int $recordNumber): ?string  $valueForRow
     * @return array{rows_seen:int, rows_written:int, skipped_rows: list<int>}
     */
    public function append(
        string $sourcePath,
        string $targetPath,
        int $headerRowNumber,
        string $headerText,
        callable $valueForRow,
        string $delimiter = ',',
        string $enclosure = '"',
    ): array {
        $in = fopen($sourcePath, 'rb');
        $out = fopen($targetPath, 'wb');
        if ($in === false || $out === false) {
            throw new \RuntimeException('Không mở được tệp CSV để ghi cột kết quả.');
        }

        $recordNumber = 0;
        $rowsWritten = 0;
        $inQuotes = false;
        $record = '';

        try {
            while (($chunk = fread($in, self::READ_CHUNK)) !== false && $chunk !== '') {
                $length = strlen($chunk);

                for ($i = 0; $i < $length; $i++) {
                    $char = $chunk[$i];
                    $record .= $char;

                    if ($char === $enclosure) {
                        $inQuotes = ! $inQuotes;

                        continue;
                    }

                    if ($inQuotes || $char !== "\n") {
                        continue;
                    }

                    $recordNumber++;
                    $isHeader = $recordNumber === $headerRowNumber;
                    $value = $isHeader ? $headerText : $valueForRow($recordNumber);
                    fwrite($out, $this->withExtraField($record, $value, $delimiter, $enclosure));
                    if (! $isHeader && $value !== null && $value !== '') {
                        $rowsWritten++;
                    }
                    $record = '';
                }
            }

            // Bản ghi cuối có thể không kết thúc bằng ký tự xuống dòng.
            if (trim($record) !== '') {
                $recordNumber++;
                $isHeader = $recordNumber === $headerRowNumber;
                $value = $isHeader ? $headerText : $valueForRow($recordNumber);
                fwrite($out, $this->withExtraField($record, $value, $delimiter, $enclosure));
                if (! $isHeader && $value !== null && $value !== '') {
                    $rowsWritten++;
                }
            }
        } finally {
            fclose($in);
            fclose($out);
        }

        return ['rows_seen' => $recordNumber, 'rows_written' => $rowsWritten, 'skipped_rows' => []];
    }

    /** Chèn trường mới trước ký tự xuống dòng, giữ nguyên kiểu xuống dòng của tệp gốc. */
    private function withExtraField(string $record, ?string $value, string $delimiter, string $enclosure): string
    {
        $newline = '';
        $body = $record;

        if (str_ends_with($body, "\r\n")) {
            $newline = "\r\n";
            $body = substr($body, 0, -2);
        } elseif (str_ends_with($body, "\n")) {
            $newline = "\n";
            $body = substr($body, 0, -1);
        }

        return $body.$delimiter.$this->quote($value ?? '', $delimiter, $enclosure).$newline;
    }

    private function quote(string $value, string $delimiter, string $enclosure): string
    {
        if ($value === '') {
            return '';
        }

        $needsQuotes = str_contains($value, $delimiter)
            || str_contains($value, $enclosure)
            || str_contains($value, "\n")
            || str_contains($value, "\r");

        if (! $needsQuotes) {
            return $value;
        }

        return $enclosure.str_replace($enclosure, $enclosure.$enclosure, $value).$enclosure;
    }
}
