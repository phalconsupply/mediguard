<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Models\IssueCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Bảng tiến độ.
 *
 * Số liệu lấy bằng truy vấn tổng hợp theo phạm vi của người dùng, không tải
 * danh sách dòng về rồi cộng ở PHP.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $batches = ImportBatch::query()
            ->withCount('files')
            ->latest('id')
            ->limit(10)
            ->get();

        $selected = $request->integer('batch') ?: $batches->firstWhere('kind', ImportBatch::KIND_RESPONSE)?->id;

        $base = IssueCase::query()->visibleTo($user);
        if ($selected) {
            $base->where('import_batch_id', $selected);
        }

        $byStatus = (clone $base)
            ->select('status', DB::raw('COUNT(*) as total'), DB::raw('SUM(amount_reference) as amount'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $byErrorCode = (clone $base)
            ->select('error_code', DB::raw('COUNT(*) as total'))
            ->selectRaw('SUM(CASE WHEN requires_explanation = 1 THEN 1 ELSE 0 END) as needs')
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as settled', [
                IssueCase::STATUS_APPROVED,
                IssueCase::STATUS_EXPORTED,
            ])
            ->groupBy('error_code')
            ->orderByDesc('total')
            ->limit(30)
            ->get();

        $byDepartment = (clone $base)
            ->leftJoin('departments as d', 'd.id', '=', 'issue_cases.department_id')
            ->select('d.code', 'd.name', DB::raw('COUNT(*) as total'))
            ->selectRaw('SUM(CASE WHEN issue_cases.status IN (?, ?) THEN 1 ELSE 0 END) as settled', [
                IssueCase::STATUS_APPROVED,
                IssueCase::STATUS_EXPORTED,
            ])
            ->groupBy('d.code', 'd.name')
            ->orderByDesc('total')
            ->limit(30)
            ->get();

        return view('dashboard', [
            'batches' => $batches,
            'selectedBatch' => $selected,
            'byStatus' => $byStatus,
            'byErrorCode' => $byErrorCode,
            'byDepartment' => $byDepartment,
            // Tiền hiển thị luôn nói rõ đang dùng cột nào, vì THANH_TIEN_BH,
            // T_BHTT_DTL và TIEN_DE_NGHI_KIEM_TRA không đồng nhất.
            'amountColumn' => 'TIEN_DE_NGHI_KIEM_TRA',
        ]);
    }
}
