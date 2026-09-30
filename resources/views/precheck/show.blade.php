@extends('layouts.app')
@section('title', 'Lần chạy #'.$run->id)

@section('content')
<h1 class="mb-1 text-xl font-semibold">Lần chạy #{{ $run->id }}</h1>
<p class="mb-6 text-sm text-slate-600">
    {{ $run->batch->title }} · {{ $run->status }} ·
    {{ count($run->ruleset_snapshot ?? []) }} quy tắc ·
    bắt đầu {{ $run->started_at?->format('d/m/Y H:i') ?? '—' }}
</p>

@if ($run->failure_reason)
    <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
        {{ $run->failure_reason }}
    </div>
@endif

<div class="mb-6 grid gap-3 sm:grid-cols-4">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="text-xs uppercase tracking-wide text-slate-500">Hồ sơ nạp được</div>
        <div class="mt-1 text-2xl font-semibold">{{ number_format($run->encounters_total, 0, ',', '.') }}</div>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="text-xs uppercase tracking-wide text-slate-500">Dòng chi tiết</div>
        <div class="mt-1 text-2xl font-semibold">{{ number_format($run->details_total, 0, ',', '.') }}</div>
    </div>
    <div class="rounded-lg border border-rose-200 bg-rose-50 p-4">
        <div class="text-xs uppercase tracking-wide text-rose-700">Phát hiện nguy cơ</div>
        <div class="mt-1 text-2xl font-semibold text-rose-800">{{ number_format($run->detected_count, 0, ',', '.') }}</div>
    </div>
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
        <div class="text-xs uppercase tracking-wide text-amber-700">Không đủ dữ liệu</div>
        <div class="mt-1 text-2xl font-semibold text-amber-800">{{ number_format($run->insufficient_count, 0, ',', '.') }}</div>
        <div class="mt-1 text-xs text-amber-700">Không tính là đạt.</div>
    </div>
</div>

@if ($run->coverage)
    <section class="mb-6 overflow-hidden rounded-lg border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold">Độ phủ theo từng quy tắc</h2>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2">Quy tắc</th>
                    <th class="px-4 py-2 text-right">Dòng được kiểm tra</th>
                    <th class="px-4 py-2 text-right">Phát hiện</th>
                    <th class="px-4 py-2 text-right">Không đủ dữ liệu</th>
                    <th class="px-4 py-2 text-right">Ngoài phạm vi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($run->coverage as $code => $stats)
                    <tr>
                        <td class="px-4 py-2 font-mono">{{ $code }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($stats['checked'] ?? 0, 0, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($stats['detected'] ?? 0, 0, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($stats['insufficient'] ?? 0, 0, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right text-slate-500">{{ number_format($stats['out_of_scope'] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endif

<form method="GET" class="mb-4 flex flex-wrap gap-2">
    <select name="result" class="rounded border border-slate-300 px-3 py-1.5 text-sm">
        <option value="">Mọi kết quả</option>
        @foreach ($resultLabels as $value => $label)
            <option value="{{ $value }}" @selected(request('result') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <input name="rule" value="{{ request('rule') }}" placeholder="Mã quy tắc"
           class="rounded border border-slate-300 px-3 py-1.5 font-mono text-sm">
    <button class="rounded bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-800">Lọc</button>
</form>

<div class="space-y-3">
    @forelse ($findings as $finding)
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="mb-2 flex flex-wrap items-center gap-2 text-xs">
                <span class="rounded px-2 py-0.5 {{ $finding->result === 'detected' ? 'bg-rose-100 text-rose-900' : 'bg-amber-100 text-amber-900' }}">
                    {{ $finding->resultLabel() }}
                </span>
                <span class="rounded bg-slate-100 px-2 py-0.5 font-mono">{{ $finding->rule_code }} v{{ $finding->ruleVersion->version }}</span>
                <span class="text-slate-500">MA_LK {{ $finding->ma_lk ?? '—' }}</span>
                <span class="text-slate-500">
                    {{ $finding->locator['loai_ho_so'] ?? '' }}
                    @if (($finding->locator['stt'] ?? null) !== null) · STT {{ $finding->locator['stt'] }} @endif
                    @if (($finding->locator['hoso_index'] ?? null) !== null) · hồ sơ thứ {{ $finding->locator['hoso_index'] }} @endif
                </span>
            </div>

            <p class="text-sm">{{ $finding->reason ?? 'Không có mô tả.' }}</p>

            @if ($finding->values)
                <div class="mt-1 flex flex-wrap gap-2 text-xs text-slate-600">
                    @foreach ($finding->values as $field => $value)
                        <span class="rounded bg-slate-50 px-2 py-0.5">
                            <span class="font-mono">{{ $field }}</span>: {{ $value ?? '—' }}
                        </span>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('precheck.findings.handle', $finding) }}" class="mt-3 flex flex-wrap gap-2">
                @csrf
                <select name="handling_status" class="rounded border border-slate-300 px-2 py-1 text-xs">
                    @foreach (\App\Models\Finding::HANDLING_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected($finding->handling_status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <input name="handling_note" value="{{ $finding->handling_note }}" placeholder="Ghi chú / lý do giữ nguyên"
                       class="grow rounded border border-slate-300 px-2 py-1 text-xs">
                <button class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100">Lưu</button>
            </form>
        </div>
    @empty
        <p class="rounded-lg border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500">
            Không có phát hiện nào khớp bộ lọc.
        </p>
    @endforelse
</div>

<div class="mt-4">{{ $findings->links() }}</div>
@endsection
