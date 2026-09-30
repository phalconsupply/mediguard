@extends('layouts.app')
@section('title', 'Lỗi '.$catalog->code)

@section('content')
<h1 class="mb-1 text-xl font-semibold">Mã lỗi <span class="font-mono">{{ $catalog->code }}</span></h1>
<p class="mb-6 text-sm text-slate-600">{{ $catalog->name ?? 'Chưa đặt tên' }}</p>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
        <section class="rounded-lg border border-slate-200 bg-white">
            <h2 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold">
                Biến thể ghi nhận được ({{ $catalog->variants->count() }})
            </h2>
            <div class="divide-y divide-slate-100">
                @forelse ($catalog->variants as $variant)
                    <div class="p-4">
                        <div class="mb-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                            <span class="rounded bg-slate-100 px-2 py-0.5">kỳ {{ $variant->period ?? '—' }}</span>
                            <span class="rounded px-2 py-0.5 {{ $variant->status === 'new' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100' }}">
                                {{ $variant->status }}
                            </span>
                            <span>{{ number_format($variant->row_count, 0, ',', '.') }} dòng</span>
                            <span>{{ count($variant->column_fingerprint['headers'] ?? []) }} cột</span>
                        </div>
                        <p class="text-sm text-slate-700">{{ $variant->sample_note ?? 'Không có ghi chú mẫu.' }}</p>

                        @if (auth()->user()->isManager())
                            <form method="POST" action="{{ route('catalog.variants.review', $variant) }}" class="mt-2 flex gap-2">
                                @csrf @method('PUT')
                                <select name="status" class="rounded border border-slate-300 px-2 py-1 text-xs">
                                    @foreach (['new' => 'Mới', 'reviewed' => 'Đã xem', 'merged' => 'Đã gộp'] as $value => $label)
                                        <option value="{{ $value }}" @selected($variant->status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100">Lưu</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-slate-500">Chưa ghi nhận biến thể nào.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white">
            <h2 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold">Quy tắc tự kiểm tra liên quan</h2>
            <div class="divide-y divide-slate-100">
                @forelse ($catalog->rules as $rule)
                    <div class="flex items-center justify-between p-4 text-sm">
                        <div>
                            <a href="{{ route('rules.show', $rule) }}" class="font-mono text-teal-700 hover:underline">{{ $rule->code }}</a>
                            <span class="text-slate-600">— {{ $rule->name }}</span>
                        </div>
                        <div class="text-xs text-slate-500">{{ $rule->versions->count() }} phiên bản</div>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-slate-500">
                        Chưa có quy tắc máy. Lỗi này hiện chỉ giải trình thủ công.
                    </p>
                @endforelse
            </div>
        </section>
    </div>

    @if (auth()->user()->isManager())
        <form method="POST" action="{{ route('catalog.update', $catalog) }}"
              class="space-y-3 rounded-lg border border-slate-200 bg-white p-4">
            @csrf @method('PUT')
            <h2 class="text-sm font-semibold">Phân loại</h2>

            <div>
                <label for="name" class="mb-1 block text-xs font-medium text-slate-600">Tên dễ hiểu</label>
                <input id="name" name="name" value="{{ old('name', $catalog->name) }}" required
                       class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
            </div>

            <div>
                <label for="group" class="mb-1 block text-xs font-medium text-slate-600">Nhóm lỗi</label>
                <input id="group" name="group" value="{{ old('group', $catalog->group) }}" list="groups"
                       class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                <datalist id="groups">
                    @foreach (array_keys(config('mediguard.columns_by_group')) as $group)
                        <option value="{{ $group }}"></option>
                    @endforeach
                </datalist>
            </div>

            <div>
                <label for="responsible_role" class="mb-1 block text-xs font-medium text-slate-600">Ai phải giải trình</label>
                <select id="responsible_role" name="responsible_role" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                    @foreach (['orderer' => 'Người chỉ định/kê đơn', 'performer' => 'Người thực hiện', 'both' => 'Cả hai', 'unknown' => 'Chưa xác định'] as $value => $label)
                        <option value="{{ $value }}" @selected($catalog->responsible_role === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="mb-1 block text-xs font-medium text-slate-600">Trạng thái</label>
                <select id="status" name="status" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                    @foreach (['pending_review' => 'Chờ phân loại', 'classified' => 'Đã phân loại', 'manual_only' => 'Chỉ giải trình thủ công', 'retired' => 'Ngừng dùng'] as $value => $label)
                        <option value="{{ $value }}" @selected($catalog->status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="priority" class="mb-1 block text-xs font-medium text-slate-600">Mức ưu tiên (1 cao — 5 thấp)</label>
                <input id="priority" name="priority" type="number" min="1" max="5" value="{{ $catalog->priority }}"
                       class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
            </div>

            <div>
                <label for="description" class="mb-1 block text-xs font-medium text-slate-600">Mô tả</label>
                <textarea id="description" name="description" rows="4"
                          class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">{{ old('description', $catalog->description) }}</textarea>
            </div>

            <div>
                <label for="explanation_template" class="mb-1 block text-xs font-medium text-slate-600">Mẫu nội dung giải trình</label>
                <textarea id="explanation_template" name="explanation_template" rows="4"
                          class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">{{ old('explanation_template', $catalog->explanation_template) }}</textarea>
            </div>

            <button class="w-full rounded bg-slate-900 px-3 py-2 text-sm text-white hover:bg-slate-800">Lưu</button>
            <p class="text-xs text-slate-500">
                Sửa mô tả ở đây không thay đổi logic của quy tắc kiểm tra.
            </p>
        </form>
    @endif
</div>
@endsection
