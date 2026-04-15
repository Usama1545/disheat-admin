<?php
namespace App\Policies;

use App\Models\User;
use App\Models\Addon;
use App\Enums\SellerPermissionEnum;
use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Traits\ChecksPermissions;

class AddonPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        // Only the seller who owns the order can view it
        if ($user->seller() === null) {
            return $this->hasPermission(AdminPermissionEnum::ADDON_VIEW());
        }

        // Check role or permission
        if (
            $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
            $this->hasPermission(SellerPermissionEnum::ADDON_VIEW())
        ) {
            return true;
        }
        return false;
    }

    public function view(User $user, Addon $addon): bool
    {
        if ($user->seller() === null) {
            return $this->hasPermission(AdminPermissionEnum::ADDON_VIEW());
        }

        return $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
            $this->hasPermission(SellerPermissionEnum::ADDON_VIEW());
    }

    public function create(User $user): bool
    {
        if ($user->seller() === null) {
            return $this->hasPermission(AdminPermissionEnum::ADDON_CREATE());
        }

        return $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
            $this->hasPermission(SellerPermissionEnum::ADDON_CREATE());
    }

    public function update(User $user, Addon $addon): bool
    {
        if ($user->seller() === null) {
            return $this->hasPermission(AdminPermissionEnum::ADDON_EDIT());
        }

        return $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
            $this->hasPermission(SellerPermissionEnum::ADDON_EDIT());
    }

    public function delete(User $user, Addon $addon): bool
    {
        if ($user->seller() === null) {
            return $this->hasPermission(AdminPermissionEnum::ADDON_DELETE());
        }

        return $user->hasRole(DefaultSystemRolesEnum::SELLER()) ||
            $this->hasPermission(SellerPermissionEnum::ADDON_DELETE());
    }
}