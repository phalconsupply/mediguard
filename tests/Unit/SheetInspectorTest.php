<?php

namespace Tests\Unit;

use App\Services\Import\ColumnIndex;
use App\Services\Import\SheetInspector;
use PHPUnit\Framework\TestCase;

class SheetInspectorTest extends TestCase
{
    public function test_it_keeps_every_column_when_headers_repeat(): void
    {
        $columns = SheetInspector::buildColumns(['ID_CP', 'NGAY_YL', 'MA_LK', 'NGAY_YL', 'NGAY_YL']);

        $this->assertCount(5, $columns);
        $this->assertSame(['NGAY_YL#1', 'NGAY_YL#2', 'NGAY_YL#3'], [
            $columns[1]['internal_code'],
            $columns[3]['internal_code'],
            $columns[4]['internal_code'],
        ]);
        $this->assertSame([1, 3, 4], [
            $columns[1]['position'],
            $columns[3]['position'],
            $columns[4]['position'],
        ]);
    }

    public function test_it_keeps_the_original_header_text_alongside_the_normalised_one(): void
    {
        $columns = SheetInspector::buildColumns([' TIEN_DE_NGHI_KIEM_TRA ']);

        $this->assertSame(' TIEN_DE_NGHI_KIEM_TRA ', $columns[0]['raw_header']);
        $this->assertSame('TIEN_DE_NGHI_KIEM_TRA', $columns[0]['normalized_header']);
    }

    public function test_the_fingerprint_changes_when_the_column_layout_changes(): void
    {
        $a = SheetInspector::fingerprint(SheetInspector::buildColumns(['ID_CP', 'MA_LK']));
        $b = SheetInspector::fingerprint(SheetInspector::buildColumns(['MA_LK', 'ID_CP']));
        $sameAsA = SheetInspector::fingerprint(SheetInspector::buildColumns(['ID_CP', 'MA_LK']));

        $this->assertNotSame($a, $b, 'Đổi thứ tự cột phải sinh vân tay khác.');
        $this->assertSame($a, $sameAsA);
    }

    public function test_column_index_returns_every_position_of_a_repeated_header(): void
    {
        $index = ColumnIndex::fromColumns(SheetInspector::buildColumns(['A', 'NGAY_YL', 'B', 'NGAY_YL']));

        $this->assertSame([1, 3], $index->positionsOf('NGAY_YL'));
        $this->assertSame(1, $index->positionOf('NGAY_YL', 1));
        $this->assertSame(3, $index->positionOf('NGAY_YL', 2));
    }

    public function test_column_index_returns_null_for_a_column_absent_this_period(): void
    {
        $index = ColumnIndex::fromColumns(SheetInspector::buildColumns(['ID_CP', 'MA_LK']));

        $this->assertFalse($index->has('MA_BSI_THUC_HIEN'));
        $this->assertNull($index->value(['1', '2'], 'MA_BSI_THUC_HIEN'));
    }

    public function test_first_value_falls_back_across_renamed_columns(): void
    {
        $index = ColumnIndex::fromColumns(SheetInspector::buildColumns(['MA_VAT_TU', 'MA_VATTU']));

        $this->assertSame('VT2', $index->firstValue(['', 'VT2'], ['MA_VAT_TU', 'MA_VATTU']));
        $this->assertNull($index->firstValue(['', ''], ['MA_VAT_TU', 'MA_VATTU']));
    }
}
