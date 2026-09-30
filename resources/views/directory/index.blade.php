@extends('layouts.app')
@section('title', 'Danh mục quản trị')

@section('content')
<h1 class="mb-1 text-xl font-semibold">Danh mục quản trị</h1>
<p class="mb-6 text-sm text-slate-600">
    Thiếu ánh xạ mã nhân sự thì vụ việc rơi vào hàng chờ của đầu mối, không bị gán nhầm người.
    Mọi ánh xạ có ngày hiệu lực để tra đúng theo kỳ.
</p>

<div class="grid gap-6 lg:grid-cols-2">
    <section class="rounded-lg border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold">Khoa / phòng</h2>

        <form method="POST" action="{{ route('directory.departments.store') }}" class="grid gap-2 border-b border-slate-100 p-4 sm:grid-cols-2">
            @csrf
            <input name="code" placeholder="Mã khoa (K01)" required class="rounded border border-slate-300 px-2 py-1.5 text-sm">
            <input name="name" placeholder="Tên khoa" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
            <select name="kind" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
                @foreach (['clinical' => 'Lâm sàng', 'paraclinical' => 'Cận lâm sàng', 'pharmacy' => 'Dược', 'admin' => 'Hành chính', 'outpatient' => 'Ngoại trú'] as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select name="head_user_id" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
                <option value="">Chưa có trưởng khoa</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-xs text-slate-600 sm:col-span-2">
                <input type="checkbox" name="is_outpatient_pool" value="1" class="rounded border-slate-300">
                Là nhóm ngoại trú chung (hàng chờ cho dòng K01 chưa xác định được người)
            </label>
            <button class="rounded bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-800 sm:col-span-2">Lưu khoa</button>
        </form>

        <div class="max-h-80 overflow-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($departments as $department)
                        <tr>
                            <td class="px-4 py-1.5 font-mono">{{ $department->code }}</td>
                            <td class="px-4 py-1.5">{{ $department->name ?? '—' }}</td>
                            <td class="px-4 py-1.5 text-xs text-slate-500">
                                {{ $department->users_count }} tài khoản
                                @if ($department->is_outpatient_pool)
                                    <span class="ml-1 rounded bg-teal-50 px-1.5 py-0.5 text-teal-800">nhóm ngoại trú</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold">Mã nhân sự &rarr; tài khoản</h2>

        <form method="POST" action="{{ route('directory.staff.store') }}" class="grid gap-2 border-b border-slate-100 p-4 sm:grid-cols-2">
            @csrf
            <input name="staff_code" placeholder="Mã bác sĩ / NVYT" required class="rounded border border-slate-300 px-2 py-1.5 font-mono text-sm">
            <input name="full_name" placeholder="Họ tên" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
            <select name="user_id" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
                <option value="">Chưa nối tài khoản</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                @endforeach
            </select>
            <select name="department_id" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
                <option value="">Chưa gán khoa</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}">{{ $department->displayName() }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-xs text-slate-600">
                <input type="checkbox" name="can_order" value="1" checked class="rounded border-slate-300"> Được chỉ định/kê đơn
            </label>
            <label class="flex items-center gap-2 text-xs text-slate-600">
                <input type="checkbox" name="can_perform" value="1" checked class="rounded border-slate-300"> Được thực hiện dịch vụ
            </label>
            <input name="effective_from" type="date" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
            <button class="rounded bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-800">Lưu ánh xạ</button>
        </form>

        <div class="max-h-80 overflow-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($staff as $row)
                        <tr>
                            <td class="px-4 py-1.5 font-mono">{{ $row->staff_code }}</td>
                            <td class="px-4 py-1.5">{{ $row->user?->name ?? $row->full_name ?? '—' }}</td>
                            <td class="px-4 py-1.5 text-xs text-slate-500">
                                {{ $row->department?->code ?? 'chưa gán khoa' }}
                                @unless ($row->user_id)
                                    <span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-amber-900">chưa có tài khoản</span>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $staff->links() }}</div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold">Mã dịch vụ &rarr; đơn vị thực hiện</h2>

        <form method="POST" action="{{ route('directory.services.store') }}" class="grid gap-2 border-b border-slate-100 p-4 sm:grid-cols-2">
            @csrf
            <input name="service_code" placeholder="Mã dịch vụ (MA_CP)" required class="rounded border border-slate-300 px-2 py-1.5 font-mono text-sm">
            <input name="service_name" placeholder="Tên dịch vụ" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
            <select name="department_id" required class="rounded border border-slate-300 px-2 py-1.5 text-sm">
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}">{{ $department->displayName() }}</option>
                @endforeach
            </select>
            <button class="rounded bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-800">Lưu</button>
        </form>

        <div class="max-h-64 overflow-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($serviceOwners as $owner)
                        <tr>
                            <td class="px-4 py-1.5 font-mono">{{ $owner->service_code }}</td>
                            <td class="px-4 py-1.5">{{ $owner->service_name ?? '—' }}</td>
                            <td class="px-4 py-1.5 text-xs text-slate-500">{{ $owner->department->code }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $serviceOwners->links() }}</div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold">Quy tắc phân công theo mã lỗi</h2>

        <form method="POST" action="{{ route('directory.rules.store') }}" class="grid gap-2 border-b border-slate-100 p-4">
            @csrf
            <input name="error_code" placeholder="Mã lỗi (9065)" required class="rounded border border-slate-300 px-2 py-1.5 font-mono text-sm">

            <fieldset class="rounded border border-slate-200 p-2">
                <legend class="px-1 text-xs text-slate-600">Thứ tự chọn người (tick theo thứ tự ưu tiên)</legend>
                @foreach ([
                    'ma_bsi_thuc_hien' => 'Người thực hiện (MA_BSI_THUC_HIEN)',
                    'ma_bac_si' => 'Người chỉ định (MA_BAC_SI)',
                    'service_owner' => 'Đơn vị thực hiện theo mã dịch vụ',
                    'department' => 'Khoa theo MA_KHOA',
                    'outpatient_pool' => 'Nhóm ngoại trú chung',
                ] as $value => $label)
                    <label class="flex items-center gap-2 py-0.5 text-xs">
                        <input type="checkbox" name="resolver_chain[]" value="{{ $value }}" class="rounded border-slate-300">
                        {{ $label }}
                    </label>
                @endforeach
            </fieldset>

            <div class="grid grid-cols-2 gap-2">
                <select name="outpatient_mode" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
                    <option value="per_person">K01: tách theo người chịu trách nhiệm</option>
                    <option value="pool">K01: gom về một nhóm ngoại trú</option>
                </select>
                <input name="due_days" type="number" min="1" max="90" value="7" placeholder="Số ngày xử lý"
                       class="rounded border border-slate-300 px-2 py-1.5 text-sm">
            </div>

            <select name="fallback_department_id" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
                <option value="">Đầu mối dự phòng: nhóm ngoại trú mặc định</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}">{{ $department->displayName() }}</option>
                @endforeach
            </select>

            <button class="rounded bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-800">Lưu quy tắc</button>
            <p class="text-xs text-slate-500">Quy tắc áp dụng cho các lần nạp sau, không phân công lại đợt đã nạp.</p>
        </form>

        <div class="max-h-64 overflow-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($assignmentRules as $rule)
                        <tr>
                            <td class="px-4 py-1.5 font-mono">{{ $rule->error_code }}</td>
                            <td class="px-4 py-1.5 text-xs">{{ implode(' → ', $rule->resolver_chain) }}</td>
                            <td class="px-4 py-1.5 text-xs text-slate-500">{{ $rule->due_days }} ngày</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
