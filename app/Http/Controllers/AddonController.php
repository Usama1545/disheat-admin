<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\SellerPermissionEnum;
use App\Http\Requests\Addon\StoreAddonRequest;
use App\Http\Requests\Addon\UpdateAddonRequest;
use App\Models\Addon;
use App\Traits\PanelAware;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use App\Enums\DefaultSystemRolesEnum;
use Illuminate\View\View;

class AddonController extends Controller
{
    use ChecksPermissions, PanelAware, AuthorizesRequests;

    /**
     * List addons
     */
    protected bool $editPermission = false;
    protected bool $deletePermission = false;
    protected bool $createPermission = false;
    public function __construct()
    {
        if ($this->getPanel() === 'admin') {
            $this->editPermission = $this->hasPermission(AdminPermissionEnum::ADDON_EDIT());
            $this->deletePermission = $this->hasPermission(AdminPermissionEnum::ADDON_DELETE());
            $this->createPermission = $this->hasPermission(AdminPermissionEnum::ADDON_CREATE());
        }elseif ($this->getPanel() === 'seller') {
            $this->editPermission = true;
            $this->deletePermission = true;
            $this->createPermission = true;
        }
    }
    public function index()
    {
        $this->authorize('viewAny', Addon::class);

            $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'name', 'name' => 'name', 'title' => __('labels.name')],
            ['data' => 'type', 'name' => 'type', 'title' => __('labels.type')],
            ['data' => 'is_required', 'name' => 'is_required', 'title' => __('labels.is_required')],
            ['data' => 'min_select', 'name' => 'min_select', 'title' => __('labels.min_select')],
            ['data' => 'max_select', 'name' => 'max_select', 'title' => __('labels.max_select')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        $editPermission = $this->editPermission;
        $createPermission = $this->createPermission;
        $deletePermission = $this->deletePermission;

        return view(
            $this->panelView('addons.index'),
            compact('columns', 'editPermission', 'createPermission', 'deletePermission')
        );
    }

    public function create(): View
    {
        $this->authorize('create', Addon::class);

        return view($this->panelView('addons.form'));
    }

    /**
     * Store addon (ADMIN + SELLER)
     */
    public function store(StoreAddonRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Addon::class);

            $validated = $request->validated();

            // Force seller_id
            $validated['seller_id'] = auth()->user()->seller()->id;

            $addon = Addon::create($validated);
            $addon->options()->createMany($validated['options'] ?? []);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'Addon created successfully',
                data: $addon,
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(false, 'Validation failed', $e->errors(), 422);
        }
    }

    public function edit($id)
    {
        try {
            $addon = Addon::findOrFail($id);

            $this->authorize('view', $addon);

            return view($this->panelView('addons.form'), compact('addon'));
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, 'Addon not found', [], 404);
        }
    }

    /**
     * Show addon
     */
    public function show($id): JsonResponse
    {
        try {
            $addon = Addon::findOrFail($id);

            $this->authorize('view', $addon);

            return ApiResponseType::sendJsonResponse(true, 'Addon fetched', $addon);
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, 'Addon not found', [], 404);
        }
    }

    /**
     * Update addon (ADMIN + OWNER SELLER)
     */
    public function update(StoreAddonRequest $request, $id): JsonResponse
    {
        try {
            $addon = Addon::findOrFail($id);

            $this->authorize('update', $addon);

            $validated = $request->validated();

            // Prevent seller_id override
            unset($validated['seller_id']);

            $addon->update($validated);
            if (isset($validated['options'])) {
                $addon->options()->delete();
                $addon->options()->createMany($validated['options']);
            }

            return ApiResponseType::sendJsonResponse(
                true,
                'Addon updated successfully',
                $addon
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, 'Addon not found', [], 404);
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(false, 'Validation failed', $e->errors(), 422);
        }
    }

    /**
     * Delete addon (ADMIN + OWNER SELLER)
     */
    public function destroy($id): JsonResponse
    {
        try {
            $addon = Addon::findOrFail($id);

            $this->authorize('delete', $addon);

            // Optional: prevent delete if attached to products
            if ($addon->products()->exists()) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    'Addon cannot be deleted because it is attached to products'
                );
            }

            $addon->delete();

            return ApiResponseType::sendJsonResponse(
                true,
                'Addon deleted successfully'
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, 'Addon not found', [], 404);
        }
    }

    public function getAddons(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Addon::class);

        $draw = $request->get('draw');
        $start = $request->get('start');
        $length = $request->get('length');
        $searchValue = $request->get('search')['value'] ?? '';

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'asc';

        $columns = ['id', 'name', 'type', 'is_required', 'min_select', 'max_select', 'created_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';

        $query = Addon::query();

        $totalRecords = Addon::count();
        $filteredRecords = $totalRecords;

        // Search
        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('name', 'like', "%{$searchValue}%")
                ->orWhere('type', 'like', "%{$searchValue}%");
            });

            $filteredRecords = $query->count();
        }

        $data = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($addon) {
                return [
                    'id' => $addon->id,
                    'name' => $addon->name,
                    'type' => ucfirst($addon->type),

                    'is_required' => '<span class="badge text-uppercase ' .
                        ($addon->is_required ? "bg-info-lt" : "bg-warning-lt") . '">' .
                        ($addon->is_required ? __('labels.required') : __('labels.optional')) .
                    '</span>',

                    'min_select' => $addon->min_select,
                    'max_select' => $addon->max_select,

                    'created_at' => optional($addon->created_at)->format('Y-m-d'),

                    'action' => view('partials.actions', [
                        'modelName' => 'addon',
                        'id' => $addon->id,
                        'title' => $addon->name,
                        'mode' => 'model_view',
                        'editPermission' => $this->editPermission,
                        'deletePermission' => $this->deletePermission
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

    /**
     * Search (for dropdowns)
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('search');
        $sellerId = auth()->user()->seller()->id;

        $addons = Addon::query()
            ->where('seller_id', $sellerId)
            ->when($query, function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })
            ->select('id', 'name')
            ->limit(20)
            ->get();

        $results = $addons->map(function ($addon) {
            $name =  $addon->name;

            return [
                'id' => $addon->id,
                'value' => $addon->id,
                'name' => $name,
            ];
        });

        return response()->json($results);
    }
}