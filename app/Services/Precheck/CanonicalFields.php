<?php

namespace App\Services\Precheck;

use App\Support\Normalizer;

/**
 * Bộ trường chuẩn dùng chung cho mọi định dạng đầu vào của luồng tự kiểm tra.
 *
 * XML gửi giám định, XLSX và CSV xuất từ HIS đều được đưa về đây, nhờ vậy một
 * quy tắc viết một lần chạy được trên cả ba nguồn khi chúng chứa cùng dữ liệu.
 *
 * Lưu ý về tên trùng nghĩa khác: `KET_LUAN` của XML4 là kết luận chuyên môn cận lâm sàng,
 * còn `KET_LUAN` của tệp phản hồi là kết luận giám định. Ở đây chỉ có `gia_tri`/`ket_luan_cls`
 * của cận lâm sàng; kết luận giám định thuộc luồng phản hồi và không nằm trong mô hình này.
 */
final class CanonicalFields
{
    public const KIND_MONEY = 'money';

    public const KIND_DATETIME = 'datetime';

    public const KIND_NUMBER = 'number';

    public const KIND_CODE = 'code';

    public const KIND_TEXT = 'text';

    /** @var array<string, string> trường hồ sơ -> kiểu dữ liệu */
    public const ENCOUNTER = [
        'ma_lk' => self::KIND_TEXT,
        'ma_bn' => self::KIND_TEXT,
        'ma_the' => self::KIND_TEXT,
        'ma_khoa' => self::KIND_CODE,
        'ma_benh_chinh' => self::KIND_CODE,
        'ma_benh_kt' => self::KIND_TEXT,
        'ma_loai_kcb' => self::KIND_CODE,
        'ngay_vao' => self::KIND_DATETIME,
        'ngay_ra' => self::KIND_DATETIME,
        't_tongchi' => self::KIND_MONEY,
        't_bhtt' => self::KIND_MONEY,
    ];

    /** @var array<string, string> trường chi tiết -> kiểu dữ liệu */
    public const DETAIL = [
        'ma_lk' => self::KIND_TEXT,
        'stt' => self::KIND_NUMBER,
        'ma_dich_vu' => self::KIND_CODE,
        'ma_vat_tu' => self::KIND_CODE,
        'ma_thuoc' => self::KIND_CODE,
        'ten' => self::KIND_TEXT,
        'ma_chi_so' => self::KIND_CODE,
        'gia_tri' => self::KIND_TEXT,
        'ma_bac_si' => self::KIND_CODE,
        'nguoi_thuc_hien' => self::KIND_TEXT,
        'ma_may' => self::KIND_CODE,
        'ma_khoa' => self::KIND_CODE,
        'so_luong' => self::KIND_NUMBER,
        'thanh_tien' => self::KIND_MONEY,
        'ngay_yl' => self::KIND_DATETIME,
        'ngay_th_yl' => self::KIND_DATETIME,
        'ngay_kq' => self::KIND_DATETIME,
    ];

    /**
     * Ép kiểu một giá trị thô về kiểu của trường chuẩn.
     *
     * Giá trị không đọc được trả về null để quy tắc báo "không đủ dữ liệu",
     * thay vì bị quy về 0 hoặc chuỗi rỗng rồi kết luận nhầm là đạt.
     */
    public static function cast(string $kind, mixed $raw): mixed
    {
        return match ($kind) {
            self::KIND_MONEY => Normalizer::money($raw),
            self::KIND_DATETIME => Normalizer::dateTime($raw),
            self::KIND_NUMBER => self::number($raw),
            self::KIND_CODE => Normalizer::code($raw),
            default => Normalizer::text($raw),
        };
    }

    public static function kindOfDetailField(string $field): string
    {
        return self::DETAIL[$field] ?? self::KIND_TEXT;
    }

    public static function kindOfEncounterField(string $field): string
    {
        return self::ENCOUNTER[$field] ?? self::KIND_TEXT;
    }

    private static function number(mixed $raw): int|float|null
    {
        $value = Normalizer::money($raw);
        if ($value === null) {
            return null;
        }

        return floor($value) === $value ? (int) $value : $value;
    }
}
