<?php

namespace App\Services\Assignment;

use App\Models\Assignment;
use App\Models\AssignmentRule;
use App\Models\ErrorCatalog;
use App\Models\IssueCase;
use App\Models\SourceFile;
use App\Models\SourceRow;
use App\Support\Normalizer;
use Illuminate\Support\Facades\DB;

/**
 * Sinh vụ việc giải trình từ các dòng nguồn đã nạp.
 *
 * Một dòng trong một tệp là một vụ việc. Dòng tổng hợp và dòng đã được BHYT đánh dấu
 * "Chấp nhận" vẫn được tạo vụ việc để đối soát đủ số dòng, nhưng không bắt buộc giải trình
 * nên không chặn tiến độ.
 */
final class IssueCaseBuilder
{
    private const CHUNK = 500;

    /**
     * @return array{created: int, assigned: int, unassigned: int, no_explanation_needed: int}
     */
    public function build(SourceFile $file, ?int $actorId = null): array
    {
        $batch = $file->batch;
        $effectiveDate = $batch?->effectiveDate();
        $catalog = ErrorCatalog::where('code', $file->error_code ?? 'UNKNOWN')->first();
        $rule = AssignmentRule::query()
            ->where('error_code', $file->error_code ?? 'UNKNOWN')
            ->effectiveOn($effectiveDate)
            ->orderByDesc('effective_from')
            ->first();

        $resolver = new AssignmentResolver($effectiveDate);
        $dueOn = $rule ? now()->addDays($rule->due_days)->toDateString() : now()->addDays(7)->toDateString();

        $stats = ['created' => 0, 'assigned' => 0, 'unassigned' => 0, 'no_explanation_needed' => 0];

        SourceRow::query()
            ->where('source_sheet_id', $file->dataSheet()?->id)
            ->orderBy('row_number')
            ->chunkById(self::CHUNK, function ($rows) use (
                $file, $catalog, $rule, $resolver, $dueOn, $actorId, &$stats
            ) {
                $cases = [];
                $now = now();

                foreach ($rows as $row) {
                    $conclusion = Normalizer::conclusion($row->ket_luan);
                    $requiresExplanation = $row->row_type === SourceRow::TYPE_COST
                        && in_array($conclusion, ['tu_choi', 'canh_bao'], true);

                    $placement = $requiresExplanation
                        ? $resolver->resolve($row, $rule, $catalog)
                        : ['assignee_id' => null, 'department_id' => null, 'resolver' => 'not_required', 'status' => IssueCase::STATUS_UNASSIGNED];

                    $cases[] = [
                        'import_batch_id' => $file->import_batch_id,
                        'source_file_id' => $file->id,
                        'source_row_id' => $row->id,
                        'error_catalog_id' => $catalog?->id,
                        'error_code' => $file->error_code ?? 'UNKNOWN',
                        'conclusion' => $row->ket_luan,
                        'requires_explanation' => $requiresExplanation,
                        'amount_reference' => $row->tien_de_nghi_kiem_tra,
                        'status' => $placement['status'] === 'assigned'
                            ? IssueCase::STATUS_ASSIGNED
                            : IssueCase::STATUS_UNASSIGNED,
                        'assignee_id' => $placement['assignee_id'],
                        'department_id' => $placement['department_id'],
                        'assignment_source' => $placement['resolver'],
                        'priority' => $catalog?->priority ?? 3,
                        'due_on' => $requiresExplanation ? $dueOn : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $stats['created']++;
                    if (! $requiresExplanation) {
                        $stats['no_explanation_needed']++;
                    } elseif ($placement['assignee_id'] !== null) {
                        $stats['assigned']++;
                    } else {
                        $stats['unassigned']++;
                    }
                }

                DB::table('issue_cases')->insertOrIgnore($cases);
                $this->recordInitialAssignments($file, $cases, $actorId);
            });

        return $stats;
    }

    /**
     * Ghi lịch sử phân công lần đầu để về sau truy được vì sao một dòng rơi vào ai.
     *
     * @param  list<array<string, mixed>>  $cases
     */
    private function recordInitialAssignments(SourceFile $file, array $cases, ?int $actorId): void
    {
        $rowIds = array_column($cases, 'source_row_id');
        if ($rowIds === []) {
            return;
        }

        $caseIds = DB::table('issue_cases')
            ->where('source_file_id', $file->id)
            ->whereIn('source_row_id', $rowIds)
            ->pluck('id', 'source_row_id');

        $records = [];
        $now = now();

        foreach ($cases as $case) {
            $caseId = $caseIds[$case['source_row_id']] ?? null;
            if ($caseId === null || $case['assignment_source'] === 'not_required') {
                continue;
            }

            $records[] = [
                'issue_case_id' => $caseId,
                'assignee_id' => $case['assignee_id'],
                'department_id' => $case['department_id'],
                'assigned_by' => $actorId,
                'resolver' => $case['assignment_source'],
                'reason' => 'Phân công tự động khi nạp đợt.',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($records !== []) {
            DB::table('assignments')->insert($records);
        }
    }

    /** Chuyển người phụ trách một vụ việc, luôn kèm lý do và lưu lịch sử. */
    public function reassign(
        IssueCase $case,
        ?int $assigneeId,
        ?int $departmentId,
        string $reason,
        ?int $actorId,
    ): IssueCase {
        DB::transaction(function () use ($case, $assigneeId, $departmentId, $reason, $actorId) {
            $case->fill([
                'assignee_id' => $assigneeId,
                'department_id' => $departmentId,
                'assignment_source' => 'manual',
                'status' => $assigneeId !== null && $case->status === IssueCase::STATUS_UNASSIGNED
                    ? IssueCase::STATUS_ASSIGNED
                    : $case->status,
            ])->save();

            Assignment::create([
                'issue_case_id' => $case->id,
                'assignee_id' => $assigneeId,
                'department_id' => $departmentId,
                'assigned_by' => $actorId,
                'resolver' => 'manual',
                'reason' => $reason,
            ]);
        });

        return $case->refresh();
    }
}
