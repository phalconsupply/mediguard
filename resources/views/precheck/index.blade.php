@extends('layouts.app')
@section('title', 'Tự kiểm tra')

@section('content')
<h1 class="mb-1 text-xl font-semibold">Các lần tự kiểm tra</h1>
<p class="mb-6 text-sm text-slate-600">
    Mỗi lần chạy chụp lại phiên bản quy tắc và hash tệp đầu vào, nên kết quả đọc lại được
    nguyên trạng dù thư viện đổi sau đó.
</p>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-2">Lần chạy</th>
                <th class="px-4 py-2">Đợt</th>
                <th class="px-4 py-2 text-right">Hồ sơ</th>
                <th class="px-4 py-2 text-right">Dòng chi tiết</th>
                <th class="px-4 py-2 text-right">Phát hiện</th>
                <th class="px-4 py-2 text-right">Không đủ dữ liệu</th>
                <th class="px-4 py-2">Trạng thái</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($runs as $run)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2">
                        <a href="{{ route('precheck.show', $run) }}" class="text-teal-700 hover:underline">#{{ $run->id }}</a>
                        <div class="text-xs text-slate-500">{{ $run->created_at->format('d/m/Y H:i') }}</div>
                    </td>
                    <td class="px-4 py-2">{{ $run->batch->title }}</td>
                    <td class="px-4 py-2 text-right">{{ number_format($run->encounters_total, 0, ',', '.') }}</td>
                    <td class="px-4 py-2 text-right">{{ number_format($run->details_total, 0, ',', '.') }}</td>
                    <td class="px-4 py-2 text-right font-medium text-rose-700">{{ number_format($run->detected_count, 0, ',', '.') }}</td>
                    <td class="px-4 py-2 text-right text-amber-700">{{ number_format($run->insufficient_count, 0, ',', '.') }}</td>
                    <td class="px-4 py-2">
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs">{{ $run->status }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">Chưa có lần chạy nào.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $runs->links() }}</div>
@endsection
