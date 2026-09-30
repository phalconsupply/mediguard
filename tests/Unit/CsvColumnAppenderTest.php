<?php

namespace Tests\Unit;

use App\Services\Export\CsvColumnAppender;
use PHPUnit\Framework\TestCase;

class CsvColumnAppenderTest extends TestCase
{
    private string $source;

    private string $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = tempnam(sys_get_temp_dir(), 'mg_src_');
        $this->target = tempnam(sys_get_temp_dir(), 'mg_dst_');
    }

    protected function tearDown(): void
    {
        @unlink($this->source);
        @unlink($this->target);
        parent::tearDown();
    }

    public function test_it_appends_the_column_without_touching_the_original_bytes(): void
    {
        $original = "ID_CP,GHI_CHU,KET_LUAN\r\n1,\"VI PHẠM [Thanh toán], xem lại\",Từ chối\r\n2,,Chấp nhận\r\n";
        file_put_contents($this->source, $original);

        $result = (new CsvColumnAppender)->append(
            $this->source,
            $this->target,
            headerRowNumber: 1,
            headerText: 'ĐÃ GIẢI TRÌNH',
            valueForRow: fn (int $row) => $row === 2 ? 'Hồ sơ đã có chỉ định phù hợp.' : null,
        );

        $written = file_get_contents($this->target);

        $this->assertSame(3, $result['rows_seen']);
        $this->assertSame(1, $result['rows_written']);

        $expected = "ID_CP,GHI_CHU,KET_LUAN,ĐÃ GIẢI TRÌNH\r\n"
            ."1,\"VI PHẠM [Thanh toán], xem lại\",Từ chối,Hồ sơ đã có chỉ định phù hợp.\r\n"
            ."2,,Chấp nhận,\r\n";

        $this->assertSame($expected, $written);
    }

    public function test_it_treats_a_newline_inside_quotes_as_part_of_the_same_record(): void
    {
        $original = "ID_CP,GHI_CHU\n1,\"dòng một\ndòng hai\"\n2,bình thường\n";
        file_put_contents($this->source, $original);

        $result = (new CsvColumnAppender)->append(
            $this->source,
            $this->target,
            headerRowNumber: 1,
            headerText: 'ĐÃ GIẢI TRÌNH',
            valueForRow: fn (int $row) => 'bản '.$row,
        );

        // 3 bản ghi, không phải 4: ký tự xuống dòng nằm trong dấu nháy không tách bản ghi.
        $this->assertSame(3, $result['rows_seen']);
        $this->assertStringContainsString("\"dòng một\ndòng hai\",bản 2\n", file_get_contents($this->target));
    }

    public function test_it_quotes_a_value_that_contains_the_delimiter(): void
    {
        file_put_contents($this->source, "A\n1\n");

        (new CsvColumnAppender)->append(
            $this->source,
            $this->target,
            headerRowNumber: 1,
            headerText: 'ĐÃ GIẢI TRÌNH',
            valueForRow: fn () => 'có dấu phẩy, và "dấu nháy"',
        );

        $this->assertStringContainsString('"có dấu phẩy, và ""dấu nháy"""', file_get_contents($this->target));
    }

    public function test_it_handles_a_final_record_without_a_trailing_newline(): void
    {
        file_put_contents($this->source, "A\n1");

        $result = (new CsvColumnAppender)->append(
            $this->source,
            $this->target,
            headerRowNumber: 1,
            headerText: 'H',
            valueForRow: fn () => 'X',
        );

        $this->assertSame(2, $result['rows_seen']);
        $this->assertSame("A,H\n1,X", file_get_contents($this->target));
    }
}
