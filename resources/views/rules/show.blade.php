@extends('layouts.app')
@section('title', 'Quy tắc '.$rule->code)

@section('content')
<h1 class="mb-1 text-xl font-semibold">
    <span class="font-mono">{{ $rule->code }}</span> — {{ $rule->name }}
</h1>
<p class="mb-6 text-sm text-slate-600">{{ $rule->description ?? 'Chưa có mô tả.' }}</p>

<div class="grid gap-6 lg:grid-cols-3">
    <section class="lg:col-span-2 space-y-4">
        @foreach ($rule->versions as $version)
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="mb-2 flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded bg-slate-900 px-2 py-0.5 text-white">v{{ $version->version }}</span>
                    <span class="rounded px-2 py-0.5 {{ $version->status === 'active' ? 'bg-teal-100 text-teal-900' : 'bg-slate-100 text-slate-700' }}">
                        {{ $version->statusLabel() }}
                    </span>
                    <span class="rounded bg-slate-100 px-2 py-0.5">{{ $version->severity }}</span>
                    @if ($version->effective_from)
                        <span class="text-slate-500">từ {{ $version->effective_from->format('d/m/Y') }}</span>
                    @endif
                    <span class="text-slate-500">soạn: {{ $version->author?->name ?? '—' }}</span>
                    @if ($version->approver)
                        <span class="text-slate-500">duyệt: {{ $version->approver->name }}</span>
                    @endif
                </div>

                <pre class="overflow-auto rounded bg-slate-50 p-3 text-xs">{{ json_encode($version->definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>

                <div class="mt-2 text-xs text-slate-600">
                    Trường bắt buộc:
                    <span class="font-mono">{{ implode(', ', $version->required_fields ?? []) }}</span>
                    — thiếu một trong các trường này thì bản ghi được báo
                    <strong>không đủ dữ liệu để kiểm tra</strong>.
                </div>

                @if (empty($version->test_cases))
                    <div class="mt-2 rounded border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs text-amber-900">
                        Chưa có bộ ca kiểm thử (ca đúng, ca cảnh báo, ca thiếu dữ liệu) nên chưa duyệt được.
                    </div>
                @endif

                @if (auth()->user()->isManager())
                    <form method="POST" action="{{ route('rules.versions.transition', $version) }}" class="mt-3 flex gap-2">
                        @csrf
                        <select name="status" class="rounded border border-slate-300 px-2 py-1 text-xs">
                            @foreach (\App\Models\CheckRuleVersion::STATUS_LABELS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100">Chuyển trạng thái</button>
                    </form>
                @endif
            </div>
        @endforeach
    </section>

    @if (auth()->user()->isManager())
        <form method="POST" action="{{ route('rules.versions.store', $rule) }}"
              class="space-y-3 rounded-lg border border-slate-200 bg-white p-4">
            @csrf
            <h2 class="text-sm font-semibold">Phiên bản mới</h2>

            <div>
                <label for="definition" class="mb-1 block text-xs font-medium text-slate-600">Định nghĩa (JSON)</label>
                <textarea id="definition" name="definition" rows="14" required
                          class="w-full rounded border border-slate-300 px-2 py-1.5 font-mono text-xs">{{ old('definition', <<<'JSON'
{
  "subject": "detail",
  "filter": {"all": [{"field": "detail_kind", "op": "equals", "value": "dvkt"}]},
  "condition": {"any": [{"field": "ngay_kq", "op": "before_field", "value": "ngay_th_yl"}]},
  "message": "Giờ có kết quả trước giờ thực hiện y lệnh."
}
JSON) }}</textarea>
            </div>

            <div>
                <label for="required_fields" class="mb-1 block text-xs font-medium text-slate-600">
                    Trường bắt buộc (cách nhau dấu phẩy)
                </label>
                <input id="required_fields" name="required_fields" value="{{ old('required_fields') }}"
                       placeholder="ngay_th_yl, ngay_kq"
                       class="w-full rounded border border-slate-300 px-2 py-1.5 font-mono text-xs">
                <p class="mt-1 text-xs text-slate-500">Bỏ trống thì lấy đúng các trường mà điều kiện đụng tới.</p>
            </div>

            <div>
                <label for="severity" class="mb-1 block text-xs font-medium text-slate-600">Mức cảnh báo</label>
                <select id="severity" name="severity" class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                    <option value="info">Thông tin</option>
                    <option value="warning" selected>Cảnh báo</option>
                    <option value="blocker">Phải xử lý trước khi gửi</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label for="effective_from" class="mb-1 block text-xs font-medium text-slate-600">Hiệu lực từ</label>
                    <input id="effective_from" name="effective_from" type="date"
                           class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label for="effective_to" class="mb-1 block text-xs font-medium text-slate-600">Đến</label>
                    <input id="effective_to" name="effective_to" type="date"
                           class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                </div>
            </div>

            <button class="w-full rounded bg-slate-900 px-3 py-2 text-sm text-white hover:bg-slate-800">
                Tạo phiên bản nháp
            </button>
        </form>
    @endif
</div>
@endsection
