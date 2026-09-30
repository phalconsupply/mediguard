<?php

namespace App\Services\Rules;

use App\Models\Finding;
use DateTimeInterface;

/**
 * Đánh giá một quy tắc trên một bản ghi.
 *
 * Ba kết quả tách bạch và không bao giờ lẫn nhau:
 *  - `detected`          : điều kiện đúng -> có nguy cơ bị xuất toán.
 *  - `not_detected`      : đủ dữ liệu, điều kiện không đúng.
 *  - `insufficient_data` : thiếu trường bắt buộc -> chưa kết luận được.
 *
 * Thiếu dữ liệu không bao giờ được quy về `not_detected`.
 */
final class RuleEvaluator
{
    /**
     * @param  array<string, mixed>  $record  bản ghi đã chuẩn hóa
     * @param  array<string, mixed>  $definition
     * @param  list<string>  $requiredFields
     * @param  array<string, mixed>  $context  dữ liệu cấp hồ sơ dùng cho các toán tử so với cửa sổ điều trị
     * @return array{result: string, reason: ?string, missing: list<string>, values: array<string, mixed>}
     */
    public function evaluate(array $record, array $definition, array $requiredFields, array $context = []): array
    {
        // Bước lọc chạy trước: bản ghi ngoài phạm vi áp dụng không tính vào độ phủ.
        if (isset($definition['filter']) && ! $this->matchesFilter($record, $definition['filter'])) {
            return ['result' => Finding::RESULT_NOT_DETECTED, 'reason' => null, 'missing' => [], 'values' => [], 'out_of_scope' => true];
        }

        $missing = $this->missingFields($record, $requiredFields);
        if ($missing !== []) {
            return [
                'result' => Finding::RESULT_INSUFFICIENT,
                'reason' => 'Thiếu trường bắt buộc: '.implode(', ', $missing).'.',
                'missing' => $missing,
                'values' => $this->collectValues($record, $requiredFields),
            ];
        }

        $outcome = $this->evaluateGroup($record, $definition['condition'], $context);

        if ($outcome === null) {
            $referenced = RuleDefinition::referencedFields($definition);

            return [
                'result' => Finding::RESULT_INSUFFICIENT,
                'reason' => 'Không so sánh được vì một vế của điều kiện không có giá trị.',
                'missing' => $this->missingFields($record, $referenced),
                'values' => $this->collectValues($record, $referenced),
            ];
        }

        return [
            'result' => $outcome ? Finding::RESULT_DETECTED : Finding::RESULT_NOT_DETECTED,
            'reason' => $outcome ? ($definition['message'] ?? null) : null,
            'missing' => [],
            'values' => $outcome ? $this->collectValues($record, RuleDefinition::referencedFields($definition)) : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $filter
     */
    private function matchesFilter(array $record, array $filter): bool
    {
        // Bộ lọc thiếu dữ liệu coi như không thuộc phạm vi áp dụng.
        return $this->evaluateGroup($record, $filter, []) === true;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $group
     * @param  array<string, mixed>  $context
     * @return bool|null null nghĩa là không so sánh được
     */
    private function evaluateGroup(array $record, array $group, array $context): ?bool
    {
        $mode = array_key_first($group);
        $items = $group[$mode] ?? [];
        $results = [];

        foreach ($items as $item) {
            $result = (isset($item['all']) || isset($item['any']) || isset($item['none']))
                ? $this->evaluateGroup($record, $item, $context)
                : $this->evaluateCondition($record, $item, $context);

            $results[] = $result;
        }

        return match ($mode) {
            'all' => $this->combineAll($results),
            'any' => $this->combineAny($results),
            'none' => match ($this->combineAny($results)) {
                true => false,
                false => true,
                default => null,
            },
            default => null,
        };
    }

    /** @param list<bool|null> $results */
    private function combineAll(array $results): ?bool
    {
        // Một vế sai đủ kết luận cả nhóm sai, kể cả khi vế khác thiếu dữ liệu.
        if (in_array(false, $results, true)) {
            return false;
        }

        return in_array(null, $results, true) ? null : true;
    }

    /** @param list<bool|null> $results */
    private function combineAny(array $results): ?bool
    {
        if (in_array(true, $results, true)) {
            return true;
        }

        return in_array(null, $results, true) ? null : false;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array{field: string, op: string, value?: mixed}  $condition
     * @param  array<string, mixed>  $context
     */
    private function evaluateCondition(array $record, array $condition, array $context): ?bool
    {
        $field = $condition['field'];
        $op = $condition['op'];
        $expected = $condition['value'] ?? null;
        $actual = $record[$field] ?? null;

        if ($op === 'is_empty') {
            return $this->isBlank($actual);
        }
        if ($op === 'is_present') {
            return ! $this->isBlank($actual);
        }

        if ($this->isBlank($actual)) {
            return null;
        }

        if (in_array($op, RuleDefinition::TWO_SIDED_OPERATORS, true)) {
            return $this->evaluateTwoSided($record, $op, $actual, $expected, $context);
        }

        return match ($op) {
            'equals' => $this->scalar($actual) === $this->scalar($expected),
            'not_equals' => $this->scalar($actual) !== $this->scalar($expected),
            'in' => is_array($expected) && in_array($this->scalar($actual), array_map([$this, 'scalar'], $expected), true),
            'not_in' => is_array($expected) && ! in_array($this->scalar($actual), array_map([$this, 'scalar'], $expected), true),
            'contains' => str_contains(mb_strtolower((string) $actual), mb_strtolower((string) $expected)),
            'starts_with' => str_starts_with(mb_strtolower((string) $actual), mb_strtolower((string) $expected)),
            'gt' => $this->numeric($actual) !== null && $this->numeric($actual) > (float) $expected,
            'gte' => $this->numeric($actual) !== null && $this->numeric($actual) >= (float) $expected,
            'lt' => $this->numeric($actual) !== null && $this->numeric($actual) < (float) $expected,
            'lte' => $this->numeric($actual) !== null && $this->numeric($actual) <= (float) $expected,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $context
     */
    private function evaluateTwoSided(array $record, string $op, mixed $actual, mixed $expected, array $context): ?bool
    {
        if ($op === 'outside_encounter_window') {
            return $this->outsideEncounterWindow($actual, $context);
        }

        $other = $record[(string) $expected] ?? null;
        if ($this->isBlank($other)) {
            return null;
        }

        $left = $this->toTimestamp($actual);
        $right = $this->toTimestamp($other);
        if ($left === null || $right === null) {
            return null;
        }

        return match ($op) {
            'before_field' => $left < $right,
            'after_field' => $left > $right,
            // So đến phút vì nhiều mốc trong nguồn chỉ chính xác đến phút.
            'same_minute_as_field' => intdiv($left, 60) === intdiv($right, 60),
            'minutes_between_lt' => ($right - $left) / 60 < (float) ($context['threshold'] ?? 0),
            'minutes_between_gt' => ($right - $left) / 60 > (float) ($context['threshold'] ?? 0),
            default => null,
        };
    }

    /** @param array<string, mixed> $context */
    private function outsideEncounterWindow(mixed $actual, array $context): ?bool
    {
        $from = $this->toTimestamp($context['ngay_vao'] ?? null);
        $to = $this->toTimestamp($context['ngay_ra'] ?? null);
        $at = $this->toTimestamp($actual);

        if ($at === null || $from === null || $to === null) {
            return null;
        }

        return $at < $from || $at > $to;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  list<string>  $fields
     * @return list<string>
     */
    private function missingFields(array $record, array $fields): array
    {
        $missing = [];
        foreach ($fields as $field) {
            if ($this->isBlank($record[$field] ?? null)) {
                $missing[] = $field;
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function collectValues(array $record, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            if (! array_key_exists($field, $record)) {
                continue;
            }
            $value = $record[$field];
            $values[$field] = $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value;
        }

        return $values;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || (is_array($value) && $value === []);
    }

    private function scalar(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }

    private function numeric(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        return is_numeric((string) $value) ? (float) $value : null;
    }

    private function toTimestamp(mixed $value): ?int
    {
        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }
        if ($this->isBlank($value)) {
            return null;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : $timestamp;
    }
}
