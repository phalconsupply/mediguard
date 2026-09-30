@extends('layouts.app')
@section('title', 'Đợt dữ liệu')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold">Đợt dữ liệu</h1>
        <p class="text-sm text-slate-600">Mỗi kỳ là một đợt độc lập. Nạp lại cùng tệp không sinh việc trùng.</p>
    </div>
    <a href="{{ route('batches.create') }}" class="rounded bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">
        Tạo đợt mới
    </a>
</div>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-2">Mã</th>
                <th class="px-4 py-2">Tên đợt</th>
                <th class="px-4 py-2">Loại</th>
                <th class="px-4 py-2">Kỳ</th>
                <th class="px-4 py-2 text-right">Tệp</th>
                <th class="px-4 py-2 text-right">Vụ việc</th>
                <th class="px-4 py-2">Trạng thái</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($batches as $batch)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2 font-mono text-xs">{{ $batch->code }}</td>
                    <td class="px-4 py-2">
                        <a href="{{ route('batches.show', $batch) }}" class="font-medium text-teal-700 hover:underline">
                            {{ $batch->title }}
                        </a>
                    </td>
                    <td class="px-4 py-2">
                        {{ $batch->kind === 'response' ? 'Phản hồi BHYT' : 'Tự kiểm tra' }}
                    </td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $batch->period ?? '—' }}</td>
                    <td class="px-4 py-2 text-right">{{ $batch->files_count }}</td>
                    <td class="px-4 py-2 text-right">{{ number_format($batch->issue_cases_count, 0, ',', '.') }}</td>
                    <td class="px-4 py-2">
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs">{{ $batch->status }}</span>
                        @if ($batch->isClosed())
                            <span class="ml-1 rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-900">đã khóa kỳ</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">Chưa có đợt nào.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $batches->links() }}</div>
@endsection
