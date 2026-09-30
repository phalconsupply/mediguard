<?php

namespace App\Http\Controllers;

use App\Jobs\RunPrecheck;
use App\Models\CheckRuleVersion;
use App\Models\Finding;
use App\Models\ImportBatch;
use App\Models\PrecheckRun;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tự kiểm tra trước khi gửi giám định.
 *
 * Người quản lý chọn bộ quy tắc đã duyệt theo kỳ áp dụng; hệ thống chụp lại
 * phiên bản từng quy tắc, hash tệp đầu vào và cấu hình ánh xạ rồi chạy nền.
 */
class PrecheckController extends Controller
{
    public function index(): View
    {
        $runs = PrecheckRun::with('batch')->latest('id')->paginate(20);

        return view('precheck.index', compact('runs'));
    }

    public function create(ImportBatch $batch): View
    {
        abort_unless($batch->kind === ImportBatch::KIND_PRECHECK, 404);

        $versions = CheckRuleVersion::with('rule')
            ->runnable()
            ->effectiveOn($batch->effectiveDate())
            ->get();

        return view('precheck.create', compact('batch', 'versions'));
    }

    public function store(Request $request, ImportBatch $batch): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);
        abort_unless($batch->kind === ImportBatch::KIND_PRECHECK, 404);

        $data = $request->validate([
            'rule_version_ids' => ['required', 'array', 'min:1'],
            'rule_version_ids.*' => ['exists:check_rule_versions,id'],
        ]);

        $versions = CheckRuleVersion::with('rule')
            ->runnable()
            ->whereIn('id', $data['rule_version_ids'])
            ->get();

        if ($versions->isEmpty()) {
            return back()->withErrors([
                'rule_version_ids' => 'Không quy tắc nào trong danh sách đang ở trạng thái áp dụng.',
            ]);
        }

        $run = PrecheckRun::create([
            'import_batch_id' => $batch->id,
            'status' => 'queued',
            'ruleset_snapshot' => $versions->map(fn (CheckRuleVersion $v) => [
                'rule_code' => $v->rule->code,
                'rule_name' => $v->rule->name,
                'rule_version_id' => $v->id,
                'version' => $v->version,
                'severity' => $v->severity,
            ])->all(),
            'input_snapshot' => $batch->files()->get()->map(fn ($f) => [
                'file' => $f->original_name,
                'sha256' => $f->sha256,
                'format' => $f->format,
                'mapping_profile' => $f->detected['mapping_profile'] ?? null,
            ])->all(),
            'started_by' => $request->user()->id,
        ]);

        RunPrecheck::dispatch($run->id, $request->user()->id);
        Audit::record($request->user()->id, 'precheck.run_queued', $run, ['rules' => $versions->count()]);

        return redirect()->route('precheck.show', $run)->with('status', 'Đã đưa lần chạy vào hàng đợi.');
    }

    public function show(Request $request, PrecheckRun $run): View
    {
        $run->load('batch', 'startedBy');

        $findings = $run->findings()
            ->with('ruleVersion.rule', 'detail', 'assignee')
            ->when($request->filled('result'), fn ($q) => $q->where('result', $request->input('result')))
            ->when($request->filled('rule'), fn ($q) => $q->where('rule_code', $request->input('rule')))
            ->when(! $request->user()->isManager(), fn ($q) => $q->where(function ($s) use ($request) {
                $s->where('assignee_id', $request->user()->id)
                    ->orWhere('department_id', $request->user()->department_id);
            }))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('precheck.show', [
            'run' => $run,
            'findings' => $findings,
            'resultLabels' => Finding::RESULT_LABELS,
        ]);
    }

    /** Ghi nhận cách xử lý một phát hiện: đã sửa dữ liệu nguồn hay giữ nguyên có lý do. */
    public function handle(Request $request, Finding $finding): RedirectResponse
    {
        $data = $request->validate([
            'handling_status' => ['required', 'in:open,fixed,accepted_risk,not_applicable'],
            'handling_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['handling_status'] === 'accepted_risk' && blank($data['handling_note'] ?? null)) {
            return back()->withErrors(['handling_note' => 'Giữ nguyên dữ liệu phải ghi lý do.']);
        }

        $finding->update($data);
        Audit::record($request->user()->id, 'precheck.finding_handled', $finding, $data);

        return back()->with('status', 'Đã ghi nhận cách xử lý phát hiện.');
    }
}
