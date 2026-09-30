<?php

namespace App\Services\Rules;

use App\Models\CheckRuleVersion;
use App\Models\Finding;
use App\Models\ImportBatch;
use App\Models\PrecheckDetail;
use App\Models\PrecheckEncounter;
use App\Models\PrecheckRun;
use App\Models\SourceRow;
use App\Services\Assignment\AssignmentResolver;
use Illuminate\Support\Facades\DB;

/**
 * Chạy một bộ quy tắc đã duyệt trên dữ liệu của một đợt tự kiểm tra.
 *
 * Lần chạy chụp lại phiên bản từng quy tắc và hash tệp đầu vào, nên kết quả
 * đọc lại được nguyên trạng dù thư viện quy tắc đổi sau đó.
 *
 * Chỉ ghi `detected` và `insufficient_data` xuống bảng phát hiện. `not_detected`
 * được đếm vào độ phủ thay vì sinh hàng triệu bản ghi không mang thông tin.
 */
final class PrecheckRunner
{
    private const CHUNK = 500;

    public function __construct(private readonly RuleEvaluator $evaluator) {}

    public function run(PrecheckRun $run): PrecheckRun
    {
        $batch = $run->batch;
        $versions = $this->resolveVersions($run);

        $run->fill(['status' => 'running', 'started_at' => now()])->save();

        try {
            $coverage = [];
            foreach ($versions as $version) {
                $coverage[$version->rule->code] = $this->runVersion($run, $batch, $version);
            }

            $run->fill([
                'status' => 'completed',
                'coverage' => $coverage,
                'encounters_total' => PrecheckEncounter::where('import_batch_id', $batch->id)->count(),
                'details_total' => PrecheckDetail::where('import_batch_id', $batch->id)->count(),
                'detected_count' => $run->findings()->where('result', Finding::RESULT_DETECTED)->count(),
                'insufficient_count' => $run->findings()->where('result', Finding::RESULT_INSUFFICIENT)->count(),
                'finished_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            $run->fill([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
                'finished_at' => now(),
            ])->save();

            throw $e;
        }

        return $run->refresh();
    }

    /**
     * Đúng các phiên bản đã chụp lúc tạo lần chạy được dùng, kể cả khi thư viện
     * đã có phiên bản mới hơn.
     *
     * @return list<CheckRuleVersion>
     */
    private function resolveVersions(PrecheckRun $run): array
    {
        $ids = array_column($run->ruleset_snapshot ?? [], 'rule_version_id');

        return CheckRuleVersion::with('rule')->whereIn('id', $ids)->get()->all();
    }

    /**
     * @return array{checked: int, detected: int, insufficient: int, out_of_scope: int}
     */
    private function runVersion(PrecheckRun $run, ImportBatch $batch, CheckRuleVersion $version): array
    {
        $definition = $version->definition;
        $required = $version->required_fields ?? [];
        $subject = $definition['subject'] ?? RuleDefinition::SUBJECT_DETAIL;
        $resolver = new AssignmentResolver($batch->effectiveDate());

        $stats = ['checked' => 0, 'detected' => 0, 'insufficient' => 0, 'out_of_scope' => 0];
        $buffer = [];

        $query = $subject === RuleDefinition::SUBJECT_ENCOUNTER
            ? PrecheckEncounter::where('import_batch_id', $batch->id)
            : PrecheckDetail::with('encounter:id,ma_lk,ngay_vao,ngay_ra,ma_khoa')->where('import_batch_id', $batch->id);

        $query->orderBy('id')->chunkById(self::CHUNK, function ($records) use (
            $run, $version, $definition, $required, $subject, $resolver, &$stats, &$buffer
        ) {
            foreach ($records as $record) {
                $attributes = $this->toArray($record);
                $context = $subject === RuleDefinition::SUBJECT_DETAIL
                    ? $this->encounterContext($record)
                    : ['ngay_vao' => $attributes['ngay_vao'] ?? null, 'ngay_ra' => $attributes['ngay_ra'] ?? null];
                $context['threshold'] = $definition['threshold_minutes'] ?? 0;

                $outcome = $this->evaluator->evaluate($attributes, $definition, $required, $context);

                if (($outcome['out_of_scope'] ?? false) === true) {
                    $stats['out_of_scope']++;

                    continue;
                }

                $stats['checked']++;

                if ($outcome['result'] === Finding::RESULT_NOT_DETECTED) {
                    continue;
                }

                $stats[$outcome['result'] === Finding::RESULT_DETECTED ? 'detected' : 'insufficient']++;

                $placement = $this->placeFinding($record, $subject, $resolver);

                $buffer[] = [
                    'precheck_run_id' => $run->id,
                    'check_rule_version_id' => $version->id,
                    'rule_code' => $version->rule->code,
                    'result' => $outcome['result'],
                    'ma_lk' => $attributes['ma_lk'] ?? null,
                    'precheck_detail_id' => $subject === RuleDefinition::SUBJECT_DETAIL ? $record->id : null,
                    'locator' => json_encode($record->locator, JSON_UNESCAPED_UNICODE),
                    'values' => json_encode($outcome['values'], JSON_UNESCAPED_UNICODE),
                    'reason' => $outcome['reason'],
                    'missing_fields' => json_encode($outcome['missing'], JSON_UNESCAPED_UNICODE),
                    'assignee_id' => $placement['assignee_id'],
                    'department_id' => $placement['department_id'],
                    'handling_status' => 'open',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($buffer) >= self::CHUNK) {
                    DB::table('findings')->insert($buffer);
                    $buffer = [];
                }
            }
        });

        if ($buffer !== []) {
            DB::table('findings')->insert($buffer);
        }

        return $stats;
    }

    /**
     * Phát hiện được giao cho người chỉ định/người thực hiện như luồng giải trình.
     * Ở đây dùng lại bộ chọn người của luồng phản hồi bằng một dòng nguồn giả lập,
     * để hai luồng không lệch quy tắc phân công.
     *
     * @return array{assignee_id: ?int, department_id: ?int}
     */
    private function placeFinding(object $record, string $subject, AssignmentResolver $resolver): array
    {
        $row = new SourceRow;
        $row->ma_bsi_thuc_hien = $subject === RuleDefinition::SUBJECT_DETAIL
            ? ($record->nguoi_thuc_hien ?: null)
            : null;
        $row->ma_bac_si = $record->ma_bac_si ?? null;
        $row->ma_cp = $record->ma_dich_vu ?? null;
        $row->ma_khoa = $record->ma_khoa ?? null;

        $placement = $resolver->resolve($row, null, null);

        return ['assignee_id' => $placement['assignee_id'], 'department_id' => $placement['department_id']];
    }

    /** @return array<string, mixed> */
    private function toArray(object $record): array
    {
        $attributes = $record->getAttributes();

        // `extra` giữ các trường chưa ánh xạ; đưa lên cùng cấp để quy tắc dùng được
        // mà không phải nạp lại tệp khi mở rộng điều kiện.
        $extra = $record->extra ?? [];
        foreach ($extra as $key => $value) {
            $key = mb_strtolower((string) $key);
            if (! array_key_exists($key, $attributes)) {
                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }

    /** @return array<string, mixed> */
    private function encounterContext(object $detail): array
    {
        $encounter = $detail->encounter ?? null;

        return [
            'ngay_vao' => $encounter?->ngay_vao,
            'ngay_ra' => $encounter?->ngay_ra,
            'encounter_ma_khoa' => $encounter?->ma_khoa,
        ];
    }
}
