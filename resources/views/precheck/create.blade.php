@extends('layouts.app')
@section('title', 'Chạy tự kiểm tra')

@section('content')
<h1 class="mb-1 text-xl font-semibold">Chạy tự kiểm tra — {{ $batch->title }}</h1>
<p class="mb-6 text-sm text-slate-600">
    Chỉ các quy tắc <strong>đang áp dụng</strong> và còn hiệu lực tại kỳ
    <span class="font-mono">{{ $batch->period ?? 'chưa rõ' }}</span> mới hiện ở đây.
</p>

@if ($versions->isEmpty())
    <div class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Chưa có quy tắc nào ở trạng thái đang áp dụng cho kỳ này. Vào
        <a href="{{ route('rules.index') }}" class="underline">Quy tắc</a> để duyệt và áp dụng trước.
    </div>
@else
    <form method="POST" action="{{ route('precheck.store', $batch) }}" class="rounded-lg border border-slate-200 bg-white">
        @csrf
        <div class="divide-y divide-slate-100">
            @foreach ($versions as $version)
                <label class="flex items-start gap-3 p-4 hover:bg-slate-50">
                    <input type="checkbox" name="rule_version_ids[]" value="{{ $version->id }}" checked
                           class="mt-1 rounded border-slate-300">
                    <div>
                        <div class="text-sm font-medium">
                            <span class="font-mono">{{ $version->rule->code }}</span> v{{ $version->version }}
                            — {{ $version->rule->name }}
                        </div>
                        <div class="mt-0.5 text-xs text-slate-500">
                            mức {{ $version->severity }} ·
                            trường bắt buộc: <span class="font-mono">{{ implode(', ', $version->required_fields ?? []) }}</span>
                        </div>
                    </div>
                </label>
            @endforeach
        </div>

        <div class="border-t border-slate-200 p-4">
            <button class="rounded bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">
                Chạy nền
            </button>
        </div>
    </form>
@endif
@endsection
