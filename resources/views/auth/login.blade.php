@extends('layouts.app')
@section('title', 'Đăng nhập')

@section('content')
<div class="mx-auto max-w-sm">
    <h1 class="mb-1 text-xl font-semibold">Đăng nhập</h1>
    <p class="mb-6 text-sm text-slate-600">Tài khoản do bộ phận quản trị của bệnh viện cấp.</p>

    <form method="POST" action="{{ route('login') }}" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6">
        @csrf

        <div>
            <label for="email" class="mb-1 block text-sm font-medium">Địa chỉ thư điện tử</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none">
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium">Mật khẩu</label>
            <input id="password" name="password" type="password" required
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none">
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
            Ghi nhớ trên máy này
        </label>

        <button class="w-full rounded bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">
            Đăng nhập
        </button>
    </form>
</div>
@endsection
