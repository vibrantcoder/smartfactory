<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\User;

use App\Domain\Auth\Services\PermissionService;
use App\Domain\Shared\Enums\Role as RoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * AssignRoleRequest
 *
 * Validates a role assignment request.
 *
 * SECURITY — Dynamic rule based on assigner's level:
 *   The 'in' rule is built from the roles the authenticated user
 *   is ALLOWED to assign (via PermissionService::getAssignableRolesFor).
 *   This means the validation layer itself enforces privilege escalation prevention.
 *
 * INPUT:
 *   POST /admin/users/{user}/assign-role
 *   { "role": "supervisor" }
 */
class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Must have the assign-role.user permission (set by factory.scope middleware)
        return $this->user()?->can('assign-role.user') ?? false;
    }

    public function rules(): array
    {
        // Build assignable role list dynamically — prevents privilege escalation at validation
        $permissionService  = app(PermissionService::class);
        $assignableRoles    = $permissionService->getAssignableRolesFor($this->user());
        $assignableRoleNames = array_map(fn(RoleEnum $r) => $r->value, $assignableRoles);

        return [
            'role' => [
                'required',
                'string',
                Rule::in($assignableRoleNames),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'A role must be specified.',
            'role.in'       => 'The selected role is invalid or you do not have sufficient privilege to assign it.',
        ];
    }
}
