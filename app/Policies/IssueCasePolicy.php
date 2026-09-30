<?php

namespace App\Policies;

use App\Models\IssueCase;
use App\Models\User;

/**
 * Phân quyền ở tầng dữ liệu, không chỉ ẩn nút trên giao diện.
 *
 * Khoa chỉ xem dòng được giao cho mình hoặc cho khoa mình; quản lý BHYT xem toàn đợt.
 * Mọi truy vấn danh sách phải đi qua `scopeVisibleTo` để không lộ hồ sơ ngoài phạm vi.
 */
class IssueCasePolicy
{
    public function view(User $user, IssueCase $case): bool
    {
        if ($user->isManager()) {
            return true;
        }

        if ($case->assignee_id === $user->id) {
            return true;
        }

        return $user->department_id !== null && $case->department_id === $user->department_id;
    }

    /** Viết giải trình: người được giao, hoặc trưởng khoa của khoa đang giữ vụ việc. */
    public function explain(User $user, IssueCase $case): bool
    {
        if ($user->isViewer() || $case->batch?->isClosed()) {
            return false;
        }

        if (! $case->requires_explanation) {
            return false;
        }

        if ($case->assignee_id === $user->id) {
            return true;
        }

        if ($user->role === User::ROLE_DEPT_HEAD && $case->department_id === $user->department_id) {
            return true;
        }

        return $user->isManager();
    }

    /** Duyệt: trưởng khoa của khoa đó hoặc quản lý BHYT. Người viết không tự duyệt bản của mình. */
    public function approve(User $user, IssueCase $case): bool
    {
        if (! $user->canApprove() || $case->batch?->isClosed()) {
            return false;
        }

        if ($user->isManager()) {
            return true;
        }

        return $case->department_id !== null && $case->department_id === $user->department_id;
    }

    public function reassign(User $user, IssueCase $case): bool
    {
        if ($case->batch?->isClosed()) {
            return false;
        }

        if ($user->isManager()) {
            return true;
        }

        // Trưởng khoa phân tiếp trong khoa mình, ví dụ hàng chờ ngoại trú K01.
        return $user->role === User::ROLE_DEPT_HEAD && $case->department_id === $user->department_id;
    }
}
