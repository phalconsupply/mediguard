<?php

namespace Tests\Unit;

use App\Models\Finding;
use App\Services\Rules\RuleDefinition;
use App\Services\Rules\RuleEvaluator;
use PHPUnit\Framework\TestCase;

class RuleEvaluatorTest extends TestCase
{
    private RuleEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new RuleEvaluator;
    }

    public function test_a_missing_required_field_is_reported_as_insufficient_data_not_as_a_pass(): void
    {
        $definition = [
            'subject' => 'detail',
            'condition' => ['any' => [['field' => 'ngay_kq', 'op' => 'before_field', 'value' => 'ngay_th_yl']]],
            'message' => 'Giờ kết quả trước giờ thực hiện.',
        ];

        $outcome = $this->evaluator->evaluate(
            ['ngay_th_yl' => '2026-07-03 11:15:00', 'ngay_kq' => null],
            $definition,
            ['ngay_th_yl', 'ngay_kq'],
        );

        $this->assertSame(Finding::RESULT_INSUFFICIENT, $outcome['result']);
        $this->assertSame(['ngay_kq'], $outcome['missing']);
    }

    public function test_it_detects_a_result_recorded_before_the_service_was_performed(): void
    {
        $outcome = $this->evaluator->evaluate(
            ['ngay_th_yl' => '2026-07-03 11:15:00', 'ngay_kq' => '2026-07-03 11:00:00'],
            [
                'subject' => 'detail',
                'condition' => ['any' => [['field' => 'ngay_kq', 'op' => 'before_field', 'value' => 'ngay_th_yl']]],
                'message' => 'Giờ kết quả trước giờ thực hiện.',
            ],
            ['ngay_th_yl', 'ngay_kq'],
        );

        $this->assertSame(Finding::RESULT_DETECTED, $outcome['result']);
        $this->assertSame('Giờ kết quả trước giờ thực hiện.', $outcome['reason']);
        $this->assertSame('2026-07-03 11:00:00', $outcome['values']['ngay_kq']);
    }

    public function test_enough_data_and_no_match_gives_not_detected(): void
    {
        $outcome = $this->evaluator->evaluate(
            ['ngay_th_yl' => '2026-07-03 11:00:00', 'ngay_kq' => '2026-07-03 11:15:00'],
            [
                'subject' => 'detail',
                'condition' => ['any' => [['field' => 'ngay_kq', 'op' => 'before_field', 'value' => 'ngay_th_yl']]],
            ],
            ['ngay_th_yl', 'ngay_kq'],
        );

        $this->assertSame(Finding::RESULT_NOT_DETECTED, $outcome['result']);
    }

    public function test_same_minute_comparison_ignores_seconds(): void
    {
        $outcome = $this->evaluator->evaluate(
            ['ngay_th_yl' => '2026-07-03 11:15:00', 'ngay_kq' => '2026-07-03 11:15:59'],
            [
                'subject' => 'detail',
                'condition' => ['any' => [['field' => 'ngay_kq', 'op' => 'same_minute_as_field', 'value' => 'ngay_th_yl']]],
                'message' => 'Thời lượng thực hiện bằng 0.',
            ],
            ['ngay_th_yl', 'ngay_kq'],
        );

        $this->assertSame(Finding::RESULT_DETECTED, $outcome['result']);
    }

    public function test_a_record_outside_the_filter_is_marked_out_of_scope_not_checked(): void
    {
        $outcome = $this->evaluator->evaluate(
            ['detail_kind' => 'thuoc', 'ngay_kq' => null],
            [
                'subject' => 'detail',
                'filter' => ['all' => [['field' => 'detail_kind', 'op' => 'equals', 'value' => 'dvkt']]],
                'condition' => ['any' => [['field' => 'ngay_kq', 'op' => 'is_empty']]],
            ],
            ['ngay_kq'],
        );

        $this->assertTrue($outcome['out_of_scope'] ?? false);
    }

    public function test_absence_operators_treat_an_empty_cell_as_a_valid_answer(): void
    {
        $definition = [
            'subject' => 'detail',
            'condition' => ['all' => [
                ['field' => 'nguoi_thuc_hien', 'op' => 'is_empty'],
                ['field' => 'ma_dich_vu', 'op' => 'is_present'],
            ]],
            'message' => 'Dịch vụ kỹ thuật không ghi người thực hiện.',
        ];

        $detected = $this->evaluator->evaluate(
            ['nguoi_thuc_hien' => null, 'ma_dich_vu' => 'DV001'],
            $definition,
            [],
        );

        $notDetected = $this->evaluator->evaluate(
            ['nguoi_thuc_hien' => 'NV001', 'ma_dich_vu' => 'DV001'],
            $definition,
            [],
        );

        $this->assertSame(Finding::RESULT_DETECTED, $detected['result']);
        $this->assertSame(Finding::RESULT_NOT_DETECTED, $notDetected['result']);
    }

    public function test_a_false_branch_settles_an_all_group_even_when_another_branch_lacks_data(): void
    {
        $outcome = $this->evaluator->evaluate(
            ['detail_kind' => 'thuoc', 'ngay_kq' => null],
            [
                'subject' => 'detail',
                'condition' => ['all' => [
                    ['field' => 'detail_kind', 'op' => 'equals', 'value' => 'dvkt'],
                    ['field' => 'ngay_kq', 'op' => 'before_field', 'value' => 'ngay_th_yl'],
                ]],
            ],
            [],
        );

        // Vế đầu đã sai nên cả nhóm sai, không cần vế thiếu dữ liệu.
        $this->assertSame(Finding::RESULT_NOT_DETECTED, $outcome['result']);
    }

    public function test_an_any_group_that_only_lacks_data_cannot_conclude(): void
    {
        $outcome = $this->evaluator->evaluate(
            ['ngay_kq' => null, 'ngay_th_yl' => null],
            [
                'subject' => 'detail',
                'condition' => ['any' => [
                    ['field' => 'ngay_kq', 'op' => 'before_field', 'value' => 'ngay_th_yl'],
                ]],
            ],
            [],
        );

        $this->assertSame(Finding::RESULT_INSUFFICIENT, $outcome['result']);
    }

    public function test_the_encounter_window_needs_both_admission_and_discharge(): void
    {
        $definition = [
            'subject' => 'detail',
            'condition' => ['any' => [['field' => 'ngay_kq', 'op' => 'outside_encounter_window']]],
            'message' => 'Giờ kết quả ngoài thời gian điều trị.',
        ];

        $inside = $this->evaluator->evaluate(
            ['ngay_kq' => '2026-07-03 10:00:00'],
            $definition,
            ['ngay_kq'],
            ['ngay_vao' => '2026-07-03 08:00:00', 'ngay_ra' => '2026-07-03 16:00:00'],
        );

        $outside = $this->evaluator->evaluate(
            ['ngay_kq' => '2026-07-03 17:00:00'],
            $definition,
            ['ngay_kq'],
            ['ngay_vao' => '2026-07-03 08:00:00', 'ngay_ra' => '2026-07-03 16:00:00'],
        );

        $noWindow = $this->evaluator->evaluate(
            ['ngay_kq' => '2026-07-03 17:00:00'],
            $definition,
            ['ngay_kq'],
            ['ngay_vao' => '2026-07-03 08:00:00', 'ngay_ra' => null],
        );

        $this->assertSame(Finding::RESULT_NOT_DETECTED, $inside['result']);
        $this->assertSame(Finding::RESULT_DETECTED, $outside['result']);
        $this->assertSame(Finding::RESULT_INSUFFICIENT, $noWindow['result']);
    }

    public function test_definition_validation_rejects_an_unknown_operator(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RuleDefinition::validate([
            'subject' => 'detail',
            'condition' => ['any' => [['field' => 'ngay_kq', 'op' => 'khong_ton_tai']]],
        ]);
    }

    public function test_definition_validation_requires_a_known_subject(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RuleDefinition::validate([
            'subject' => 'hoso',
            'condition' => ['any' => [['field' => 'ngay_kq', 'op' => 'is_empty']]],
        ]);
    }

    public function test_referenced_fields_include_both_sides_of_a_comparison(): void
    {
        $fields = RuleDefinition::referencedFields([
            'subject' => 'detail',
            'filter' => ['all' => [['field' => 'detail_kind', 'op' => 'equals', 'value' => 'dvkt']]],
            'condition' => ['any' => [['field' => 'ngay_kq', 'op' => 'before_field', 'value' => 'ngay_th_yl']]],
        ]);

        sort($fields);
        $this->assertSame(['detail_kind', 'ngay_kq', 'ngay_th_yl'], $fields);
    }
}
