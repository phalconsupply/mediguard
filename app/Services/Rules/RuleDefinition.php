<?php

namespace App\Services\Rules;

/**
 * Cấu trúc một định nghĩa quy tắc.
 *
 * Quy tắc mô tả bằng dữ liệu để bộ phận BHYT soạn và xem lại được, không phải bằng mã.
 * Quy tắc phức tạp hơn khả năng mô tả ở đây sẽ do CNTT bổ sung thành bộ kiểm tra riêng
 * rồi đăng ký vào cùng thư viện phiên bản.
 *
 * Ví dụ:
 * {
 *   "subject": "detail",
 *   "filter": {"all": [{"field": "detail_kind", "op": "equals", "value": "dvkt"}]},
 *   "condition": {"any": [{"field": "ngay_kq", "op": "before_field", "value": "ngay_th_yl"}]},
 *   "message": "Giờ kết quả trước giờ thực hiện y lệnh."
 * }
 *
 * `condition` đúng nghĩa là **phát hiện nguy cơ**, không phải hồ sơ đạt.
 */
final class RuleDefinition
{
    public const SUBJECT_DETAIL = 'detail';

    public const SUBJECT_ENCOUNTER = 'encounter';

    /** Toán tử so sánh một bản ghi. */
    public const OPERATORS = [
        'equals', 'not_equals', 'in', 'not_in', 'contains', 'starts_with',
        'is_empty', 'is_present', 'gt', 'gte', 'lt', 'lte',
        'before_field', 'after_field', 'same_minute_as_field',
        'minutes_between_lt', 'minutes_between_gt',
        'outside_encounter_window',
    ];

    /**
     * Toán tử cần cả hai vế có giá trị. Thiếu một vế thì kết quả là
     * "không đủ dữ liệu để kiểm tra", không phải "không phát hiện".
     */
    public const TWO_SIDED_OPERATORS = [
        'before_field', 'after_field', 'same_minute_as_field',
        'minutes_between_lt', 'minutes_between_gt', 'outside_encounter_window',
    ];

    /** Toán tử vốn dùng để kiểm tra sự vắng mặt, nên ô trống là kết quả hợp lệ. */
    public const ABSENCE_OPERATORS = ['is_empty', 'is_present'];

    /** @param array<string, mixed> $definition */
    public static function validate(array $definition): void
    {
        $subject = $definition['subject'] ?? null;
        if (! in_array($subject, [self::SUBJECT_DETAIL, self::SUBJECT_ENCOUNTER], true)) {
            throw new \InvalidArgumentException('Quy tắc phải khai báo subject là detail hoặc encounter.');
        }

        if (! isset($definition['condition']) || ! is_array($definition['condition'])) {
            throw new \InvalidArgumentException('Quy tắc phải có điều kiện phát hiện.');
        }

        self::validateGroup($definition['condition']);

        if (isset($definition['filter'])) {
            self::validateGroup($definition['filter']);
        }
    }

    /** @param array<string, mixed> $group */
    private static function validateGroup(array $group): void
    {
        $key = array_key_first($group);
        if (! in_array($key, ['all', 'any', 'none'], true)) {
            throw new \InvalidArgumentException('Nhóm điều kiện phải là all, any hoặc none.');
        }

        foreach ($group[$key] as $item) {
            if (isset($item['all']) || isset($item['any']) || isset($item['none'])) {
                self::validateGroup($item);

                continue;
            }

            if (! isset($item['field'], $item['op'])) {
                throw new \InvalidArgumentException('Mỗi điều kiện phải có field và op.');
            }

            if (! in_array($item['op'], self::OPERATORS, true)) {
                throw new \InvalidArgumentException("Toán tử {$item['op']} chưa được hỗ trợ.");
            }
        }
    }

    /**
     * Các trường mà một định nghĩa thực sự đụng tới. Dùng để tính độ phủ
     * và để cảnh báo khi cấu hình `required_fields` bỏ sót trường quan trọng.
     *
     * @param  array<string, mixed>  $definition
     * @return list<string>
     */
    public static function referencedFields(array $definition): array
    {
        $fields = [];

        $walk = function (array $group) use (&$walk, &$fields): void {
            $key = array_key_first($group);
            if (! in_array($key, ['all', 'any', 'none'], true)) {
                return;
            }

            foreach ($group[$key] as $item) {
                if (isset($item['all']) || isset($item['any']) || isset($item['none'])) {
                    $walk($item);

                    continue;
                }

                $fields[] = $item['field'];
                if (in_array($item['op'], self::TWO_SIDED_OPERATORS, true) && isset($item['value'])) {
                    $fields[] = (string) $item['value'];
                }
            }
        };

        if (isset($definition['condition'])) {
            $walk($definition['condition']);
        }
        if (isset($definition['filter'])) {
            $walk($definition['filter']);
        }

        return array_values(array_unique($fields));
    }
}
