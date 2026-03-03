<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Workstation') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="/css/app.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @stack('head')
</head>
<body class="h-full">
<div class="flex h-full flex-col">

    {{-- Top navigation --}}
    <header class="bg-indigo-700 text-white shrink-0 shadow-lg">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
            <div class="flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-500 text-sm font-bold">SF</div>
                <div>
                    <span class="font-bold text-sm">SmartFactory</span>
                    <span class="ml-2 text-xs text-indigo-300">Employee Portal</span>
                </div>
            </div>

            <nav class="flex items-center gap-1">
                <a href="{{ route('employee.dashboard') }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors
                          {{ request()->routeIs('employee.dashboard') ? 'bg-indigo-900 text-white' : 'text-indigo-200 hover:bg-indigo-600 hover:text-white' }}">
                    <span class="hidden sm:inline">My Machine</span>
                    <span class="sm:hidden">Dashboard</span>
                </a>
                <a href="{{ route('employee.jobs.index') }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors
                          {{ request()->routeIs('employee.jobs.*') ? 'bg-indigo-900 text-white' : 'text-indigo-200 hover:bg-indigo-600 hover:text-white' }}">
                    My Jobs
                </a>
            </nav>

            <div class="flex items-center gap-3">
                <span class="hidden sm:block text-xs text-indigo-300">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('employee.logout') }}">
                    @csrf
                    <button type="submit"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium text-indigo-200 hover:bg-indigo-600 hover:text-white transition-colors">
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </header>

    {{-- Flash messages --}}
    @if(session('success'))
    <div class="border-b border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-800">
        <div class="mx-auto max-w-5xl">{{ session('success') }}</div>
    </div>
    @endif
    @if(session('error'))
    <div class="border-b border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800">
        <div class="mx-auto max-w-5xl">{{ session('error') }}</div>
    </div>
    @endif

    <main class="flex-1 overflow-y-auto py-6">
        <div class="mx-auto max-w-5xl px-4">
            @yield('content')
        </div>
    </main>

    <footer class="shrink-0 border-t border-slate-200 bg-white py-2 text-center text-xs text-slate-400">
        SmartFactory Employee Portal &mdash; {{ now()->format('d M Y') }}
    </footer>
</div>
@stack('scripts')
</body>
</html>
