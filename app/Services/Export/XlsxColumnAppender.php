<?php

namespace App\Services\Export;

use ZipArchive;

/**
 * Nối một cột vào cuối trang dữ liệu của một XLSX mà giữ nguyên phần còn lại của workbook.
 *
 * Cách làm: sao chép nguyên gói OOXML rồi chỉ viết lại đúng một tệp
 * `xl/worksheets/sheetN.xml`. Các trang tính khác, định dạng, kiểu ô, công thức,
 * sharedStrings và mọi phần khác của gói không bị đụng tới. Giá trị mới được ghi
 * dạng chuỗi nội tuyến (`inlineStr`) nên không phải sửa bảng chuỗi dùng chung.
 *
 * Trang tính được xử lý theo luồng: mỗi lần chỉ giữ một phần tử `<row>` trong bộ nhớ.
 */
final class XlsxColumnAppender
{
    private const READ_CHUNK = 262144;

    /**
     * @param  callable(int $rowNumber): ?string  $valueForRow  nội dung cột mới theo số dòng gốc
     * @return array{rows_seen: int, rows_written: int, skipped_rows: list<int>, sheet_path: string}
     */
    public function append(
        string $sourcePath,
        string $targetPath,
        int $sheetIndex,
        int $headerRowNumber,
        int $newColumnIndex,
        string $headerText,
        callable $valueForRow,
    ): array {
        if (! copy($sourcePath, $targetPath)) {
            throw new \RuntimeException("Không sao chép được tệp nguồn sang {$targetPath}.");
        }

        $zip = new ZipArchive;
        if ($zip->open($targetPath) !== true) {
            throw new \RuntimeException('Không mở được gói XLSX để ghi cột kết quả.');
        }

        try {
            $sheetPath = $this->locateWorksheet($zip, $sheetIndex);

            $original = tempnam(sys_get_temp_dir(), 'mg_sheet_');
            $rewritten = tempnam(sys_get_temp_dir(), 'mg_out_');

            $this->extract($zip, $sheetPath, $original);

            $result = $this->rewrite(
                $original,
                $rewritten,
                $headerRowNumber,
                $newColumnIndex,
                $headerText,
                $valueForRow,
            );

            $zip->deleteName($sheetPath);
            $zip->addFile($rewritten, $sheetPath);
            // addFile đọc tệp lúc close(), nên $rewritten phải còn tồn tại đến sau close().
            $zip->close();

            @unlink($original);
            @unlink($rewritten);

            return $result + ['sheet_path' => $sheetPath];
        } catch (\Throwable $e) {
            @$zip->close();
            throw $e;
        }
    }

    /**
     * Tìm đường dẫn trang tính thứ `$sheetIndex` (0-based, theo thứ tự trong workbook)
     * qua `workbook.xml` và bảng quan hệ của nó.
     */
    private function locateWorksheet(ZipArchive $zip, int $sheetIndex): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook === false || $rels === false) {
            throw new \RuntimeException('Gói XLSX thiếu workbook.xml hoặc bảng quan hệ.');
        }

        if (! preg_match_all('/<sheet\b[^>]*>/', $workbook, $sheetTags)) {
            throw new \RuntimeException('Không đọc được danh sách trang tính trong workbook.xml.');
        }

        $tag = $sheetTags[0][$sheetIndex] ?? null;
        if ($tag === null) {
            throw new \RuntimeException("Workbook không có trang tính ở vị trí {$sheetIndex}.");
        }

        if (! preg_match('/r:id="([^"]+)"/', $tag, $m)) {
            throw new \RuntimeException('Trang tính không khai báo quan hệ r:id.');
        }
        $relationshipId = $m[1];

        if (! preg_match('/<Relationship\b[^>]*Id="'.preg_quote($relationshipId, '/').'"[^>]*>/', $rels, $rel)) {
            throw new \RuntimeException("Không tìm thấy quan hệ {$relationshipId} trong workbook.xml.rels.");
        }

        if (! preg_match('/Target="([^"]+)"/', $rel[0], $target)) {
            throw new \RuntimeException('Quan hệ trang tính thiếu thuộc tính Target.');
        }

        $path = ltrim($target[1], '/');
        $path = str_starts_with($path, 'xl/') ? $path : 'xl/'.$path;

        if ($zip->locateName($path) === false) {
            throw new \RuntimeException("Gói XLSX không chứa {$path}.");
        }

        return $path;
    }

    private function extract(ZipArchive $zip, string $entry, string $destination): void
    {
        $in = $zip->getStream($entry);
        if ($in === false) {
            throw new \RuntimeException("Không đọc được {$entry} từ gói XLSX.");
        }

        $out = fopen($destination, 'wb');
        if ($out === false) {
            fclose($in);
            throw new \RuntimeException('Không mở được tệp tạm để giải nén trang tính.');
        }

        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
    }

    /**
     * @param  callable(int): ?string  $valueForRow
     * @return array{rows_seen:int, rows_written:int, skipped_rows: list<int>}
     */
    private function rewrite(
        string $sourceXml,
        string $targetXml,
        int $headerRowNumber,
        int $newColumnIndex,
        string $headerText,
        callable $valueForRow,
    ): array {
        $in = fopen($sourceXml, 'rb');
        $out = fopen($targetXml, 'wb');
        if ($in === false || $out === false) {
            throw new \RuntimeException('Không mở được tệp tạm khi ghi lại trang tính.');
        }

        $newColumnLetter = self::columnLetter($newColumnIndex);
        $rowsSeen = 0;
        $rowsWritten = 0;
        $skipped = [];

        try {
            $buffer = $this->readUntil($in, '<sheetData', '');
            $prefixEnd = strpos($buffer, '<sheetData');
            if ($prefixEnd === false) {
                throw new \RuntimeException('Trang tính không có phần tử sheetData.');
            }

            $prefix = substr($buffer, 0, $prefixEnd);
            $buffer = substr($buffer, $prefixEnd);
            fwrite($out, $this->widenDimension($prefix, $newColumnLetter));

            while (true) {
                $rowStart = strpos($buffer, '<row');
                if ($rowStart === false) {
                    $chunk = fread($in, self::READ_CHUNK);
                    if ($chunk === false || $chunk === '') {
                        fwrite($out, $buffer);
                        break;
                    }
                    // Giữ lại phần đuôi để không cắt đôi thẻ `<row`.
                    $keep = min(strlen($buffer), 8);
                    fwrite($out, substr($buffer, 0, strlen($buffer) - $keep));
                    $buffer = substr($buffer, strlen($buffer) - $keep).$chunk;

                    continue;
                }

                fwrite($out, substr($buffer, 0, $rowStart));
                $buffer = substr($buffer, $rowStart);

                $buffer = $this->readUntil($in, '>', $buffer);
                $startTagEnd = strpos($buffer, '>');
                if ($startTagEnd === false) {
                    fwrite($out, $buffer);
                    break;
                }

                $startTag = substr($buffer, 0, $startTagEnd + 1);
                $selfClosing = str_ends_with($startTag, '/>');

                if ($selfClosing) {
                    $element = $startTag;
                    $buffer = substr($buffer, $startTagEnd + 1);
                } else {
                    $buffer = $this->readUntil($in, '</row>', $buffer);
                    $closeAt = strpos($buffer, '</row>');
                    if ($closeAt === false) {
                        fwrite($out, $buffer);
                        break;
                    }
                    $element = substr($buffer, 0, $closeAt + 6);
                    $buffer = substr($buffer, $closeAt + 6);
                }

                $rowsSeen++;
                $rowNumber = $this->rowNumber($startTag) ?? $rowsSeen;

                $isHeader = $rowNumber === $headerRowNumber;
                $value = $isHeader ? $headerText : $valueForRow($rowNumber);
                $written = $this->emitRow($out, $element, $startTag, $selfClosing, $rowNumber, $newColumnIndex, $newColumnLetter, $value);

                if ($written === 'skipped') {
                    $skipped[] = $rowNumber;
                } elseif ($written === 'written' && ! $isHeader) {
                    $rowsWritten++;
                }
            }

            // Ghi nốt phần còn lại của tệp sau dòng cuối cùng.
            while (($chunk = fread($in, self::READ_CHUNK)) !== false && $chunk !== '') {
                fwrite($out, $chunk);
            }
        } finally {
            fclose($in);
            fclose($out);
        }

        return ['rows_seen' => $rowsSeen, 'rows_written' => $rowsWritten, 'skipped_rows' => $skipped];
    }

    /**
     * Ghi lại một phần tử row, chèn ô mới ngay trước thẻ đóng.
     *
     * @return 'written'|'empty'|'skipped'
     */
    private function emitRow(
        mixed $out,
        string $element,
        string $startTag,
        bool $selfClosing,
        int $rowNumber,
        int $newColumnIndex,
        string $newColumnLetter,
        ?string $value,
    ): string {
        // `spans` là gợi ý phạm vi cột; để nguyên sẽ mâu thuẫn với cột vừa thêm.
        $cleanStartTag = preg_replace('/\s+spans="[^"]*"/', '', $startTag) ?? $startTag;

        if ($value === null || $value === '') {
            fwrite($out, $selfClosing ? $cleanStartTag : $cleanStartTag.substr($element, strlen($startTag)));

            return 'empty';
        }

        // Ô phải xếp tăng dần theo cột. Nếu dòng đã có ô ở vị trí bằng hoặc sau cột mới,
        // báo bỏ qua để bước kiểm soát chất lượng chặn lại, thay vì ghi ra tệp sai thứ tự.
        if (! $selfClosing && $this->hasCellAtOrAfter($element, $newColumnIndex)) {
            fwrite($out, $cleanStartTag.substr($element, strlen($startTag)));

            return 'skipped';
        }

        $cell = sprintf(
            '<c r="%s%d" t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>',
            $newColumnLetter,
            $rowNumber,
            htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
        );

        if ($selfClosing) {
            fwrite($out, rtrim(substr($cleanStartTag, 0, -2)).'>'.$cell.'</row>');
        } else {
            $body = substr($element, strlen($startTag), -6);
            fwrite($out, $cleanStartTag.$body.$cell.'</row>');
        }

        return 'written';
    }

    private function hasCellAtOrAfter(string $element, int $newColumnIndex): bool
    {
        if (! preg_match_all('/<c\b[^>]*\sr="([A-Z]+)\d+"/', $element, $matches)) {
            return false;
        }

        foreach ($matches[1] as $letters) {
            if (self::columnIndex($letters) >= $newColumnIndex) {
                return true;
            }
        }

        return false;
    }

    /** Mở rộng `<dimension ref="A1:DG100"/>` để bao cột vừa thêm. */
    private function widenDimension(string $prefix, string $newColumnLetter): string
    {
        return preg_replace_callback(
            '/<dimension\s+ref="([A-Z]+)(\d+):([A-Z]+)(\d+)"\s*\/>/',
            function (array $m) use ($newColumnLetter): string {
                $end = self::columnIndex($m[3]) >= self::columnIndex($newColumnLetter) ? $m[3] : $newColumnLetter;

                return sprintf('<dimension ref="%s%s:%s%s"/>', $m[1], $m[2], $end, $m[4]);
            },
            $prefix,
            1
        ) ?? $prefix;
    }

    /** Đọc thêm từ tệp cho đến khi buffer chứa `$needle` hoặc hết tệp. */
    private function readUntil(mixed $handle, string $needle, string $buffer): string
    {
        while (! str_contains($buffer, $needle)) {
            $chunk = fread($handle, self::READ_CHUNK);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $buffer .= $chunk;
        }

        return $buffer;
    }

    private function rowNumber(string $startTag): ?int
    {
        return preg_match('/\br="(\d+)"/', $startTag, $m) ? (int) $m[1] : null;
    }

    /** 0 -> A, 25 -> Z, 26 -> AA */
    public static function columnLetter(int $index): string
    {
        $letters = '';
        $index++;

        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letters = chr(65 + $remainder).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }

    /** A -> 0, Z -> 25, AA -> 26 */
    public static function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split(strtoupper($letters)) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return $index - 1;
    }
}
