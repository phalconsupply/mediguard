<?php

namespace App\Services\Import\Xml;

use XMLReader;

/**
 * Đọc tệp XML gửi giám định (`GIAMDINHHS`) theo luồng.
 *
 * Mẫu do đơn vị cung cấp chứa XML con **lồng trực tiếp** trong `NOIDUNGFILE`,
 * không phải chuỗi base64. Bộ đọc hỗ trợ cả hai biến thể nhưng chỉ giải mã base64
 * khi nội dung thực sự là base64 hợp lệ và giải ra được XML — không mặc định giải mã.
 *
 * Mỗi bản ghi trả về đều kèm vị trí trong nguồn: thứ tự `HOSO`, `LOAIHOSO`,
 * `STT` và thứ tự phần tử, để người xử lý tìm lại đúng dòng.
 */
final class GiamDinhHsReader
{
    /** Giới hạn kích thước một khối `NOIDUNGFILE` để tệp hỏng không làm cạn RAM. */
    private const MAX_INNER_BYTES = 33554432; // 32 MiB

    /** @var list<string> lỗi cấu trúc ở cấp tệp, đọc được sau khi duyệt xong */
    private array $documentIssues = [];

    public function __construct(private readonly string $path) {}

    /**
     * Lỗi cấu trúc XML ở cấp tệp (sai cú pháp, thiếu namespace, thực thể không hợp lệ).
     * Gọi sau khi duyệt xong `hoSo()`.
     *
     * @return list<string>
     */
    public function documentIssues(): array
    {
        return $this->documentIssues;
    }

    /**
     * Thông tin cấp tệp, đọc mà không nạp hết nội dung.
     *
     * @return array{facility_code: ?string, created_on: ?string, declared_count: ?int}
     */
    public function header(): array
    {
        $previous = libxml_use_internal_errors(true);
        $reader = $this->open();
        $header = ['facility_code' => null, 'created_on' => null, 'declared_count' => null];

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }

                match ($reader->localName) {
                    'MACSKCB' => $header['facility_code'] = $this->readTextValue($reader),
                    'NGAYLAP' => $header['created_on'] = $this->readTextValue($reader),
                    'SOLUONGHOSO' => $header['declared_count'] = (int) $this->readTextValue($reader),
                    default => null,
                };

