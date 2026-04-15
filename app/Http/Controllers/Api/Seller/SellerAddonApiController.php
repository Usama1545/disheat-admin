<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Addon\StoreAddonRequest;
use App\Http\Requests\Addon\UpdateAddonRequest;
use App\Models\Addon;
use App\Types\Api\ApiResponseType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SellerAddonApiController extends Controller
{
    use AuthorizesRequests;

    /**
     * List addons
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Addon::class);

        $seller = auth()->user()?->seller();

        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
        }

        $perPage = (int) $request->input('per_page', 15);
        $q = $request->input('search');

        $query = Addon::where('seller_id', $seller->id)
            ->withCount('options');

        if ($q) {
            $query->where('name', 'like', "%$q%");
        }

        $paginator = $query->latest()->paginate($perPage);

        return ApiResponseType::sendJsonResponse(true, 'Addons fetched successfully', [
            'current_page' => $paginator->currentPage(),
            'last_page'   => $paginator->lastPage(),
            'per_page'    => $paginator->perPage(),
            'total'       => $paginator->total(),
            'data'        => $paginator->items(),
        ]);
    }

    /**
     * Show single addon
     */
    public function show($id): JsonResponse
    {
        try {
            $this->authorize('viewAny', Addon::class);

            $seller = auth()->user()?->seller();

            $addon = Addon::where('seller_id', $seller->id)
                ->with('options')
                ->findOrFail($id);

            return ApiResponseType::sendJsonResponse(true, 'Addon fetched successfully', $addon);

        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, 'Addon not found', null, 404);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'Unauthorized', [], 403);
        }
    }

    /**
     * Store addon + options
     */
    public function store(StoreAddonRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Addon::class);

            $seller = auth()->user()?->seller();

            DB::beginTransaction();

            $validated = $request->validated();
            $validated['seller_id'] = $seller->id;

            $addon = Addon::create($validated);

            // create options
            if (!empty($validated['options'])) {
                $addon->options()->createMany($validated['options']);
            }

            DB::commit();

            return ApiResponseType::sendJsonResponse(true, 'Addon created successfully', $addon, 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponseType::sendJsonResponse(false, 'Failed to create addon', [
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update addon + options
     */
    public function update(StoreAddonRequest $request, $id): JsonResponse
    {
        try {
            $seller = auth()->user()?->seller();

            $addon = Addon::where('seller_id', $seller->id)->findOrFail($id);

            $this->authorize('update', $addon);

            DB::beginTransaction();

            $validated = $request->validated();

            $addon->update($validated);

            // replace options (simple approach)
            if (isset($validated['options'])) {
                $addon->options()->delete();
                $addon->options()->createMany($validated['options']);
            }

            DB::commit();

            return ApiResponseType::sendJsonResponse(true, 'Addon updated successfully', $addon);

        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, 'Addon not found', null, 404);
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponseType::sendJsonResponse(false, 'Failed to update addon', [
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete addon
     */
    public function destroy($id): JsonResponse
    {
        try {
            $seller = auth()->user()?->seller();

            $addon = Addon::where('seller_id', $seller->id)->findOrFail($id);

            $this->authorize('delete', $addon);

            DB::beginTransaction();

            $addon->options()->delete();
            $addon->delete();

            DB::commit();

            return ApiResponseType::sendJsonResponse(true, 'Addon deleted successfully');

        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, 'Addon not found', null, 404);
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponseType::sendJsonResponse(false, 'Failed to delete addon', [
                'error' => $e->getMessage()
            ], 500);
        }
    }
}