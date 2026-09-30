@extends('layouts.app')
@section('title', 'Vụ việc #'.$case->id)

@section('content')
@php
    $current = $case->explanations->first();
    $canExplain = auth()->user()->can('explain', $case);
    $canApprove = auth()->user()->can('approve', $case);
@endphp

<div class="mb-6">
    <a href="{{ url()->previous() }}" class="text-sm text-slate-500 hover:underline">&larr; Quay lại danh sách</a>
    <h1 class="mt-1 text-xl font-semibold">
        Vụ việc #{{ $case->id }} · mã lỗi <span class="font-mono">{{ $case->error_code }}</span>
    </h1>
    <p class="text-sm text-slate-600">
        {{ $case->sourceFile->original_name }} · dòng {{ $case->sourceRow->row_number }} ·
        trạng thái <strong>{{ $case->statusLabel() }}</strong>
    </p>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <section class="rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-3 text-sm font-semibold">Nội dung BHYT nêu</h2>
            <dl class="space-y-2 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Kết luận</dt>
                    <dd>{{ $case->conclusion ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Ghi chú (nguyên văn)</dt>
                    <dd class="whitespace-pre-wrap rounded bg-slate-50 p-3 text-slate-800">{{ $case->sourceRow->ghi_chu ?? '—' }}</dd>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">ID_CP</dt>
                        <dd class="font-mono">{{ $case->sourceRow->id_cp ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">MA_LK</dt>
                        <dd class="font-mono">{{ $case->sourceRow->ma_lk ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">TIEN_DE_NGHI_KIEM_TRA</dt>
                        <dd>{{ $case->sourceRow->tien_de_nghi_kiem_tra !== null ? number_format((float) $case->sourceRow->tien_de_nghi_kiem_tra, 0, ',', '.').' đ' : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Hạn xử lý</dt>
                        <dd>{{ $case->due_on?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                </div>
            </dl>
        </section>

        @if ($canExplain)
            <section class="rounded-lg border border-slate-200 bg-white p-4">
                <h2 class="mb-3 text-sm font-semibold">Soạn giải trình</h2>

                @if ($current?->status === \App\Models\Explanation::STATUS_RETURNED)
                    <div class="mb-3 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        Bản trước bị trả lại: {{ $current->return_reason }}
                    </div>
                @endif

                <form method="POST" action="{{ route('explanations.store', $case) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf

                    <div>
                        <label for="body" class="mb-1 block text-sm font-medium">Nội dung</label>
                        <textarea id="body" name="body" rows="8" required
                                  class="w-full rounded border border-slate-300 px-3 py-2 text-sm">{{ old('body', $current?->body ?? $case->catalog?->explanation_template) }}</textarea>
                        <p class="mt-1 text-xs text-slate-500">
                            Đây là nội dung sẽ nằm ở cột <span class="font-mono">{{ config('mediguard.export.column_header') }}</span>
                            của tệp gửi lại, không phải kết quả BHYT đã chấp thuận.
                        </p>
                    </div>

                    <div>
                        <label for="resolution" class="mb-1 block text-sm font-medium">Hướng xử lý</label>
                        <select id="resolution" name="resolution" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                            @foreach (\App\Models\Explanation::RESOLUTIONS as $value => $label)
                                <option value="{{ $value }}" @selected(old('resolution', $current?->resolution) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="evidences" class="mb-1 block text-sm font-medium">Minh chứng đính kèm</label>
                        <input id="evidences" type="file" name="evidences[]" multiple
                               class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    </div>

                    <div class="flex gap-2">
                        <button name="submit" value="0" class="rounded border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100">
                            Lưu nháp
                        </button>
                        <button name="submit" value="1" class="rounded bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">
                            Gửi duyệt
                        </button>
                    </div>
                </form>
            </section>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white">
            <h2 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold">Lịch sử giải trình</h2>
            <div class="divide-y divide-slate-100">
                @forelse ($case->explanations as $explanation)
                    <div class="p-4">
                        <div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                            <span class="rounded bg-slate-100 px-2 py-0.5">bản {{ $explanation->version }}</span>
                            <span class="rounded bg-slate-100 px-2 py-0.5">{{ $explanation->status }}</span>
                            <span>{{ $explanation->author?->name }}</span>
                            <span>{{ $explanation->updated_at?->format('d/m/Y H:i') }}</span>
                            @if ($explanation->resolution)
                                <span class="rounded bg-teal-50 px-2 py-0.5 text-teal-800">{{ $explanation->resolutionLabel() }}</span>
                            @endif
                        </div>
                        <p class="whitespace-pre-wrap text-sm">{{ $explanation->body }}</p>

                        @if ($explanation->evidences->isNotEmpty())
                            <ul class="mt-2 text-xs text-slate-600">
                                @foreach ($explanation->evidences as $evidence)
                                    <li>📎 {{ $evidence->original_name }} ({{ number_format($evidence->size_bytes / 1024, 0) }} KB)</li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($canApprove && $explanation->status === \App\Models\Explanation::STATUS_SUBMITTED)
                            <div class="mt-3 flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('explanations.approve', $explanation) }}">
                                    @csrf
                                    <button class="rounded bg-teal-600 px-3 py-1.5 text-sm text-white hover:bg-teal-700">Duyệt</button>
                                </form>
                                <form method="POST" action="{{ route('explanations.return', $explanation) }}" class="flex gap-2">
                                    @csrf
                                    <input name="return_reason" required placeholder="Lý do trả lại"
                                           class="rounded border border-slate-300 px-3 py-1.5 text-sm">
                                    <button class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-100">Trả lại</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-slate-500">Chưa có bản giải trình nào.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="space-y-6">
        @can('reassign', $case)
            <section class="rounded-lg border border-slate-200 bg-white p-4">
                <h2 class="mb-3 text-sm font-semibold">Chuyển người phụ trách</h2>
                <form method="POST" action="{{ route('cases.reassign', $case) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label for="assignee_id" class="mb-1 block text-xs font-medium text-slate-600">Người được giao</label>
                        <select id="assignee_id" name="assignee_id" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                            <option value="">Để trống (đưa vào hàng chờ của khoa)</option>
                            @foreach ($candidates as $candidate)
                                <option value="{{ $candidate->id }}" @selected($case->assignee_id === $candidate->id)>
                                    {{ $candidate->name }}@if ($candidate->staff_code) ({{ $candidate->staff_code }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="department_id" class="mb-1 block text-xs font-medium text-slate-600">Khoa theo dõi</label>
                        <select id="department_id" name="department_id" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                            <option value="">Không gán</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected($case->department_id === $department->id)>
                                    {{ $department->displayName() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="reason" class="mb-1 block text-xs font-medium text-slate-600">Lý do</label>
                        <input id="reason" name="reason" required class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                    </div>
                    <button class="w-full rounded bg-slate-900 px-3 py-2 text-sm text-white hover:bg-slate-800">Chuyển</button>
                </form>
            </section>
        @endcan

        <section class="rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-2 text-sm font-semibold">Cách hệ thống chọn người</h2>
            <p class="text-xs text-slate-600">
                Bước chọn đã dùng: <span class="font-mono">{{ $case->assignment_source ?? '—' }}</span>
            </p>
            <ul class="mt-2 space-y-1 text-xs text-slate-600">
                @foreach ($case->assignments as $assignment)
                    <li>
                        {{ $assignment->created_at->format('d/m/Y H:i') }} —
                        {{ $assignment->assignee?->name ?? 'hàng chờ' }}
                        ({{ $assignment->resolver }})
                        @if ($assignment->reason)<br><span class="text-slate-500">{{ $assignment->reason }}</span>@endif
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white">
            <h2 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold">
                Toàn bộ dữ liệu nguồn ({{ count($columns) }} cột)
            </h2>
            <div class="max-h-[40rem] overflow-auto">
                <table class="w-full text-xs">
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($columns as $column)
                            @php $value = $cells[$column->position] ?? ''; @endphp
                            <tr class="{{ trim((string) $value) === '' ? 'text-slate-400' : '' }}">
                                <th class="w-2/5 px-3 py-1.5 text-left align-top font-mono font-normal text-slate-500">
                                    {{ $column->label() }}
                                </th>
                                <td class="px-3 py-1.5 break-words">{{ $value !== '' ? $value : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection
