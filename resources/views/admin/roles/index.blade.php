{{--
    Roles List
    ===========
    Lists all roles with permission counts.
    Data from PermissionService::getRoleSummaries() — each item has:
      'role'              → Spatie Role model (with ->id, ->name)
      'label'             → human label
      'description'       → human description
      'level'             → int hierarchy level
      'is_factory_scoped' → bool
      'permission_count'  → int
--}}
@extends('admin.layouts.app')

@section('title', 'Roles')

@section('content')

<div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">

    <div class="border-b border-gray-100 px-5 py-4">
        <h2 class="text-sm font-semibold text-gray-700">Role Definitions</h2>
        <p class="mt-0.5 text-xs text-gray-400">
            Manage what each role is allowed to do. Super Admin permissions are always granted via Gate bypass.
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-100 bg-gray-50">
                <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <th class="px-5 py-3">Role</th>
                    <th class="px-5 py-3">Description</th>
                    <th class="px-5 py-3 text-center">Permissions</th>
                    <th class="px-5 py-3">Scope</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($summaries as $summary)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3">
                        <span class="font-medium text-gray-900">{{ $summary['label'] }}</span>
                        <p class="mt-0.5 font-mono text-xs text-gray-400">{{ $summary['role']->name }}</p>
                    </td>
                    <td class="px-5 py-3 text-gray-600 max-w-xs">
                        {{ $summary['description'] ?? '—' }}
                    </td>
                    <td class="px-5 py-3 text-center">
                        @if($summary['role']->name === \App\Domain\Shared\Enums\Role::SUPER_ADMIN->value)
                            <span class="rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-700">
                                All (bypass)
                            </span>
                        @else
                            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                                {{ $summary['permission_count'] }}
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-3">
                        @if($summary['is_factory_scoped'])
                            <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                                Factory
                            </span>
                        @else
                            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500">
                                Global
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('admin.roles.show', $summary['role']) }}"
                           class="rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700
                                  hover:bg-indigo-100 transition-colors">
                            Edit Permissions
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-5 py-12 text-center text-sm text-gray-400">
                        No roles found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
