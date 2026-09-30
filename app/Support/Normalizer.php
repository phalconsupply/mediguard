<?php

namespace App\Support;

use DateTimeImmutable;

/**
 * Chuẩn hóa giá trị thô đọc từ XLSX/CSV/XML.
 *
 * Dữ liệu khảo sát có số tiền ở cả hai dạng `502549` và `502.549,00`, tiêu đề cột
 * có khoảng trắng bao quanh và trùng tên, mốc thời gian có ba độ chính xác khác nhau.
 * Mọi phép đọc đều phải đi qua đây để tránh mỗi nơi tự đoán một kiểu.
 */
final class Normalizer
{
    /** Ký tự mở đầu bị bảng tính hiểu là công thức. */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Chuẩn hóa tiêu đề cột: bỏ khoảng trắng thừa, BOM, ký tự vô hình; đưa về chữ hoa.
     * Giữ nguyên dấu gạch dưới vì tên trường nguồn dùng dạng `MA_BSI_THUC_HIEN`.
     */
    public static function header(?string $raw): string
    {
        $value = (string) $raw;
        $value = str_replace(["\u{FEFF}", "\u{00A0}", "\u{200B}"], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return mb_strtoupper(trim($value), 'UTF-8');
    }

    /**
     * Mã nội bộ của một cột: tiêu đề chuẩn hóa + lần xuất hiện.
     * Nhờ vậy cấu hình cột chịu được việc đổi thứ tự cột giữa các kỳ,
     * còn tiêu đề trùng vẫn phân biệt được.
     */
    public static function internalCode(string $normalizedHeader, int $occurrence): string
    {
        return $normalizedHeader.'#'.$occurrence;
    }

    /**
     * Đọc số tiền. Trả về null khi ô trống hoặc không đọc được,
     * không tự quy về 0 để tránh báo cáo sai.
     */
    public static function money(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }

        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        $negative = str_starts_with($value, '-') || (str_starts_with($value, '(') && str_ends_with($value, ')'));
        $digits = preg_replace('/[^0-9.,]/', '', $value) ?? '';
        if ($digits === '') {
            return null;
        }

        $lastComma = strrpos($digits, ',');
        $lastDot = strrpos($digits, '.');

        if ($lastComma !== false && $lastDot !== false) {
            // Dấu xuất hiện sau cùng là dấu thập phân, dấu còn lại là dấu nhóm nghìn.
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
            $groupSeparator = $decimalSeparator === ',' ? '.' : ',';
            $digits = str_replace($groupSeparator, '', $digits);
            $digits = str_replace($decimalSeparator, '.', $digits);
        } elseif ($lastComma !== false) {
            $digits = self::resolveSingleSeparator($digits, ',');
        } elseif ($lastDot !== false) {
            $digits = self::resolveSingleSeparator($digits, '.');
        }

        if (! is_numeric($digits)) {
            return null;
        }

        $number = (float) $digits;

        return $negative ? -$number : $number;
    }

    /**
     * Một dấu duy nhất có thể là dấu nghìn hoặc dấu thập phân.
     * Quy ước: đúng 3 chữ số sau dấu và không có dấu khác -> dấu nhóm nghìn.
     */
    private static function resolveSingleSeparator(string $digits, string $separator): string
    {
        $parts = explode($separator, $digits);
        $tail = end($parts);

        if (count($parts) > 2 || (strlen($tail) === 3 && $parts[0] !== '')) {
            return str_replace($separator, '', $digits);
        }

        return str_replace($separator, '.', $digits);
    }

    /**
     * Đọc mốc thời gian và trả kèm độ chính xác thực tế của nguồn.
     *
     * Các dạng gặp trong dữ liệu khảo sát:
     *   `2026-07-03 15:44:00` (giây), `2026-07-03 15:49` (phút),
     *   `202607031549` và `196805220000` (XML, phút), `20260401` (ngày).
     *
     * @return array{value: ?DateTimeImmutable, precision: int} precision: 0 không rõ, 1 ngày, 2 phút, 3 giây
     */
    public static function dateTimeWithPrecision(mixed $raw): array
    {
        if ($raw instanceof DateTimeImmutable) {
            return ['value' => $raw, 'precision' => 3];
        }
        if ($raw instanceof \DateTimeInterface) {
            return ['value' => DateTimeImmutable::createFromInterface($raw), 'precision' => 3];
        }

        $value = trim((string) ($raw ?? ''));
        if ($value === '') {
            return ['value' => null, 'precision' => 0];
        }

        $patterns = [
            ['/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/', 'Y-m-d H:i:s', 3],
            ['/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}$/', 'Y-m-d H:i', 2],
            ['/^\d{4}-\d{2}-\d{2}$/', 'Y-m-d', 1],
            ['/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}$/', 'd/m/Y H:i:s', 3],
            ['/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/', 'd/m/Y H:i', 2],
            ['/^\d{2}\/\d{2}\/\d{4}$/', 'd/m/Y', 1],
            ['/^\d{14}$/', 'YmdHis', 3],
            ['/^\d{12}$/', 'YmdHi', 2],
            ['/^\d{8}$/', 'Ymd', 1],
        ];

        foreach ($patterns as [$pattern, $format, $precision]) {
            if (preg_match($pattern, $value)) {
                $normalized = str_replace('T', ' ', $value);
                $parsed = DateTimeImmutable::createFromFormat('!'.$format, $normalized);
                $errors = DateTimeImmutable::getLastErrors();

                if ($parsed !== false && empty($errors['warning_count']) && empty($errors['error_count'])) {
                    return ['value' => $parsed, 'precision' => $precision];
                }

                // Đúng dạng nhưng không phải ngày có thật (ví dụ `20261345`)
                // -> báo không đọc được thay vì để PHP tự dồn sang tháng sau.
                return ['value' => null, 'precision' => 0];
            }
        }

        return ['value' => null, 'precision' => 0];
    }

    public static function dateTime(mixed $raw): ?DateTimeImmutable
    {
        return self::dateTimeWithPrecision($raw)['value'];
    }

    /** Chuẩn hóa mã (khoa, bác sĩ, dịch vụ): bỏ khoảng trắng, đưa về chữ hoa. */
    public static function code(mixed $raw): ?string
    {
        $value = trim((string) ($raw ?? ''));

        return $value === '' ? null : mb_strtoupper($value, 'UTF-8');
    }

    public static function text(mixed $raw): ?string
    {
        $value = trim((string) ($raw ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * Bọc văn bản trước khi ghi ra XLSX để bảng tính không hiểu nhầm là công thức.
     * Nội dung gốc được giữ nguyên, chỉ thêm một dấu nháy đơn ở đầu.
     */
    public static function safeSpreadsheetText(?string $value): string
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        foreach (self::FORMULA_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return "'".$value;
            }
        }

        return $value;
    }

    /**
     * Kết luận giám định đưa về nhóm chuẩn. Không suy đoán khi chuỗi lạ:
     * trả về `khac` để quản lý xem lại thay vì mặc định coi là từ chối.
     */
    public static function conclusion(?string $raw): string
    {
        $value = mb_strtolower(trim((string) $raw), 'UTF-8');

        return match (true) {
            $value === '' => 'trong',
            str_contains($value, 'từ chối') || str_contains($value, 'tu choi') => 'tu_choi',
            str_contains($value, 'cảnh báo') || str_contains($value, 'canh bao') => 'canh_bao',
            str_contains($value, 'chấp nhận') || str_contains($value, 'chap nhan') => 'chap_nhan',
            default => 'khac',
        };
    }
}