                if ($reader->localName === 'DANHSACHHOSO') {
                    break;
                }
            }
        } finally {
            $reader->close();
            $this->collectLibxmlErrors();
            libxml_use_internal_errors($previous);
        }

        return $header;
    }

    /**
     * Duyệt từng `HOSO` và trả về các `FILEHOSO` đã tách.
     *
     * @return \Generator<int, array{hoso_index: int, files: array<string, list<array<string, string>>>, issues: list<string>}>
     */
    public function hoSo(): \Generator
    {
        // Lỗi cú pháp được gom lại để báo riêng, thay vì in ra như cảnh báo của PHP.
        $previous = libxml_use_internal_errors(true);
        $reader = $this->open();
        $hoSoIndex = 0;

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'HOSO') {
                    continue;
                }

                $hoSoIndex++;
                $xml = $reader->readOuterXml();
                if ($xml === '') {
                    continue;
                }

                yield $hoSoIndex => $this->parseHoSo($xml, $hoSoIndex);

                // `next` bỏ qua toàn bộ cây con đã đọc, giữ bộ nhớ ở mức một hồ sơ.
                $reader->next();
            }
        } finally {
            $reader->close();
            $this->collectLibxmlErrors();
            libxml_use_internal_errors($previous);
        }
    }

    private function collectLibxmlErrors(): void
    {
        foreach (libxml_get_errors() as $error) {
            $this->documentIssues[] = sprintf('Dòng %d: %s', $error->line, trim($error->message));
        }

        libxml_clear_errors();
    }

    /**
     * @return array{hoso_index: int, files: array<string, list<array<string, string>>>, issues: list<string>}
     */
    private function parseHoSo(string $xml, int $hoSoIndex): array
    {
        $files = [];
        $issues = [];

        $document = $this->loadDocument($xml);
        if ($document === null) {
            return ['hoso_index' => $hoSoIndex, 'files' => [], 'issues' => ["HOSO {$hoSoIndex}: không đọc được cấu trúc XML."]];
        }

        foreach ($document->getElementsByTagName('FILEHOSO') as $fileNode) {
            $type = $this->childText($fileNode, 'LOAIHOSO');
            if ($type === null) {
                $issues[] = "HOSO {$hoSoIndex}: một FILEHOSO thiếu LOAIHOSO.";

                continue;
            }

            $contentNodes = $fileNode->getElementsByTagName('NOIDUNGFILE');
            if ($contentNodes->length === 0) {
                $issues[] = "HOSO {$hoSoIndex}/{$type}: thiếu NOIDUNGFILE.";

                continue;
            }

            $records = $this->extractRecords($contentNodes->item(0), $type, $hoSoIndex, $issues);
            $files[$type] = $records;
        }

        return ['hoso_index' => $hoSoIndex, 'files' => $files, 'issues' => $issues];
    }

    /**
     * Lấy các bản ghi chi tiết trong `NOIDUNGFILE`.
     *
     * Nhánh không áp dụng có thể rỗng — đó không phải lỗi, nên trả về mảng rỗng
     * thay vì báo cấu trúc sai.
     *
     * @param  list<string>  $issues
     * @return list<array<string, string>>
     */
    private function extractRecords(?\DOMNode $content, string $type, int $hoSoIndex, array &$issues): array
    {
        if ($content === null) {
            return [];
        }

        $inner = $this->resolveInnerDocument($content, $type, $hoSoIndex, $issues);
        if ($inner === null) {
            return [];
        }

        // Cấu trúc thực tế: <XMLn><CHITIEU.../><DSACH_.../><CHI_TIET_.../>.
        // Bản ghi là phần tử lá-của-danh-sách sâu nhất lặp lại nhiều lần.
        $recordNodes = $this->findRecordNodes($inner);
        $records = [];

        foreach ($recordNodes as $position => $node) {
            $record = ['_position' => (string) ($position + 1)];
            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    $record[strtoupper($child->localName)] = trim($child->textContent);
                }
            }
            $records[] = $record;
        }

        return $records;
    }

    /**
     * Giải nội dung `NOIDUNGFILE`: node XML lồng trực tiếp (biến thể trong mẫu)
     * hoặc chuỗi base64 chứa XML (biến thể của một số hệ thống khác).
     *
     * @param  list<string>  $issues
     */
    private function resolveInnerDocument(\DOMNode $content, string $type, int $hoSoIndex, array &$issues): ?\DOMElement
    {
        foreach ($content->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                return $child;
            }
        }

        $text = trim($content->textContent);
        if ($text === '') {
            return null;
        }

        if (strlen($text) > self::MAX_INNER_BYTES) {
            $issues[] = "HOSO {$hoSoIndex}/{$type}: NOIDUNGFILE vượt giới hạn xử lý.";

            return null;
        }

        $decoded = base64_decode($text, true);
        if ($decoded === false || ! str_contains($decoded, '<')) {
            $issues[] = "HOSO {$hoSoIndex}/{$type}: NOIDUNGFILE không phải XML lồng cũng không phải base64 hợp lệ.";

            return null;
        }

        $document = $this->loadDocument($decoded);
        if ($document === null) {
            $issues[] = "HOSO {$hoSoIndex}/{$type}: nội dung base64 giải ra không phải XML hợp lệ.";

            return null;
        }

        return $document->documentElement;
    }

    /**
     * Tìm tập phần tử bản ghi: các phần tử anh em cùng tên, nhiều nhất và sâu nhất.
     * Cách này không phụ thuộc vào tên `CHI_TIET_*` cụ thể nên chịu được XML mới.
     *
     * @return list<\DOMElement>
     */
    private function findRecordNodes(\DOMElement $root): array
    {
        $best = [];
        $queue = [$root];

        while ($queue !== []) {
            $node = array_shift($queue);
            $byName = [];

            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    $byName[$child->localName][] = $child;
                    $queue[] = $child;
                }
            }

            foreach ($byName as $siblings) {
                // Bản ghi phải có phần tử con; phần tử lá lặp lại là giá trị, không phải bản ghi.
                $hasChildren = $this->hasElementChild($siblings[0]);
                if ($hasChildren && count($siblings) >= count($best)) {
                    $best = $siblings;
                }
            }
        }

        if ($best !== []) {
            return array_values($best);
        }

        // Danh sách chỉ có một bản ghi.
        foreach ($root->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->hasElementChild($child)) {
                return [$child];
            }
        }

        // NOIDUNGFILE chứa thẳng một bản ghi, như `XML1/TONG_HOP` trong mẫu.
        return $this->hasElementChild($root) ? [$root] : [];
    }

    private function hasElementChild(\DOMElement $element): bool
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                return true;
            }
        }

        return false;
    }

    private function childText(\DOMElement $element, string $name): ?string
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && strtoupper($child->localName) === $name) {
                $value = trim($child->textContent);

                return $value === '' ? null : $value;
            }
        }

        return null;
    }

    /** Nạp một đoạn XML với tham chiếu thực thể bên ngoài bị chặn. */
    private function loadDocument(string $xml): ?\DOMDocument
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);

        // LIBXML_NONET chặn truy cập mạng; không dùng LIBXML_NOENT nên thực thể không được mở rộng.
        $ok = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT);

        $this->collectLibxmlErrors();
        libxml_use_internal_errors($previous);

        return $ok ? $document : null;
    }

    private function open(): XMLReader
    {
        $reader = new XMLReader;

        if (! $reader->open($this->path, null, LIBXML_NONET | LIBXML_NOCDATA)) {
            throw new \RuntimeException("Không mở được tệp XML {$this->path}.");
        }

        return $reader;
    }

    private function readTextValue(XMLReader $reader): ?string
    {
        $value = trim($reader->readString());

        return $value === '' ? null : $value;
    }
}
