@extends('layouts.app')
@section('title', 'Tiến độ')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold">Tiến độ giải trình</h1>
        <p class="text-sm text-slate-600">
            Số liệu trong phạm vi được phân quyền của bạn. Cột tiền đang dùng:
            <span class="font-mono text-slate-800">{{ $amountColumn }}</span>.
        </p>
    </div>

    <form method="GET" class="flex items-end gap-2">
        <div>
            <label for="batch" class="mb-1 block text-xs font-medium text-slate-600">Đợt</label>
            <select id="batch" name="batch" class="rounded border border-slate-300 px-3 py-1.5 text-sm">
                <option value="">Tất cả</option>
                @foreach ($batches as $batch)
                    <option value="{{ $batch->id }}" @selected($selectedBatch == $batch->id)>
                        {{ $batch->title }} ({{ $batch->period ?? 'chưa rõ kỳ' }})
                    </option>
                @endforeach
            </select>
        </div>
        <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-100">Xem</button>
    </form>
</div>

<div class="mb-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    @foreach (\App\Models\IssueCase::STATUS_LABELS as $status => $label)
        @php $row = $byStatus->get($status); @endphp
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</div>
            <div class="mt-1 text-2xl font-semibold">{{ number_format((int) ($row->total ?? 0), 0, ',', '.') }}</div>
            <div class="text-xs text-slate-500">
                {{ number_format((float) ($row->amount ?? 0), 0, ',', '.') }} đ
            </div>
        </div>
    @endforeach
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <section class="rounded-lg border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold">Theo mã lỗi</h2>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2">Mã</th>
                    <th class="px-4 py-2 text-right">Số dòng</th>
                    <th class="px-4 py-2 text-right">Cần giải trình</th>
                    <th class="px-4 py-2 text-right">Đã xong</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($byErrorCode as $row)
                    <tr>
                        <td class="px-4 py-2 font-mono">
                            <a href="{{ route('cases.index', ['error_code' => $row->error_code, 'batch' => $selectedBatch]) }}"
                               class="text-teal-700 hover:underline">{{ $row->error_code }}</a>
                        </td>
                        <td class="px-4 py-2 text-right">{{ number_format($row->total, 0, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format((int) $row->needs, 0, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format((int) $row->settled, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Chưa có dữ liệu.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold">Theo khoa/đơn vị theo dõi</h2>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2">Khoa</th>
                    <th class="px-4 py-2 text-right">Số dòng</th>
                    <th class="px-4 py-2 text-right">Đã xong</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($byDepartment as $row)
                    <tr>
                        <td class="px-4 py-2">
                            {{ $row->code ?? 'Chưa gán khoa' }}
                            @if ($row->name)<span class="text-slate-500">— {{ $row->name }}</span>@endif
                        </td>
                        <td class="px-4 py-2 text-right">{{ number_format($row->total, 0, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format((int) $row->settled, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-slate-500">Chưa có dữ liệu.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
@endsection
