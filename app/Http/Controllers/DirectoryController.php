<?php

namespace App\Http\Controllers;

use App\Models\AssignmentRule;
use App\Models\Department;
use App\Models\ServiceOwner;
use App\Models\StaffDirectory;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Danh mục quản trị: khoa/phòng, nhân sự, đơn vị thực hiện dịch vụ và
 * quy tắc phân công theo mã lỗi.
 *
 * Đây là điều kiện để giao việc đến đúng cá nhân; thiếu danh mục thì vụ việc
 * rơi vào hàng chờ của đầu mối chứ không bị gán nhầm người.
 */
class DirectoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isManager(), 403);

        return view('directory.index', [
            'departments' => Department::withCount('users')->orderBy('code')->get(),
            'staff' => StaffDirectory::with('user:id,name', 'department:id,code,name')
                ->orderBy('staff_code')
                ->paginate(50, ['*'], 'staff_page'),
            'serviceOwners' => ServiceOwner::with('department:id,code,name')
                ->orderBy('service_code')
                ->paginate(50, ['*'], 'service_page'),
            'assignmentRules' => AssignmentRule::with('fallbackDepartment:id,code,name')
                ->orderBy('error_code')
                ->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email', 'role', 'staff_code', 'department_id']),
        ]);
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:255'],
            'kind' => ['required', 'in:clinical,paraclinical,pharmacy,admin,outpatient'],
            'is_outpatient_pool' => ['nullable', 'boolean'],
            'head_user_id' => ['nullable', 'exists:users,id'],
            'effective_from' => ['nullable', 'date'],
        ]);

        $department = Department::updateOrCreate(
            ['code' => $data['code'], 'effective_from' => $data['effective_from'] ?? null],
            $data + ['is_outpatient_pool' => $request->boolean('is_outpatient_pool'), 'is_active' => true]
        );

        Audit::record($request->user()->id, 'directory.department_saved', $department, $data);

        return back()->with('status', 'Đã lưu khoa/phòng.');
    }

    public function storeStaff(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);

        $data = $request->validate([
            'staff_code' => ['required', 'string', 'max:64'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'exists:users,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'can_order' => ['nullable', 'boolean'],
            'can_perform' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $staff = StaffDirectory::updateOrCreate(
            ['staff_code' => $data['staff_code'], 'effective_from' => $data['effective_from'] ?? null],
            $data + [
                'can_order' => $request->boolean('can_order', true),
                'can_perform' => $request->boolean('can_perform', true),
            ]
        );

        Audit::record($request->user()->id, 'directory.staff_saved', $staff, $data);

        return back()->with('status', 'Đã lưu ánh xạ mã nhân sự.');
    }

    public function storeServiceOwner(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);

        $data = $request->validate([
            'service_code' => ['required', 'string', 'max:64'],
            'service_name' => ['nullable', 'string', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'effective_from' => ['nullable', 'date'],
        ]);

        $owner = ServiceOwner::updateOrCreate(
            ['service_code' => $data['service_code'], 'effective_from' => $data['effective_from'] ?? null],
            $data
        );

        Audit::record($request->user()->id, 'directory.service_owner_saved', $owner, $data);

        return back()->with('status', 'Đã lưu đơn vị thực hiện dịch vụ.');
    }

    public function storeAssignmentRule(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);

        $data = $request->validate([
            'error_code' => ['required', 'string', 'max:32'],
            'resolver_chain' => ['required', 'array', 'min:1'],
            'resolver_chain.*' => ['in:ma_bsi_thuc_hien,ma_bac_si,service_owner,department,outpatient_pool'],
            'fallback_department_id' => ['nullable', 'exists:departments,id'],
            'outpatient_mode' => ['required', 'in:per_person,pool'],
            'due_days' => ['required', 'integer', 'between:1,90'],
            'effective_from' => ['nullable', 'date'],
        ]);

        $rule = AssignmentRule::updateOrCreate(
            ['error_code' => $data['error_code'], 'effective_from' => $data['effective_from'] ?? null],
            $data
        );

        Audit::record($request->user()->id, 'directory.assignment_rule_saved', $rule, $data);

        return back()->with('status', 'Đã lưu quy tắc phân công. Quy tắc áp dụng cho các lần nạp sau.');
    }
}
