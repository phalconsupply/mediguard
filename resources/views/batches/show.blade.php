@extends('layouts.app')
@section('title', $batch->title)

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold">{{ $batch->title }}</h1>
        <p class="text-sm text-slate-600">
            <span class="font-mono">{{ $batch->code }}</span> ·
            {{ $batch->kind === 'response' ? 'Phản hồi BHYT' : 'Tự kiểm tra' }} ·
            kỳ {{ $batch->period ?? 'chưa rõ' }} ·
            cơ sở {{ $batch->facility_code ?? 'chưa rõ' }}
        </p>
    </div>

    <div class="flex gap-2">
        @if ($batch->kind === 'response')
            <a href="{{ route('exports.index', $batch) }}" class="rounded border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100">Xuất trả</a>
            <a href="{{ route('cases.index', ['batch' => $batch->id]) }}" class="rounded bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Mở công việc</a>
        @else
            <a href="{{ route('precheck.create', $batch) }}" class="rounded bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Chạy tự kiểm tra</a>
        @endif
    </div>
</div>

<form method="POST" action="{{ route('batches.upload', $batch) }}" enctype="multipart/form-data"
      class="mb-6 rounded-lg border border-slate-200 bg-white p-4">
    @csrf
    <div class="flex flex-wrap items-end gap-4">
        <div class="grow">
            <label for="files" class="mb-1 block text-sm font-medium">Nạp tệp</label>
            <input id="files" type="file" name="files[]" multiple required
                   accept="{{ $batch->kind === 'response' ? '.xlsx,.csv' : '.xml,.xlsx,.csv' }}"
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-slate-500">
                Tối đa {{ config('mediguard.storage.max_upload_mb') }} MB mỗi tệp.
                Mã lỗi được nhận từ tên tệp dạng <span class="font-mono">TACH_9065_…</span>.
            </p>
        </div>

        @if ($batch->kind === 'precheck')
            <div>
                <label for="mapping_profile_id" class="mb-1 block text-sm font-medium">Cấu hình ánh xạ</label>
                <select id="mapping_profile_id" name="mapping_profile_id" class="rounded border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($profiles as $profile)
                        <option value="{{ $profile->id }}">{{ $profile->label() }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <button class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Nạp</button>
    </div>
</form>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-2">Tệp</th>
                <th class="px-4 py-2">Mã lỗi</th>
                <th class="px-4 py-2">Trang dữ liệu</th>
                <th class="px-4 py-2 text-right">Dòng</th>
                <th class="px-4 py-2 text-right">Cột</th>
                <th class="px-4 py-2">Trạng thái</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($batch->files as $file)
                @php $sheet = $file->sheets->firstWhere('is_data_sheet', true); @endphp
                <tr class="align-top hover:bg-slate-50">
                    <td class="px-4 py-2">
                        <div class="max-w-md break-words">{{ $file->original_name }}</div>
                        <div class="font-mono text-xs text-slate-400">{{ Str::limit($file->sha256, 16, '…') }}</div>
                        @if ($file->failure_reason)
                            <div class="mt-1 rounded bg-rose-50 px-2 py-1 text-xs text-rose-800">{{ $file->failure_reason }}</div>
                        @endif
                        @if (($file->detected['issues'] ?? []) !== [])
                            <details class="mt-1 text-xs text-amber-800">
                                <summary class="cursor-pointer">{{ count($file->detected['issues']) }} điểm cần xem khi đọc tệp</summary>
                                <ul class="mt-1 list-inside list-disc space-y-0.5">
                                    @foreach (array_slice($file->detected['issues'], 0, 20) as $issue)
                                        <li>{{ $issue }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </td>
                    <td class="px-4 py-2 font-mono">{{ $file->error_code ?? '—' }}</td>
                    <td class="px-4 py-2">
                        {{ $sheet?->sheet_name ?? '—' }}
                        @if ($sheet)
                            <div class="text-xs text-slate-500">tiêu đề ở dòng {{ $sheet->header_row_number }}</div>
                        @endif
                        @if (($file->detected['confidence'] ?? null) === 'low')
                            <div class="mt-1 rounded bg-amber-50 px-2 py-1 text-xs text-amber-900">Nhận diện chưa chắc chắn — nên xem lại.</div>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right">{{ $sheet ? number_format($sheet->row_count, 0, ',', '.') : '—' }}</td>
                    <td class="px-4 py-2 text-right">{{ $sheet?->column_count ?? '—' }}</td>
                    <td class="px-4 py-2">
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs">{{ $file->status }}</span>
                    </td>
                    <td class="px-4 py-2 text-right">
                        @if ($batch->kind === 'response' && in_array($file->format, ['xlsx', 'csv'], true))
                            <a href="{{ route('files.preview', $file) }}" class="text-sm text-teal-700 hover:underline">Xem trước</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">Chưa nạp tệp nào.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
