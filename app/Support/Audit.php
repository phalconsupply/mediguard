<?php

namespace App\Support;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * Nhật ký truy cập và thay đổi.
 *
 * Ghi lại việc nạp tệp, đổi phân công, duyệt giải trình, tải tệp và xuất kết quả.
 * Chỉ ghi thêm; không bao giờ sửa hoặc xóa bản ghi cũ.
 */
final class Audit
{
    /** @param array<string, mixed> $meta */
    public static function record(?int $userId, string $action, ?Model $subject = null, array $meta = []): void
    {
        AuditEvent::create([
            'user_id' => $userId,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta === [] ? null : $meta,
            'ip' => Request::hasSession() || app()->runningInConsole() ? Request::ip() : null,
            'created_at' => now(),
        ]);
    }
}
