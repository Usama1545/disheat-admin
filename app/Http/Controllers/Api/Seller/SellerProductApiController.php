<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreUpdateProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use App\Types\Api\ApiResponseType;
use App\Events\Product\ProductAfterCreate;
use App\Events\Product\ProductBeforeCreate;
use App\Http\Resources\Product\ProductListResource;
use App\Http\Resources\Product\ProductResource;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

#[Group('Seller Products')]
class SellerProductApiController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected ProductService $productService)
    {
    }

    /**
     * List products for the authenticated seller with pagination and search
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of products per page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('search', description: 'Search query for title/description', type: 'string', example: 'phone')]
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Product::class);

            $user = auth()->user();
            $seller = $user?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $perPage = (int) $request->input('per_page', 15);
            $q = $request->input('search');

            $query = Product::query()
                ->where('seller_id', $seller->id)
                ->with(['category', 'brand', 'variants.storeProductVariants.store'])
                ->orderByDesc('id');

            if ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('title', 'like', "%$q%")
                        ->orWhere('short_description', 'like', "%$q%")
                        ->orWhere('description', 'like', "%$q%")
                        ->orWhere('tags', 'like', "%$q%");
                });
            }

            $paginator = $query->paginate($perPage);
            // Transform collection using ProductListResource to follow product response standard
            $paginator->getCollection()->transform(fn ($product) => new ProductListResource($product));

            $response = [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'data' => $paginator->items(),
            ];

            return ApiResponseType::sendJsonResponse(true, __('labels.products_fetched_successfully'), $response);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\Throwable $e) {
            Log::error('Seller products index error: ' . $e->getMessage());
            return ApiResponseType::sendJsonResponse(false, __('labels.error_fetching_products'), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show single product owned by seller
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $seller = $user?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $product = Product::where('seller_id', $seller->id)
                ->with([
                    'category',
                    'brand',
                    'variants.attributes.attribute',
                    'variants.attributes.attributeValue',
                    'variants.storeProductVariants.store',
                ])
                ->findOrFail($id);

            $this->authorize('view', $product);

            $productResource = new ProductResource($product);
            return ApiResponseType::sendJsonResponse(true, __('labels.product_fetched_successfully'), $productResource);
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.product_not_found'), null, 404);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\Throwable $e) {
            Log::error('Seller product show error: ' . $e->getMessage());
            return ApiResponseType::sendJsonResponse(false, __('labels.error_fetching_product'), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create product for seller
     */
    public function store(StoreUpdateProductRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Product::class);
            $validated = $request->validated();

            $user = auth()->user();
            $seller = $user?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }
            $validated['seller_id'] = $seller->id;

            event(new ProductBeforeCreate());
            $result = $this->productService->storeProduct($validated, $request);
            event(new ProductAfterCreate($result['product']));

            return ApiResponseType::sendJsonResponse(true, __('labels.product_created_successfully'), [
                'product_id' => $result['product']->id,
                'product_uuid' => $result['product']->uuid,
            ], 201);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\Throwable $e) {
            Log::error('Seller product store error: ' . $e->getMessage());
            return ApiResponseType::sendJsonResponse(false, __('labels.failed_to_save_product'), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a product owned by seller
     */
    public function update(StoreUpdateProductRequest $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $seller = $user?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $product = Product::where('seller_id', $seller->id)->findOrFail($id);
            $this->authorize('update', $product);

            $validated = $request->validated();
            $validated['seller_id'] = $seller->id; // ensure ownership preserved

            $result = $this->productService->updateProduct($product, $validated, $request);

            return ApiResponseType::sendJsonResponse(true, __('labels.product_updated_successfully'), [
                'product_id' => $result['product']->id,
                'product_uuid' => $result['product']->uuid,
            ]);
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.product_not_found'), null, 404);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\Throwable $e) {
            Log::error('Seller product update error: ' . $e->getMessage());
            return ApiResponseType::sendJsonResponse(false, __('labels.failed_to_update_product'), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a product owned by seller
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $seller = $user?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $product = Product::where('seller_id', $seller->id)->findOrFail($id);
            $this->authorize('delete', $product);

            $product->delete();

            return ApiResponseType::sendJsonResponse(true, __('labels.product_deleted_successfully'), null);
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.product_not_found'), null, 404);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\Throwable $e) {
            Log::error('Seller product destroy error: ' . $e->getMessage());
            return ApiResponseType::sendJsonResponse(false, __('labels.failed_to_delete_product'), ['error' => $e->getMessage()], 500);
        }
    }
}
