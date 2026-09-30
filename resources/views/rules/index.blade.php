@extends('layouts.app')
@section('title', 'Quy tắc tự kiểm tra')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold">Quy tắc tự kiểm tra</h1>
        <p class="text-sm text-slate-600">
            Chỉ phiên bản ở trạng thái <strong>Đang áp dụng</strong> mới chạy trên dữ liệu thật.
        </p>
    </div>
    @if (auth()->user()->isManager())
        <a href="{{ route('rules.create') }}" class="rounded bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">
            Thêm quy tắc
        </a>
    @endif
</div>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-2">Mã</th>
                <th class="px-4 py-2">Tên</th>
                <th class="px-4 py-2">Mã lỗi liên quan</th>
                <th class="px-4 py-2">Phạm vi</th>
                <th class="px-4 py-2">Phiên bản đang áp dụng</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($rules as $rule)
                @php $active = $rule->versions->firstWhere('status', 'active'); @endphp
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2 font-mono">
                        <a href="{{ route('rules.show', $rule) }}" class="text-teal-700 hover:underline">{{ $rule->code }}</a>
                    </td>
                    <td class="px-4 py-2">{{ $rule->name }}</td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $rule->catalog?->code ?? '—' }}</td>
                    <td class="px-4 py-2">{{ ['all' => 'Toàn bộ', 'noi_tru' => 'Nội trú', 'ngoai_tru' => 'Ngoại trú'][$rule->scope] }}</td>
                    <td class="px-4 py-2">
                        @if ($active)
                            <span class="rounded bg-teal-50 px-2 py-0.5 text-xs text-teal-800">v{{ $active->version }}</span>
                        @else
                            <span class="text-xs text-slate-500">chưa có ({{ $rule->versions->count() }} phiên bản khác)</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Chưa có quy tắc nào.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $rules->links() }}</div>
@endsection
