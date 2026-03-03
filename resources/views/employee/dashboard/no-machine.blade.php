@extends('employee.layouts.app')
@section('title', 'No Machine Assigned')

@section('content')
<div class="flex flex-col items-center justify-center py-24 text-center">
    <div class="mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-yellow-100 text-4xl">
        🔧
    </div>
    <h2 class="text-xl font-bold text-gray-800 mb-2">No Machine Assigned</h2>
    <p class="text-gray-500 text-sm max-w-xs leading-relaxed">
        Your account has not been assigned to a machine yet.
        Please contact your supervisor or factory administrator.
    </p>
    <p class="mt-6 text-xs text-gray-400">Logged in as: {{ auth()->user()->email }}</p>
</div>
@endsection
