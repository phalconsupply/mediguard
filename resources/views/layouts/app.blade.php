<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'MediGuard') — Giải trình dữ liệu BHYT</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 text-slate-900 antialiased">
<div class="min-h-full">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-screen-2xl items-center gap-6 px-4 py-3">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-semibold">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded bg-teal-600 text-sm text-white">MG</span>
                MediGuard
            </a>

            @auth
                <nav class="flex flex-wrap items-center gap-1 text-sm">
                    @php
                        $links = [
                            'dashboard' => 'Tiến độ',
                            'batches.index' => 'Đợt dữ liệu',
                            'cases.index' => 'Công việc',
                            'catalog.index' => 'Thư viện lỗi',
                            'rules.index' => 'Quy tắc',
                            'precheck.index' => 'Tự kiểm tra',
                        ];
                        if (auth()->user()->isManager()) {
                            $links['presets.index'] = 'Chọn cột';
                            $links['directory.index'] = 'Danh mục';
                        }
                    @endphp
                    @foreach ($links as $route => $label)
                        <a href="{{ route($route) }}"
                           class="rounded px-3 py-1.5 transition hover:bg-slate-100 {{ request()->routeIs(Str::before($route, '.').'*') ? 'bg-slate-900 text-white hover:bg-slate-900' : 'text-slate-600' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>

                <div class="ml-auto flex items-center gap-3 text-sm">
                    <div class="text-right leading-tight">
                        <div class="font-medium">{{ auth()->user()->name }}</div>
                        <div class="text-xs text-slate-500">
                            {{ auth()->user()->role }}@if (auth()->user()->department) · {{ auth()->user()->department->code }} @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded border border-slate-300 px-3 py-1.5 hover:bg-slate-100">Thoát</button>
                    </form>
                </div>
            @endauth
        </div>
    </header>

    <main class="mx-auto max-w-screen-2xl px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="mx-auto max-w-screen-2xl px-4 pb-8 text-xs text-slate-500">
        Dữ liệu người bệnh chỉ hiển thị trong phạm vi được phân quyền. Mọi lượt xem, tải tệp và
        thay đổi giải trình đều được ghi nhật ký.
    </footer>
</div>
</body>
</html>
