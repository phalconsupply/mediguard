@extends('layouts.app')
@section('title', 'Thư viện lỗi')

@section('content')
<h1 class="mb-1 text-xl font-semibold">Thư viện lỗi</h1>
<p class="mb-4 text-sm text-slate-600">
    Mã lỗi chưa có trong thư viện được tự ghi nhận là <strong>chờ phân loại</strong> và vẫn đi vào
    luồng giải trình ngay trong kỳ.
</p>

<form method="GET" class="mb-4 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" placeholder="Mã hoặc tên lỗi"
           class="rounded border border-slate-300 px-3 py-1.5 text-sm">
    <select name="status" class="rounded border border-slate-300 px-3 py-1.5 text-sm">
        <option value="">Mọi trạng thái</option>
        @foreach (['pending_review' => 'Chờ phân loại', 'classified' => 'Đã phân loại', 'manual_only' => 'Chỉ giải trình thủ công', 'retired' => 'Ngừng dùng'] as $value => $label)
            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <button class="rounded bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-800">Lọc</button>
</form>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-2">Mã</th>
                <th class="px-4 py-2">Tên</th>
                <th class="px-4 py-2">Nhóm</th>
                <th class="px-4 py-2">Ai giải trình</th>
                <th class="px-4 py-2">Kỳ đầu tiên</th>
                <th class="px-4 py-2 text-right">Biến thể</th>
                <th class="px-4 py-2">Trạng thái</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($catalogs as $catalog)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2 font-mono">
                        <a href="{{ route('catalog.show', $catalog) }}" class="text-teal-700 hover:underline">{{ $catalog->code }}</a>
                    </td>
                    <td class="max-w-md px-4 py-2">{{ $catalog->name ?? '—' }}</td>
                    <td class="px-4 py-2">{{ $catalog->group ?? '—' }}</td>
                    <td class="px-4 py-2">
                        {{ ['orderer' => 'Người chỉ định', 'performer' => 'Người thực hiện', 'both' => 'Cả hai', 'unknown' => 'Chưa xác định'][$catalog->responsible_role] }}
                    </td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $catalog->first_seen_period ?? '—' }}</td>
                    <td class="px-4 py-2 text-right">{{ $catalog->variants_count }}</td>
                    <td class="px-4 py-2">
                        <span class="rounded px-2 py-0.5 text-xs {{ $catalog->status === 'pending_review' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100' }}">
                            {{ $catalog->status }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">Chưa có mục nào.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $catalogs->links() }}</div>
@endsection
