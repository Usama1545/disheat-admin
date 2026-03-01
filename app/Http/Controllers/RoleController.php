<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Exceptions\SellerNotFoundException;
use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use ChecksPermissions, PanelAware, AuthorizesRequests;

    protected bool $editPermission = false;
    protected bool $deletePermission = false;
    protected bool $createPermission = false;

    public function __construct()
    {
        $enum = $this->getPanel() === 'seller' ? SellerPermissionEnum::class : AdminPermissionEnum::class;
        $user = auth()->user();
        $this->editPermission = $this->hasPermission($enum::ROLE_EDIT()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
        $this->deletePermission = $this->hasPermission($enum::ROLE_DELETE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
        $this->createPermission = $this->hasPermission($enum::ROLE_CREATE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
    }

    public function index(): View
    {
        $this->authorize('viewAny', Role::class);
        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'name', 'name' => 'name', 'title' => __('labels.name')],
            ['data' => 'guard_name', 'name' => 'guard_name', 'title' => __('labels.guard_name')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'permissions', 'name' => 'permissions', 'title' => __('labels.permissions')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;
        $createPermission = $this->createPermission;
        return view($this->panelView('roles.index'), compact('columns', 'editPermission', 'deletePermission', 'createPermission'));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Role::class);
            $validated = $request->validated();
            if (in_array($validated['name'], ['Super Admin', 'customer', 'seller'])) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.cannot_create_role_with_this_name',
                    data: [],
                    status: 422
                );
            }
            if ($this->getPanel() == 'seller') {
                $seller = $this->ensureSeller();
                $validated['guard_name'] = 'seller';
                $validated['team_id'] = $seller->id;
            } elseif ($this->getPanel() == 'admin') {
                $validated['guard_name'] = 'admin';
            } else {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.invalid_panel',
                    data: [],
                );
            }
            $role = Role::create($validated);

            return ApiResponseType::sendJsonResponse(success: true, message: 'labels.role_created_successfully', data: $role, status: 201);
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.validation_failed', data: $e->errors(), status: 422);
        } catch (SellerNotFoundException) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.seller_not_found', data: [], status: 404);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: []);
        }
    }

    public function edit($id): JsonResponse
    {
        try {
            $role = Role::findOrFail($id);
            $this->authorize('view', $role);
            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.role_retrieved_successfully',
                data: $role
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.role_not_found',
                data: []
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.permission_denied',
                data: [],
            );
        }
    }

    public function update(UpdateRoleRequest $request, $id): JsonResponse
    {
        try {
            $role = Role::findOrFail($id);
            $this->authorize('update', $role);

            if (in_array($role->name, ['Super Admin', 'customer', 'seller'])) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.cannot_modify_role',
                    data: [],
                    status: 422
                );
            }

            $validated = $request->validated();
            $role->update($validated);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.role_updated_successfully',
                data: $role
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.role_not_found',
                data: [],
                status: 404
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.validation_failed',
                data: $e->errors(),
                status: 422
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.permission_denied',
                data: [],
            );
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $role = Role::findOrFail($id);
            $this->authorize('delete', $role);
            if (in_array($role->name, ['Super Admin', 'customer', 'seller'])) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.cannot_delete_role',
                    data: [],
                    status: 422
                );
            }

            $role->permissions()->detach();
            $role->users()->detach();
            $role->delete();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.role_deleted_successfully',
                data: []
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.role_not_found',
                data: [],
                status: 404
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.permission_denied',
                data: [],
            );
        }
    }

    public function getRoles(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $draw = $request->get('draw');
        $start = $request->get('start');
        $length = $request->get('length');
        $searchValue = $request->get('search')['value'] ?? '';

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'asc';

        $columns = ['id', 'name', 'created_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';

        $query = Role::query();
        if ($this->getPanel() == 'seller') {
            $user = auth()->user();
            $seller = $user->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', [], 404);
            }
            $query->where('guard_name', 'seller')->where('team_id', $seller->id);
        } else {
            $query->where('guard_name', 'admin');
        }
        $query->whereNotIn('name', [DefaultSystemRolesEnum::SUPER_ADMIN(), DefaultSystemRolesEnum::CUSTOMER(), DefaultSystemRolesEnum::SELLER()]);
        $totalRecords = $query->count();
        $filteredRecords = $totalRecords;

        // Search filter
        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('name', 'like', "%{$searchValue}%");
            });
            $filteredRecords = $query->count();
        }


        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;

        $data = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($role) use ($editPermission, $deletePermission) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'created_at' => $role->created_at->format('Y-m-d'),
                    'permissions' => view($this->panelView('.roles.partials.permissions'), ['role' => $role])->render(),
                    'action' => view('partials.actions', [
                        'modelName' => 'role',
                        'id' => $role->id,
                        'title' => $role->name,
                        'mode' => 'model_view',
                        'editPermission' => $editPermission,
                        'deletePermission' => $deletePermission
                    ])->render(),
                ];
            })
            ->toArray();

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }
}
