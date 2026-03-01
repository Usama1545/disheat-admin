<?php

namespace App\Http\Controllers\Api;

use App\Enums\BrandStatusEnum;
use App\Enums\HomePageScopeEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\DeliveryZoneService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
#[Group('Brands')]
class BrandApiController extends Controller
{
    /**
     * Get brands with pagination and only active status.
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of brands per page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('scope_category_slug', description: 'If you pass a category slug then brands will be filtered by category scope', type: 'string', example: "dairy")]
    #[QueryParameter('latitude', description: 'Latitude of the user location for zone-wise product counts', type: 'float', example: 23.11684540)]
    #[QueryParameter('longitude', description: 'Longitude of the user location for zone-wise product counts', type: 'float', example: 70.02805670)]

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $query = Brand::where('status', BrandStatusEnum::ACTIVE());
        // Apply scope filter
        if ($request->has('scope_category_slug')) {
            $categorySlug = $request->input('scope_category_slug');
            $category = Category::where('slug', $categorySlug)->first();
            $query = Brand::scopeByCategory($query, $category->id ?? null);
        } else {
            $query->where('scope_type', HomePageScopeEnum::GLOBAL());
        }
        // Zone-aware product counting
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $useZoneFilter = !is_null($latitude) && !is_null($longitude);
        if ($useZoneFilter) {
            $zoneInfo = DeliveryZoneService::getZonesAtPoint((float)$latitude, (float)$longitude);
            $storeIds = Product::getStoreIdsInZone($zoneInfo);
            $query->withCount([
                'products as products_count' => function ($q) use ($storeIds) {
                    if (empty($storeIds)) {
                        $q->whereRaw('1 = 0');
                        return;
                    }
                    $q->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                        ->where('status', ProductStatusEnum::ACTIVE())
                        ->whereHas('variants.storeProductVariants', function ($sq) use ($storeIds) {
                            $sq->whereIn('store_id', $storeIds);
                        });
                }
            ]);
        } else {
            $query->withCount('products');
        }

        // Exclude brands with zero available products
        $query->having('products_count', '>', 0);

        $brands = $query->orderBy('title')
            ->paginate($perPage);
        $brands->getCollection()->transform(fn($brand) => new BrandResource($brand));
        $response = [
            'current_page' => $brands->currentPage(),
            'last_page' => $brands->lastPage(),
            'per_page' => $brands->perPage(),
            'total' => $brands->total(),
            'data' => $brands->items(),
        ];
        return ApiResponseType::sendJsonResponse(true, 'labels.brand_fetched_successfully', $response);
    }
}

