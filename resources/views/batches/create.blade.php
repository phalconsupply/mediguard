@extends('layouts.app')
@section('title', 'Tạo đợt')

@section('content')
<div class="mx-auto max-w-xl">
    <h1 class="mb-1 text-xl font-semibold">Tạo đợt mới</h1>
    <p class="mb-6 text-sm text-slate-600">
        Chọn <strong>Phản hồi BHYT</strong> để nạp các tệp <span class="font-mono">TACH_…</span> nhận về,
        hoặc <strong>Tự kiểm tra</strong> để nạp dữ liệu HIS chuẩn bị gửi giám định.
    </p>

    <form method="POST" action="{{ route('batches.store') }}" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6">
        @csrf

        <div>
            <label for="title" class="mb-1 block text-sm font-medium">Tên đợt</label>
            <input id="title" name="title" value="{{ old('title') }}" required
                   placeholder="Phản hồi giám định kỳ 202607"
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label for="kind" class="mb-1 block text-sm font-medium">Loại đợt</label>
            <select id="kind" name="kind" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="response" @selected(old('kind') === 'response')>Phản hồi BHYT (XLSX/CSV)</option>
                <option value="precheck" @selected(old('kind') === 'precheck')>Tự kiểm tra trước khi gửi (XML/XLSX/CSV)</option>
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="period" class="mb-1 block text-sm font-medium">Kỳ quyết toán</label>
                <input id="period" name="period" value="{{ old('period') }}" placeholder="202607" inputmode="numeric"
                       class="w-full rounded border border-slate-300 px-3 py-2 font-mono text-sm">
                <p class="mt-1 text-xs text-slate-500">Dạng YYYYMM. Dùng để tra danh mục đúng theo kỳ.</p>
            </div>
            <div>
                <label for="facility_code" class="mb-1 block text-sm font-medium">Mã cơ sở KCB</label>
                <input id="facility_code" name="facility_code" value="{{ old('facility_code') }}" placeholder="68001"
                       class="w-full rounded border border-slate-300 px-3 py-2 font-mono text-sm">
            </div>
        </div>

        <div class="flex gap-2">
            <button class="rounded bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Tạo đợt</button>
            <a href="{{ route('batches.index') }}" class="rounded border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100">Hủy</a>
        </div>
    </form>
</div>
@endsection
