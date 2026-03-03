<!DOCTYPE html>
<html lang="en" class="h-full bg-gradient-to-br from-indigo-50 to-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Sign In — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="flex h-full items-center justify-center px-4">
<div class="w-full max-w-sm">

    {{-- Logo / branding --}}
    <div class="mb-8 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-600 text-white text-xl font-bold shadow-lg">
            SF
        </div>
        <h1 class="text-2xl font-bold text-gray-900">Employee Portal</h1>
        <p class="mt-1 text-sm text-gray-500">Sign in to view your workstation</p>
    </div>

    {{-- Card --}}
    <div class="rounded-2xl bg-white px-8 py-8 shadow-sm ring-1 ring-gray-200">

        @if($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
        @endif

        <form method="POST" action="{{ route('employee.do-login') }}" class="space-y-5">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Email address
                </label>
                <input
                    id="email" name="email" type="email"
                    value="{{ old('email') }}"
                    required autofocus autocomplete="email"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm
                           focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200
                           @error('email') border-red-300 @enderror"
                >
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Password
                </label>
                <input
                    id="password" name="password" type="password"
                    required autocomplete="current-password"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm
                           focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                >
            </div>

            <div class="flex items-center gap-2">
                <input id="remember" name="remember" type="checkbox"
                       class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label for="remember" class="text-sm text-gray-600">Remember me</label>
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm
                           hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2
                           transition-colors">
                Sign In
            </button>
        </form>
    </div>

    <p class="mt-5 text-center text-xs text-gray-400">
        Admin?
        <a href="{{ route('login') }}" class="font-medium text-indigo-500 hover:text-indigo-700 hover:underline">
            Sign in to admin panel
        </a>
    </p>
</div>
</body>
</html>
