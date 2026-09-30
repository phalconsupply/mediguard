@extends('layouts.app')
@section('title', 'Thêm quy tắc')

@section('content')
<div class="mx-auto max-w-xl">
    <h1 class="mb-1 text-xl font-semibold">Thêm quy tắc tự kiểm tra</h1>
    <p class="mb-6 text-sm text-slate-600">
        Tạo quy tắc trước, sau đó thêm phiên bản đầu tiên kèm điều kiện và bộ ca kiểm thử.
    </p>

    <form method="POST" action="{{ route('rules.store') }}" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6">
        @csrf

        <div>
            <label for="code" class="mb-1 block text-sm font-medium">Mã quy tắc</label>
            <input id="code" name="code" value="{{ old('code') }}" required placeholder="R-9065-TIME-ZERO"
                   class="w-full rounded border border-slate-300 px-3 py-2 font-mono text-sm">
        </div>

        <div>
            <label for="name" class="mb-1 block text-sm font-medium">Tên</label>
            <input id="name" name="name" value="{{ old('name') }}" required
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label for="error_catalog_id" class="mb-1 block text-sm font-medium">Mã lỗi liên quan</label>
            <select id="error_catalog_id" name="error_catalog_id" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">Chưa gắn</option>
                @foreach ($catalogs as $catalog)
                    <option value="{{ $catalog->id }}" @selected(old('error_catalog_id') == $catalog->id)>
                        {{ $catalog->displayName() }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="scope" class="mb-1 block text-sm font-medium">Phạm vi</label>
            <select id="scope" name="scope" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="all">Toàn bộ</option>
                <option value="noi_tru">Nội trú</option>
                <option value="ngoai_tru">Ngoại trú</option>
            </select>
        </div>

        <div>
            <label for="description" class="mb-1 block text-sm font-medium">Mô tả và căn cứ</label>
            <textarea id="description" name="description" rows="4"
                      class="w-full rounded border border-slate-300 px-3 py-2 text-sm">{{ old('description') }}</textarea>
        </div>

        <button class="rounded bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Tạo</button>
    </form>
</div>
@endsection
