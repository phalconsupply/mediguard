<?php

namespace Tests\Unit;

use App\Support\Normalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NormalizerTest extends TestCase
{
    #[DataProvider('moneyCases')]
    public function test_it_reads_the_money_formats_found_in_the_source_files(mixed $raw, ?float $expected): void
    {
        $this->assertSame($expected, Normalizer::money($raw));
    }

    /** @return array<string, array{mixed, ?float}> */
    public static function moneyCases(): array
    {
        return [
            'số nguyên trần' => ['502549', 502549.0],
            'dấu chấm nhóm nghìn, dấu phẩy thập phân' => ['502.549,00', 502549.0],
            'dấu phẩy nhóm nghìn, dấu chấm thập phân' => ['502,549.00', 502549.0],
            'một dấu phẩy thập phân' => ['1,01', 1.01],
            'một dấu chấm nhóm nghìn' => ['502.549', 502549.0],
            'nhiều nhóm nghìn' => ['1.234.567', 1234567.0],
            'số âm' => ['-120000', -120000.0],
            'số trong ngoặc là âm' => ['(120.000,00)', -120000.0],
            'kèm đơn vị' => ['120.000 đ', 120000.0],
            'ô trống trả về null, không phải 0' => ['', null],
            'null trả về null' => [null, null],
            'chuỗi không phải số' => ['không có', null],
            'kiểu số sẵn' => [502549, 502549.0],
        ];
    }

    #[DataProvider('dateCases')]
    public function test_it_records_the_precision_of_each_timestamp(string $raw, ?string $expected, int $precision): void
    {
        $result = Normalizer::dateTimeWithPrecision($raw);

        $this->assertSame($expected, $result['value']?->format('Y-m-d H:i:s'));
        $this->assertSame($precision, $result['precision']);
    }

    /** @return array<string, array{string, ?string, int}> */
    public static function dateCases(): array
    {
        return [
            'CSV chính xác đến giây' => ['2026-07-03 15:44:00', '2026-07-03 15:44:00', 3],
            'CSV chính xác đến phút' => ['2026-07-03 15:49', '2026-07-03 15:49:00', 2],
            'chỉ ngày' => ['2026-04-01', '2026-04-01 00:00:00', 1],
            'XML đến phút' => ['202607031549', '2026-07-03 15:49:00', 2],
            'XML chỉ ngày' => ['20260401', '2026-04-01 00:00:00', 1],
            'ngày không có thật' => ['20261345', null, 0],
            'ô trống' => ['', null, 0],
        ];
    }

    public function test_it_does_not_invent_seconds_for_a_minute_precision_value(): void
    {
        $minute = Normalizer::dateTimeWithPrecision('2026-07-03 15:49');

        // Giá trị vẫn đọc được, nhưng độ chính xác phải nói rõ là phút:
        // quy tắc cần chính xác đến giây không được dùng mốc này để kết luận.
        $this->assertSame(2, $minute['precision']);
    }

    public function test_header_normalisation_strips_surrounding_and_invisible_whitespace(): void
    {
        $this->assertSame('TIEN_DE_NGHI_KIEM_TRA', Normalizer::header(' TIEN_DE_NGHI_KIEM_TRA '));
        $this->assertSame('MA_LK', Normalizer::header("\u{FEFF}ma_lk"));
        $this->assertSame('NGAY YL', Normalizer::header("NGAY\u{00A0}  YL"));
    }

    public function test_internal_code_separates_duplicate_headers(): void
    {
        $this->assertSame('NGAY_YL#1', Normalizer::internalCode('NGAY_YL', 1));
        $this->assertSame('NGAY_YL#2', Normalizer::internalCode('NGAY_YL', 2));
    }

    #[DataProvider('formulaCases')]
    public function test_it_protects_spreadsheet_cells_from_being_read_as_formulas(string $input, string $expected): void
    {
        $this->assertSame($expected, Normalizer::safeSpreadsheetText($input));
    }

    /** @return array<string, array{string, string}> */
    public static function formulaCases(): array
    {
        return [
            'dấu bằng' => ['=SUM(A1)', "'=SUM(A1)"],
            'dấu cộng' => ['+1', "'+1"],
            'dấu trừ' => ['-1', "'-1"],
            'a còng' => ['@ten', "'@ten"],
            'văn bản thường giữ nguyên' => ['Đã kiểm tra hồ sơ', 'Đã kiểm tra hồ sơ'],
            'tiếng Việt có dấu giữ nguyên' => ['Chỉ định phù hợp chẩn đoán', 'Chỉ định phù hợp chẩn đoán'],
        ];
    }

    #[DataProvider('conclusionCases')]
    public function test_it_groups_conclusions_without_guessing(?string $raw, string $expected): void
    {
        $this->assertSame($expected, Normalizer::conclusion($raw));
    }

    /** @return array<string, array{?string, string}> */
    public static function conclusionCases(): array
    {
        return [
            'từ chối' => ['Từ chối', 'tu_choi'],
            'cảnh báo' => ['Cảnh báo', 'canh_bao'],
            'chấp nhận' => ['Chấp nhận', 'chap_nhan'],
            'không dấu' => ['tu choi', 'tu_choi'],
            'ô trống' => ['', 'trong'],
            'chuỗi lạ không bị đoán là từ chối' => ['Đang xem xét', 'khac'],
        ];
    }
}
