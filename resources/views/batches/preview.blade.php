@extends('layouts.app')
@section('title', 'Xem trước nhận diện')

@section('content')
<h1 class="mb-1 text-xl font-semibold">Xem trước: {{ $file->original_name }}</h1>
<p class="mb-6 text-sm text-slate-600">
    Nếu nhận diện sai trang tính hoặc dòng tiêu đề, chọn lại rồi nạp lại tệp này.
    Việc nạp lại chỉ ảnh hưởng tệp này, các tệp khác trong đợt giữ nguyên.
</p>

<div class="grid gap-6 lg:grid-cols-3">
    <form method="POST" action="{{ route('files.reingest', $file) }}"
          class="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
        @csrf

        <div>
            <label for="sheet_index" class="mb-1 block text-sm font-medium">Trang tính dữ liệu</label>
            <select id="sheet_index" name="sheet_index" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                @foreach ($sheets as $sheet)
                    <option value="{{ $sheet['index'] }}" @selected(($detected['sheet_index'] ?? 0) === $sheet['index'])>
                        {{ $sheet['name'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="header_row_number" class="mb-1 block text-sm font-medium">Dòng tiêu đề</label>
            <input id="header_row_number" name="header_row_number" type="number" min="1"
                   value="{{ $detected['header_row_number'] ?? 1 }}"
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div class="rounded bg-slate-50 p-3 text-xs text-slate-600">
            Mức tin cậy nhận diện:
            <strong>{{ ['high' => 'cao', 'medium' => 'trung bình', 'low' => 'thấp'][$detected['confidence'] ?? 'low'] ?? 'chưa rõ' }}</strong>
        </div>

        <button class="w-full rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
            Nạp lại với lựa chọn này
        </button>
    </form>

    <div class="lg:col-span-2 overflow-hidden rounded-lg border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold">
            Cột nhận được ({{ count($detected['columns'] ?? []) }})
        </h2>
        <div class="max-h-[32rem] overflow-auto">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Vị trí</th>
                        <th class="px-4 py-2">Tiêu đề gốc</th>
                        <th class="px-4 py-2">Tên chuẩn hóa</th>
                        <th class="px-4 py-2">Lần xuất hiện</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($detected['columns'] ?? [] as $column)
                        <tr class="{{ $column['occurrence'] > 1 ? 'bg-amber-50' : '' }}">
                            <td class="px-4 py-1.5 text-slate-500">{{ $column['position'] + 1 }}</td>
                            <td class="px-4 py-1.5 font-mono text-xs">{{ $column['raw_header'] }}</td>
                            <td class="px-4 py-1.5 font-mono text-xs text-slate-600">{{ $column['normalized_header'] }}</td>
                            <td class="px-4 py-1.5">
                                {{ $column['occurrence'] }}
                                @if ($column['occurrence'] > 1)
                                    <span class="ml-1 text-xs text-amber-800">tiêu đề trùng</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
