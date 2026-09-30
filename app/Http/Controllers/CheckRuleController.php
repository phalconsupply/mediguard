<?php

namespace App\Http\Controllers;

use App\Models\CheckRule;
use App\Models\CheckRuleVersion;
use App\Models\ErrorCatalog;
use App\Services\Rules\RuleDefinition;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Quản lý quy tắc tự kiểm tra.
 *
 * Quy tắc chỉ chạy trên dữ liệu thật khi ở trạng thái "Đang áp dụng".
 * Mọi sửa đổi tạo phiên bản mới; phiên bản đã dùng để chạy không bị ghi đè.
 */
class CheckRuleController extends Controller
{
    public function index(): View
    {
        $rules = CheckRule::with(['versions', 'catalog'])->orderBy('code')->paginate(30);

        return view('rules.index', compact('rules'));
    }

    public function show(CheckRule $rule): View
    {
        $rule->load(['versions.author', 'versions.approver', 'catalog']);

        return view('rules.show', compact('rule'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:check_rules,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'error_catalog_id' => ['nullable', 'exists:error_catalogs,id'],
            'scope' => ['required', 'in:all,noi_tru,ngoai_tru'],
        ]);

        $rule = CheckRule::create($data);
        Audit::record($request->user()->id, 'rule.created', $rule, $data);

        return redirect()->route('rules.show', $rule)->with('status', 'Đã tạo quy tắc. Hãy thêm phiên bản đầu tiên.');
    }

    /** Tạo phiên bản mới. Phiên bản luôn bắt đầu ở trạng thái nháp. */
    public function storeVersion(Request $request, CheckRule $rule): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);

        $data = $request->validate([
            'definition' => ['required', 'string'],
            'required_fields' => ['nullable', 'string'],
            'severity' => ['required', 'in:info,warning,blocker'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $definition = json_decode($data['definition'], true);
        if (! is_array($definition)) {
            throw ValidationException::withMessages(['definition' => 'Định nghĩa phải là JSON hợp lệ.']);
        }

        try {
            RuleDefinition::validate($definition);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['definition' => $e->getMessage()]);
        }

        $required = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($data['required_fields'] ?? ''))
        )));

        $version = CheckRuleVersion::create([
            'check_rule_id' => $rule->id,
            'version' => ((int) $rule->versions()->max('version')) + 1,
            'definition' => $definition,
            'required_fields' => $required !== [] ? $required : RuleDefinition::referencedFields($definition),
            'severity' => $data['severity'],
            'status' => CheckRuleVersion::STATUS_DRAFT,
            'effective_from' => $data['effective_from'] ?? null,
            'effective_to' => $data['effective_to'] ?? null,
            'author_id' => $request->user()->id,
        ]);

        Audit::record($request->user()->id, 'rule.version_created', $version, ['rule' => $rule->code]);

        return back()->with('status', "Đã tạo phiên bản {$version->version} ở trạng thái nháp.");
    }

    /**
     * Chuyển trạng thái vòng đời. Chỉ quản lý được duyệt, và một phiên bản
     * phải đi qua thử nghiệm trước khi áp dụng trên dữ liệu thật.
     */
    public function transition(Request $request, CheckRuleVersion $version): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(CheckRuleVersion::STATUS_LABELS))],
        ]);

        $allowed = [
            CheckRuleVersion::STATUS_DRAFT => [CheckRuleVersion::STATUS_ANALYSING, CheckRuleVersion::STATUS_RETIRED],
            CheckRuleVersion::STATUS_ANALYSING => [CheckRuleVersion::STATUS_TESTING, CheckRuleVersion::STATUS_RETIRED],
            CheckRuleVersion::STATUS_TESTING => [CheckRuleVersion::STATUS_APPROVED, CheckRuleVersion::STATUS_ANALYSING, CheckRuleVersion::STATUS_RETIRED],
            CheckRuleVersion::STATUS_APPROVED => [CheckRuleVersion::STATUS_ACTIVE, CheckRuleVersion::STATUS_TESTING],
            CheckRuleVersion::STATUS_ACTIVE => [CheckRuleVersion::STATUS_PAUSED, CheckRuleVersion::STATUS_RETIRED],
            CheckRuleVersion::STATUS_PAUSED => [CheckRuleVersion::STATUS_ACTIVE, CheckRuleVersion::STATUS_RETIRED],
            CheckRuleVersion::STATUS_RETIRED => [],
        ];

        if (! in_array($data['status'], $allowed[$version->status] ?? [], true)) {
            return back()->withErrors([
                'status' => "Không chuyển được từ \"{$version->statusLabel()}\" sang trạng thái đã chọn.",
            ]);
        }

        // Duyệt phải có bộ ca kiểm thử, gồm cả ca thiếu dữ liệu.
        if ($data['status'] === CheckRuleVersion::STATUS_APPROVED && empty($version->test_cases)) {
            return back()->withErrors([
                'status' => 'Chưa có bộ ca kiểm thử (ca đúng, ca cảnh báo, ca thiếu dữ liệu) nên chưa duyệt được.',
            ]);
        }

        $version->fill([
            'status' => $data['status'],
            'approver_id' => $data['status'] === CheckRuleVersion::STATUS_APPROVED ? $request->user()->id : $version->approver_id,
            'approved_at' => $data['status'] === CheckRuleVersion::STATUS_APPROVED ? now() : $version->approved_at,
        ])->save();

        Audit::record($request->user()->id, 'rule.version_transitioned', $version, $data);

        return back()->with('status', 'Đã chuyển trạng thái phiên bản quy tắc.');
    }

    public function create(): View
    {
        return view('rules.create', ['catalogs' => ErrorCatalog::orderBy('code')->get()]);
    }
}
