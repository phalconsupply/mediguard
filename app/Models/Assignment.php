<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lịch sử phân công; đổi người phụ trách luôn kèm lý do. */
#[Fillable(['issue_case_id', 'assignee_id', 'department_id', 'assigned_by', 'resolver', 'reason'])]
class Assignment extends Model
{
    public function issueCase(): BelongsTo
    {
        return $this->belongsTo(IssueCase::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
