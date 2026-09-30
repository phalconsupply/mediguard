<?php

namespace Tests\Unit;

use App\Services\Export\XlsxColumnAppender;
use App\Services\Import\Readers\XlsxTabularReader;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\TestCase;

class XlsxColumnAppenderTest extends TestCase
{
    private string $source;

    private string $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = tempnam(sys_get_temp_dir(), 'mg_src_').'.xlsx';
        $this->target = tempnam(sys_get_temp_dir(), 'mg_dst_').'.xlsx';
    }

    protected function tearDown(): void
    {
        @unlink($this->source);
        @unlink($this->target);
        parent::tearDown();
    }

    public function test_letter_and_index_conversions_round_trip(): void
    {
        foreach ([0 => 'A', 25 => 'Z', 26 => 'AA', 51 => 'AZ', 52 => 'BA', 122 => 'DS', 701 => 'ZZ', 702 => 'AAA'] as $index => $letters) {
            $this->assertSame($letters, XlsxColumnAppender::columnLetter($index));
            $this->assertSame($index, XlsxColumnAppender::columnIndex($letters));
        }
    }

    public function test_it_appends_one_column_and_leaves_every_source_cell_untouched(): void
    {
        $rows = [
            ['ID_CP', 'GHI_CHU', 'KET_LUAN', 'TIEN_DE_NGHI_KIEM_TRA'],
            ['19787930341', 'VI PHẠM [Thanh toán] thuốc', 'Từ chối', '502.549,00'],
            ['19787930342', 'Ghi chú có "dấu nháy" & ký tự < >', 'Cảnh báo', '120000'],
            ['', 'Dòng tổng hợp', '', '622549'],
        ];

        $this->writeWorkbook($rows);

        $explanations = [
            2 => 'Chỉ định phù hợp chẩn đoán đã ghi trong bệnh án.',
            3 => 'Ký tự đặc biệt & < > vẫn phải giữ nguyên.',
        ];

        $result = (new XlsxColumnAppender)->append(
            $this->source,
            $this->target,
            sheetIndex: 0,
            headerRowNumber: 1,
            newColumnIndex: 4,
            headerText: 'ĐÃ GIẢI TRÌNH',
            valueForRow: fn (int $row) => $explanations[$row] ?? null,
        );

        $this->assertSame(4, $result['rows_seen']);
        $this->assertSame(2, $result['rows_written']);
        $this->assertSame([], $result['skipped_rows']);

        $read = [];
        foreach ((new XlsxTabularReader($this->target))->rows(0) as $number => $cells) {
            $read[$number] = $cells;
        }

        $this->assertCount(4, $read);
        $this->assertSame([...$rows[0], 'ĐÃ GIẢI TRÌNH'], $read[1]);
        $this->assertSame([...$rows[1], $explanations[2]], $read[2]);
        $this->assertSame([...$rows[2], $explanations[3]], $read[3]);

        // Dòng tổng hợp giữ nguyên và để trống cột mới.
        $this->assertSame($rows[3], array_slice($read[4], 0, 4));
        $this->assertSame('', $read[4][4] ?? '');
    }

    public function test_it_keeps_other_worksheets_in_the_workbook(): void
    {
        $writer = new Writer;
        $writer->openToFile($this->source);
        $writer->getCurrentSheet()->setName('DuLieuGoc');
        $writer->addRow(Row::fromValues(['ID_CP', 'KET_LUAN']));
        $writer->addRow(Row::fromValues(['1', 'Từ chối']));
        $writer->addNewSheetAndMakeItCurrent()->setName('GhiChu');
        $writer->addRow(Row::fromValues(['Trang này không được đụng tới']));
        $writer->close();

        (new XlsxColumnAppender)->append(
            $this->source,
            $this->target,
            sheetIndex: 0,
            headerRowNumber: 1,
            newColumnIndex: 2,
            headerText: 'ĐÃ GIẢI TRÌNH',
            valueForRow: fn () => 'nội dung',
        );

        $reader = new XlsxTabularReader($this->target);
        $sheets = $reader->sheets();

        $this->assertSame(['DuLieuGoc', 'GhiChu'], array_column($sheets, 'name'));

        $second = iterator_to_array($reader->rows(1));
        $this->assertSame(['Trang này không được đụng tới'], $second[1]);
    }

    public function test_it_reports_a_row_it_could_not_write_instead_of_corrupting_the_order(): void
    {
        $this->writeWorkbook([
            ['A', 'B', 'C'],
            ['1', '2', '3'],
        ]);

        // Cột mới đặt ở vị trí 1 trong khi dòng đã có ô ở vị trí 1 và 2:
        // chèn vào đây sẽ phá thứ tự cột, nên phải báo bỏ qua.
        $result = (new XlsxColumnAppender)->append(
            $this->source,
            $this->target,
            sheetIndex: 0,
            headerRowNumber: 1,
            newColumnIndex: 1,
            headerText: 'H',
            valueForRow: fn () => 'X',
        );

        $this->assertContains(2, $result['skipped_rows']);
    }

    /** @param list<list<string>> $rows */
    private function writeWorkbook(array $rows): void
    {
        $writer = new Writer;
        $writer->openToFile($this->source);
        $writer->getCurrentSheet()->setName('DuLieuGoc');

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();
    }
}
