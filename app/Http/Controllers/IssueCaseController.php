<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ErrorCatalog;
use App\Models\ImportBatch;
use App\Models\IssueCase;
use App\Models\User;
use App\Services\Assignment\IssueCaseBuilder;
use App\Services\Presentation\ColumnPresetResolver;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Danh sách và chi tiết công việc giải trình.
 *
 * Lọc và phân trang chạy ở DB; chỉ lấy về các dòng của trang hiện tại.
 * Cột hiển thị do cấu hình quyết định, nhưng chi tiết một dòng vẫn xem được
 * toàn bộ dữ liệu nguồn.
 */
class IssueCaseController extends Controller
{
    public function __construct(private readonly ColumnPresetResolver $presets) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $query = IssueCase::query()
            ->visibleTo($user)
            ->with(['sourceRow:id,source_sheet_id,row_number,cells', 'assignee:id,name', 'department:id,code,name', 'sourceFile:id,original_name'])
            ->latest('id');

        foreach ([
            'batch' => 'import_batch_id',
            'error_code' => 'error_code',
            'status' => 'status',
            'assignee' => 'assignee_id',
            'department' => 'department_id',
        ] as $param => $column) {
            if ($request->filled($param)) {
                $query->where($column, $request->input($param));
            }
        }

        if ($request->boolean('needs_explanation')) {
            $query->where('requires_explanation', true);
        }

        if ($request->filled('q')) {
            $term = trim($request->input('q'));
            $query->whereHas('sourceRow', function ($q) use ($term) {
                $q->where('id_cp', $term)->orWhere('ma_lk', $term);
            });
        }

        $cases = $query->paginate(config('mediguard.workflow.page_size'))->withQueryString();

        // Cấu hình cột lấy theo tệp của dòng đầu tiên: mỗi tệp có bộ cột riêng.
        $sheet = $cases->first()?->sourceRow?->sheet;
        $layout = $sheet
            ? $this->presets->resolve(
                $sheet,
                ErrorCatalog::where('code', $cases->first()->error_code)->first(),
                $user->department_id,
                $request->integer('batch') ?: null,
                $request->has('columns') ? array_filter((array) $request->input('columns')) : null,
            )
            : ['columns' => [], 'preset' => null, 'missing' => [], 'new_columns' => []];

        return view('cases.index', [
            'cases' => $cases,
            'layout' => $layout,
            'batches' => ImportBatch::where('kind', ImportBatch::KIND_RESPONSE)->latest('id')->get(),
            'errorCodes' => ErrorCatalog::orderBy('code')->get(),
            'statuses' => IssueCase::STATUS_LABELS,
            'departments' => Department::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function show(Request $request, IssueCase $case): View
    {
        $this->authorize('view', $case);

        $case->load([
            'sourceRow.sheet.columns', 'sourceFile', 'batch', 'catalog',
            'assignee', 'department', 'explanations.author', 'explanations.approver',
            'explanations.evidences', 'assignments.assignee', 'assignments.assignedBy',
        ]);

        Audit::record($request->user()->id, 'case.viewed', $case);

        return view('cases.show', [
            'case' => $case,
            'columns' => $case->sourceRow->sheet->columns,
            'cells' => $case->sourceRow->cells ?? [],
            'candidates' => $this->assigneeCandidates($case),
            'departments' => Department::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function reassign(Request $request, IssueCase $case, IssueCaseBuilder $builder): RedirectResponse
    {
        $this->authorize('reassign', $case);

        $data = $request->validate([
            'assignee_id' => ['nullable', 'exists:users,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $builder->reassign(
            $case,
            $data['assignee_id'] ?? null,
            $data['department_id'] ?? null,
            $data['reason'],
            $request->user()->id,
        );

        Audit::record($request->user()->id, 'case.reassigned', $case, $data);

        return back()->with('status', 'Đã chuyển người phụ trách và lưu lý do.');
    }

    /**
     * Người có thể nhận việc: nhân sự trong khoa đang giữ vụ việc, cộng bản thân
     * người thao tác. Quản lý chọn được trong toàn viện.
     */
    private function assigneeCandidates(IssueCase $case): Collection
    {
        $query = User::query()->where('is_active', true)->orderBy('name');

        if ($case->department_id !== null) {
            $query->where('department_id', $case->department_id);
        }

        return $query->limit(200)->get(['id', 'name', 'staff_code', 'department_id']);
    }
}
