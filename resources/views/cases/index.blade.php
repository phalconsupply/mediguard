@extends('layouts.app')
@section('title', 'Công việc giải trình')

@section('content')
<h1 class="mb-1 text-xl font-semibold">Công việc giải trình</h1>
<p class="mb-4 text-sm text-slate-600">
    Mỗi dòng trong mỗi tệp là một việc riêng, kể cả khi cùng một <span class="font-mono">ID_CP</span>
    bị nêu ở nhiều tệp.
</p>

<form method="GET" class="mb-4 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
    <div>
        <label for="batch" class="mb-1 block text-xs font-medium text-slate-600">Đợt</label>
        <select id="batch" name="batch" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
            <option value="">Tất cả</option>
            @foreach ($batches as $batch)
                <option value="{{ $batch->id }}" @selected(request('batch') == $batch->id)>{{ $batch->title }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="error_code" class="mb-1 block text-xs font-medium text-slate-600">Mã lỗi</label>
        <select id="error_code" name="error_code" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
            <option value="">Tất cả</option>
            @foreach ($errorCodes as $catalog)
                <option value="{{ $catalog->code }}" @selected(request('error_code') == $catalog->code)>
                    {{ $catalog->code }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="status" class="mb-1 block text-xs font-medium text-slate-600">Trạng thái</label>
        <select id="status" name="status" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
            <option value="">Tất cả</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(request('status') == $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="department" class="mb-1 block text-xs font-medium text-slate-600">Khoa</label>
        <select id="department" name="department" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
            <option value="">Tất cả</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected(request('department') == $department->id)>
                    {{ $department->displayName() }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="q" class="mb-1 block text-xs font-medium text-slate-600">ID_CP hoặc MA_LK</label>
        <input id="q" name="q" value="{{ request('q') }}" class="w-full rounded border border-slate-300 px-2 py-1.5 font-mono text-sm">
    </div>

    <div class="flex items-end gap-2">
        <label class="flex items-center gap-1.5 text-xs text-slate-600">
            <input type="checkbox" name="needs_explanation" value="1" @checked(request()->boolean('needs_explanation'))
                   class="rounded border-slate-300">
            Chỉ dòng cần giải trình
        </label>
        <button class="rounded bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-800">Lọc</button>
    </div>
</form>

@if ($layout['missing'] !== [])
    <div class="mb-3 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
        Cấu hình cột có {{ count($layout['missing']) }} cột không có trong kỳ này nên được bỏ qua:
        <span class="font-mono text-xs">{{ implode(', ', array_slice($layout['missing'], 0, 12)) }}</span>
    </div>
@endif

@if ($layout['new_columns'] !== [] && auth()->user()->isManager())
    <details class="mb-3 rounded border border-slate-200 bg-white px-4 py-2 text-sm">
        <summary class="cursor-pointer text-slate-700">
            {{ count($layout['new_columns']) }} cột nguồn chưa bật hiển thị
        </summary>
        <p class="mt-2 text-xs text-slate-500">
            Cột ẩn vẫn nằm đủ trong tệp xuất trả. Bật/tắt tại
            <a href="{{ route('presets.index') }}" class="text-teal-700 hover:underline">Chọn cột</a>.
        </p>
        <div class="mt-2 flex flex-wrap gap-1">
            @foreach (array_slice($layout['new_columns'], 0, 60) as $column)
                <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs">{{ $column->label() }}</span>
            @endforeach
        </div>
    </details>
@endif

<div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="whitespace-nowrap px-3 py-2">Mã lỗi</th>
                @foreach ($layout['columns'] as $column)
                    <th class="whitespace-nowrap px-3 py-2">{{ $column->label() }}</th>
                @endforeach
                <th class="whitespace-nowrap px-3 py-2">Người được giao</th>
                <th class="whitespace-nowrap px-3 py-2">Trạng thái</th>
                <th class="px-3 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($cases as $case)
                <tr class="hover:bg-slate-50">
                    <td class="whitespace-nowrap px-3 py-2 font-mono">{{ $case->error_code }}</td>
                    @foreach ($layout['columns'] as $column)
                        <td class="max-w-xs truncate px-3 py-2" title="{{ $case->sourceRow?->cellAt($column->position) }}">
                            {{ $case->sourceRow?->cellAt($column->position) }}
                        </td>
                    @endforeach
                    <td class="whitespace-nowrap px-3 py-2">
                        {{ $case->assignee?->name ?? '—' }}
                        @if ($case->department)
                            <div class="text-xs text-slate-500">{{ $case->department->code }}</div>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-2">
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs">{{ $case->statusLabel() }}</span>
                        @unless ($case->requires_explanation)
                            <div class="text-xs text-slate-400">không cần giải trình</div>
                        @endunless
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right">
                        <a href="{{ route('cases.show', $case) }}" class="text-teal-700 hover:underline">Mở</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($layout['columns']) + 4 }}" class="px-4 py-8 text-center text-slate-500">
                        Không có công việc nào khớp bộ lọc.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $cases->links() }}</div>
@endsection
