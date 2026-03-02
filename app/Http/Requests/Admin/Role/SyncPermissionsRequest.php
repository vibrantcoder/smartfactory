<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Role;

use App\Domain\Shared\Enums\Permission as PermissionEnum;
use App\Domain\Shared\Enums\Role as RoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SyncPermissionsRequest
 *
 * Validates the checkbox form submission for role permission management.
 *
 * EXPECTED INPUT:
 *   POST /admin/roles/{role}/permissions
 *   Content-Type: application/json
 *   {
 *     "permissions": [
 *       "view-any.machine",
 *       "view.machine",
 *       "create.downtime"
 *     ]
 *   }
 *
 * An empty permissions array is valid (removes all permissions from the role).
 * The absence of a permission name means it was unchecked.
 *
 * SECURITY:
 *   - Only Super Admin can submit this form (enforced in RoleController).
 *   - Each permission name is validated against PermissionEnum values.
 *   - The super-admin role cannot have explicit permissions set (prevented in service).
 */
class SyncPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Secondary authorization: only super-admin can sync permissions
        // Primary authorization is in RoleController::authorize()
        return $this->user()?->hasRole(RoleEnum::SUPER_ADMIN->value) ?? false;
    }

    public function rules(): array
    {
        $validPermissions = array_column(PermissionEnum::cases(), 'value');

        return [
            'permissions'   => ['present', 'array'],
            'permissions.*' => [
                'string',
                Rule::in($validPermissions),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'permissions.present'  => 'The permissions field must be present (may be empty array).',
            'permissions.array'    => 'Permissions must be an array of permission name strings.',
            'permissions.*.in'     => 'One or more permission names are invalid. Use PermissionEnum values.',
        ];
    }

    /**
     * Ensure the array is deduplicated after validation.
     */
    protected function passedValidation(): void
    {
        $this->merge([
            'permissions' => array_values(array_unique($this->input('permissions', []))),
        ]);
    }
}
