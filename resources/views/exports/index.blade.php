@extends('layouts.app')
@section('title', 'Xuất trả')

@section('content')
<h1 class="mb-1 text-xl font-semibold">Xuất trả — {{ $batch->title }}</h1>
<p class="mb-6 text-sm text-slate-600">
    Mỗi tệp nguồn sinh đúng một tệp kết quả, giữ nguyên trang tính, thứ tự dòng, số cột và giá trị
    từng ô; chỉ nối thêm cột cuối <span class="font-mono">{{ config('mediguard.export.column_header') }}</span>.
</p>

<div class="space-y-4">
    @foreach ($files as $file)
        @php $check = $preflight[$file->id]; @endphp
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="break-words font-medium">{{ $file->original_name }}</div>
                    <div class="mt-1 text-xs text-slate-500">
                        Vụ việc: {{ number_format($check['counters']['cases_total'], 0, ',', '.') }} ·
                        cần giải trình: {{ number_format($check['counters']['cases_need_explanation'], 0, ',', '.') }} ·
                        đã duyệt/đã xuất: {{ number_format($check['counters']['cases_settled'], 0, ',', '.') }}
                    </div>

                    @foreach ($check['blocking'] as $problem)
                        <div class="mt-2 rounded border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs text-rose-900">{{ $problem }}</div>
                    @endforeach
                    @foreach ($check['warnings'] as $warning)
                        <div class="mt-2 rounded border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs text-amber-900">{{ $warning }}</div>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('exports.store', $file) }}" class="flex shrink-0 items-center gap-2">
                    @csrf
                    @if ($check['blocking'] !== [])
                        <label class="flex items-center gap-1 text-xs text-slate-600">
                            <input type="checkbox" name="force" value="1" class="rounded border-slate-300">
                            Xuất dù còn cảnh báo
                        </label>
                    @endif
                    <button class="rounded bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">
                        Xuất tệp
                    </button>
                </form>
            </div>

            @if ($file->exports->isNotEmpty())
                <table class="mt-4 w-full text-xs">
                    <thead class="text-left text-slate-500">
                        <tr>
                            <th class="py-1">Phiên bản</th>
                            <th class="py-1">Thời điểm</th>
                            <th class="py-1">Dòng nguồn / dòng xuất</th>
                            <th class="py-1">Ô giải trình</th>
                            <th class="py-1">SHA-256</th>
                            <th class="py-1"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($file->exports->sortByDesc('version') as $artifact)
                            @php $checks = $artifact->verification['checks'] ?? []; @endphp
                            <tr>
                                <td class="py-1">v{{ $artifact->version }}</td>
                                <td class="py-1">{{ $artifact->created_at->format('d/m/Y H:i') }}</td>
                                <td class="py-1">
                                    {{ number_format($checks['source_rows'] ?? 0, 0, ',', '.') }} /
                                    {{ number_format($checks['exported_rows'] ?? 0, 0, ',', '.') }}
                                </td>
                                <td class="py-1">
                                    {{ number_format($checks['explanations_written'] ?? 0, 0, ',', '.') }} /
                                    {{ number_format($checks['explanations_expected'] ?? 0, 0, ',', '.') }}
                                </td>
                                <td class="py-1 font-mono">{{ Str::limit($artifact->sha256, 16, '…') }}</td>
                                <td class="py-1 text-right">
                                    <a href="{{ route('exports.download', $artifact) }}" class="text-teal-700 hover:underline">Tải về</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach
</div>
@endsection
