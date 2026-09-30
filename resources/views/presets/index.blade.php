@extends('layouts.app')
@section('title', 'Chọn cột')

@section('content')
<h1 class="mb-1 text-xl font-semibold">Cấu hình cột hiển thị</h1>
<p class="mb-6 text-sm text-slate-600">
    Tick bật/tắt cột chỉ đổi <strong>giao diện</strong>. Không xóa dữ liệu và không đổi cấu trúc
    tệp XLSX xuất trả: cột ẩn vẫn nằm đủ trong tệp gửi lại BHYT.
</p>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-4">
        <form method="GET" class="flex flex-wrap items-end gap-2 rounded-lg border border-slate-200 bg-white p-4">
            <div class="grow">
                <label for="sheet" class="mb-1 block text-xs font-medium text-slate-600">
                    Lấy danh sách cột từ tệp đã nạp
                </label>
                <select id="sheet" name="sheet" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                    <option value="">Chọn tệp…</option>
                    @foreach ($sheets as $option)
                        <option value="{{ $option->id }}" @selected($sheet?->id === $option->id)>
                            {{ $option->file->original_name }} ({{ $option->column_count }} cột)
                        </option>
                    @endforeach
                </select>
            </div>
            <button class="rounded bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-800">Tải cột</button>
        </form>

        @if ($sheet)
            <form method="POST" action="{{ route('presets.store') }}" class="rounded-lg border border-slate-200 bg-white">
                @csrf
                <div class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2">
                    <div>
                        <label for="name" class="mb-1 block text-xs font-medium text-slate-600">Tên cấu hình</label>
                        <input id="name" name="name" required class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label for="scope_type" class="mb-1 block text-xs font-medium text-slate-600">Phạm vi</label>
                        <select id="scope_type" name="scope_type" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                            <option value="global">Toàn viện</option>
                            <option value="error_code">Theo mã lỗi</option>
                            <option value="error_group">Theo nhóm lỗi</option>
                            <option value="department">Theo khoa</option>
                            <option value="batch">Theo đợt</option>
                        </select>
                    </div>
                    <div>
                        <label for="scope_value" class="mb-1 block text-xs font-medium text-slate-600">
                            Giá trị áp dụng (mã lỗi / nhóm / id khoa / id đợt)
                        </label>
                        <input id="scope_value" name="scope_value" list="scope-values"
                               class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                        <datalist id="scope-values">
                            @foreach ($catalogs as $catalog)<option value="{{ $catalog->code }}"></option>@endforeach
                            @foreach ($departments as $department)<option value="{{ $department->id }}">{{ $department->displayName() }}</option>@endforeach
                        </datalist>
                    </div>
                    <div>
                        <label for="role" class="mb-1 block text-xs font-medium text-slate-600">Vai trò người xem</label>
                        <select id="role" name="role" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                            <option value="">Mọi vai trò</option>
                            @foreach (['bhyt_manager' => 'Quản lý BHYT', 'dept_head' => 'Trưởng khoa', 'staff' => 'Người giải trình', 'viewer' => 'Chỉ xem'] as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-sm sm:col-span-2">
                        <input type="checkbox" name="is_default" value="1" class="rounded border-slate-300">
                        Đặt làm mặc định cho phạm vi này
                    </label>
                </div>

                <div class="max-h-[30rem] overflow-auto p-4">
                    <div class="mb-2 text-xs text-slate-500">
                        Tick các cột muốn hiện. Tiêu đề trùng được ghi kèm vị trí gốc để phân biệt.
                    </div>
                    <div class="grid gap-1 sm:grid-cols-2">
                        @foreach ($sheet->columns as $column)
                            <label class="flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-slate-50">
                                <input type="checkbox" name="columns[]" value="{{ $column->internal_code }}"
                                       @checked(in_array($column->normalized_header, config('mediguard.default_columns'), true))
                                       class="rounded border-slate-300">
                                <span class="font-mono text-xs">{{ $column->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-slate-200 p-4">
                    <button class="rounded bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">
                        Lưu cấu hình
                    </button>
                </div>
            </form>
        @endif
    </div>

    <section class="rounded-lg border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold">Cấu hình đã lưu</h2>
        <div class="divide-y divide-slate-100">
            @forelse ($presets as $preset)
                <div class="p-4 text-sm">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-medium">{{ $preset->name }}</div>
                            <div class="text-xs text-slate-500">
                                {{ $preset->scope_type }}{{ $preset->scope_value ? ' = '.$preset->scope_value : '' }}
                                @if ($preset->role) · {{ $preset->role }} @endif
                                @if ($preset->is_default)
                                    <span class="ml-1 rounded bg-teal-50 px-1.5 py-0.5 text-teal-800">mặc định</span>
                                @endif
                            </div>
                            <div class="mt-1 text-xs text-slate-500">{{ count($preset->columns) }} cột</div>
                        </div>
                        <form method="POST" action="{{ route('presets.destroy', $preset) }}">
                            @csrf @method('DELETE')
                            <button class="text-xs text-rose-700 hover:underline">Xóa</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="px-4 py-6 text-center text-sm text-slate-500">
                    Chưa có cấu hình. Hệ thống dùng danh sách cột mặc định trong cấu hình ứng dụng.
                </p>
            @endforelse
        </div>
    </section>
</div>

<div class="mt-4">{{ $presets->links() }}</div>
@endsection
