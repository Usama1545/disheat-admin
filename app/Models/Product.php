<?php

namespace App\Models;

use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductFilterEnum;
use App\Enums\Product\ProductImageFitEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Enums\SettingTypeEnum;
use App\Services\DeliveryZoneService;
use App\Services\SettingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Product extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;

    protected $table = 'products';
    protected $appends = ['estimated_delivery_time', 'favorite', 'main_image', 'additional_images'];

    protected $fillable = [
        'uuid',
        'category_id',
        'seller_id',
        'title',
        'slug',
        'base_prep_time',
        'main_image',
        'additional_images',
        'image_fit',
        'short_description',
        'description',
        'price',
        'compare_at_price',
        'cost_per_item',
        'tags',
        'custom_fields',
        'is_featured',
        'verification_status',
        'created_at',
        'updated_at',
        'store_id',
        'rejection_reason'
    ];

    protected $casts = [
        'additional_images' => 'array',
        'tags' => 'array',
        'custom_fields' => 'array',
        'base_prep_time' => 'integer',
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'cost_per_item' => 'decimal:2',
        'is_featured' => 'boolean',
    ];

    /**
     * Scope to eager-load category and its immediate parent to reduce queries
     * when accessing the hierarchy key. Deeper ancestors are supported and
     * will be resolved dynamically during traversal (no fixed depth limit).
     */
    public function scopeWithCategoryHierarchy(Builder $query): Builder
    {
        return $query->with(['category.parent']);
    }

    /**
     * Accessor to get the category hierarchy key for a product, composed from
     * the root category down to the product's category using category slugs.
     * Example: "electronics/mobiles/android".
     */
    public function getCategoryHierarchyKeyAttribute(): ?array
    {
        // Ensure base relation is available; deeper ancestors are traversed
        // dynamically to support unlimited parent levels.
        if (!$this->relationLoaded('category')) {
            $this->loadMissing('category');
        }

        $category = $this->category;
        if (!$category) {
            return null;
        }

        $result = [];
        $current = &$result;

        // Walk up the tree until there is no parent
        while ($category) {
            $current['id'] = $category->id;
            if ($category->parent) {
                $current['child'] = [];
                $current = &$current['child'];
            }
            $category = $category->parent;
        }

        return $result;
    }

    public function getEstimatedDeliveryTimeAttribute()
    {
        // If user coordinates or zone info are not available, return null
        if (!isset($this->user_latitude) || !isset($this->user_longitude) || !isset($this->zone_info)) {
            return null;
        }

        // Get base preparation time from product
        $basePrepTime = $this->base_prep_time ?? 0;

        // Get delivery time per km and buffer time from zone info
        $deliveryTimePerKm = $this->zone_info['delivery_time_per_km'] ?? 0;
        $bufferTime = $this->zone_info['buffer_time'] ?? 0;

        // For simple products without variants, find the nearest store directly
        $distance = null;
        
        // Find stores selling this product
        $storeProducts = StoreProduct::where('product_id', $this->id)
            ->with('store')
            ->get();
            
        foreach ($storeProducts as $storeProduct) {
            $store = $storeProduct->store;
            if ($store && isset($store->latitude) && isset($store->longitude)) {
                $distance = DeliveryZoneService::calculateDistance(
                    $this->user_latitude,
                    $this->user_longitude,
                    $store->latitude,
                    $store->longitude
                );
                // Use the first store found (closest would be better but requires sorting)
                if ($distance !== null) {
                    break;
                }
            }
        }

        // If no store found, return null
        if ($distance === null) {
            return null;
        }
        
        // Calculate estimated time (in minutes)
        $estimatedTime = $basePrepTime + ($distance * $deliveryTimePerKm) + $bufferTime;
        // Round to the nearest minute
        return ceil($estimatedTime);
    }

    /**
     * Scope: Apply product-specific filters such as featured, low_stock, out_of_stock.
     * Simplified for simple products (no variants).
     */
    public function scopeApplyProductFilter(Builder $query, ?string $filter): Builder
    {
        if (empty($filter) || !in_array($filter, ProductFilterEnum::values(), true)) {
            return $query;
        }

        try {
            // Stock based filters - simplified for simple products
            if (in_array($filter, [ProductFilterEnum::LOW_STOCK(), ProductFilterEnum::OUT_OF_STOCK()], true)) {
                $stockSub = DB::table('store_products')
                    ->select('product_id', DB::raw('COALESCE(SUM(stock), 0) as total_stock'))
                    ->groupBy('product_id');

                // Join aggregated stock to products and select base columns
                $query->leftJoinSub($stockSub, 'product_stock_totals', function ($join) {
                    $join->on('product_stock_totals.product_id', '=', 'simple_products.id');
                })
                    ->select('simple_products.*', DB::raw('COALESCE(product_stock_totals.total_stock, 0) as total_stock'));

                if ($filter === ProductFilterEnum::OUT_OF_STOCK()) {
                    $query->whereRaw('COALESCE(product_stock_totals.total_stock, 0) <= 0');
                } elseif ($filter === ProductFilterEnum::LOW_STOCK()) {
                    // Retrieve lowStockLimit from system settings
                    $lowStockLimit = 0;
                    try {
                        $settingService = app(SettingService::class);
                        $systemSettingsResource = $settingService->getSettingByVariable(SettingTypeEnum::SYSTEM());
                        $systemSettings = $systemSettingsResource?->toArray(request())['value'] ?? [];
                        $lowStockLimit = (int)($systemSettings['lowStockLimit'] ?? 0);
                    } catch (\Throwable $e) {
                        $lowStockLimit = 0;
                    }

                    if ($lowStockLimit > 0) {
                        $query->whereRaw('COALESCE(product_stock_totals.total_stock, 0) > 0')
                            ->whereRaw('COALESCE(product_stock_totals.total_stock, 0) <= ?', [$lowStockLimit]);
                    } else {
                        // Guardrail: if not configured, return none for low stock
                        $query->whereRaw('1 = 0');
                    }
                }
            } elseif ($filter === ProductFilterEnum::FEATURED()) {
                $query->where('is_featured', true);
            }
        } catch (\Throwable $e) {
            // Ignore filter errors to avoid breaking listing endpoints
        }

        return $query;
    }

    public function getFavoriteAttribute(): ?array
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            return null; // User isn't authenticated
        }

        $wishlistItems = WishlistItem::with(['wishlist', 'store'])
            ->whereHas('wishlist', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('product_id', $this->id)->get();

        if ($wishlistItems->isEmpty()) {
            return null;
        }
        
        $items = [];
        foreach ($wishlistItems as $item) {
            $items[] = [
                'id' => $item->id,
                'wishlist_id' => $item->wishlist_id,
                'wishlist_title' => $item->wishlist->title,
                'store_id' => $item->store?->id,
                'store_name' => $item->store?->name,
            ];
        }
        return $items;
    }

    public function getMainImageAttribute(): ?string
    {
        $image = $this->attributes['main_image'] ?? null;

        return $image
            ? asset('storage/' . $image)
            : asset('assets/images/product-placeholder.jpg');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
    
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'product_id');
    }

    public function getAdditionalImagesAttribute(): ?array
    {
        return $this->additional_images ?? [];
    }

    public function getItemCountInCartAttribute()
    {
        return CartItem::where('product_id', $this->id)
            ->sum('quantity');
    }

    public function setTitleAttribute($value): void
    {
        $this->attributes['title'] = $value;
        $this->attributes['slug'] = generateUniqueSlug(model: self::class, title: $value, id: $this->id ?? null);
        if (empty($this->id)) {
            $this->attributes['uuid'] = (string)Str::uuid();
        }
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(ProductFaq::class, 'product_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'product_id');
    }

    public function taxClasses(): BelongsToMany
    {
        return $this->belongsToMany(TaxClass::class, 'product_taxes')->with('taxRates');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Get the stores that sell this product
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_products')
            ->withPivot('stock', 'price', 'special_price', 'sku')
            ->withTimestamps();
    }

    /**
     * Get the store product records for this product
     */
    public function storeProducts(): HasMany
    {
        return $this->hasMany(StoreProduct::class, 'product_id');
    }

    public function customProductSections(): HasMany
    {
        return $this->hasMany(CustomProductSection::class)->orderBy('sort_order');
    }

    public function scopeApplySorting(Builder $query, ?string $sort, array $storeIds = []): Builder
    {
        // If a sort option is explicitly provided, clear any previous ORDER BY clauses
        // so the requested sort isn't overridden by existing section-type ordering.
        if (!is_null($sort) && $sort !== '') {
            $query->reorder();
        }
        
        switch ($sort) {
            case 'price_asc':
                if (empty($storeIds)) {
                    $query->orderBy('id', 'desc');
                    break;
                }
                $priceSub = DB::table('store_products')
                    ->whereIn('store_id', $storeIds)
                    ->select('product_id', DB::raw('MIN(COALESCE(special_price, price)) as min_price'))
                    ->groupBy('product_id');

                $query->joinSub($priceSub, 'sp_prices', fn($join) => $join->on('sp_prices.product_id', '=', 'simple_products.id'))
                    ->orderBy('sp_prices.min_price', 'asc')
                    ->select('simple_products.*');
                break;

            case 'price_desc':
                if (empty($storeIds)) {
                    $query->orderBy('id', 'desc');
                    break;
                }
                $priceSub = DB::table('store_products')
                    ->whereIn('store_id', $storeIds)
                    ->select('product_id', DB::raw('MAX(COALESCE(special_price, price)) as max_price'))
                    ->groupBy('product_id');

                $query->joinSub($priceSub, 'sp_prices', fn($join) => $join->on('sp_prices.product_id', '=', 'simple_products.id'))
                    ->orderBy('sp_prices.max_price', 'desc')
                    ->select('simple_products.*');
                break;

            case 'avg_rated':
                $query->withAvg('reviews', 'rating')
                    ->orderBy('reviews_avg_rating', 'desc');
                break;

            case 'best_seller':
                $query->withCount(['orderItems' => fn($q) => $q->where('order_items.status', OrderItemStatusEnum::DELIVERED())])
                    ->orderBy('order_items_count', 'desc');
                break;

            case 'featured':
                $query->where('is_featured', true)->orderBy('id', 'desc');
                break;

            case 'relevance':
            default:
                $query->orderBy('id', 'desc');
                break;
        }

        return $query;
    }

    public static function getStoreIdsInZone(array $zoneInfo, ?string $storeSlug = null): array
    {
        if (!$zoneInfo['exists']) {
            return [];
        }

        $storeQuery = Store::whereHas('zones', function ($q) use ($zoneInfo) {
            $q->where('delivery_zones.id', $zoneInfo['zone_id']);
        })
            ->where('verification_status', StoreVerificationStatusEnum::APPROVED())
            ->where('visibility_status', StoreVisibilityStatusEnum::VISIBLE());

        if ($storeSlug) {
            $store = (clone $storeQuery)->where('slug', $storeSlug)->first();
            return $store ? [$store->id] : [];
        }

        return $storeQuery->pluck('id')->toArray();
    }

    /**
     * Get all child category IDs recursively
     */
    private static function getAllChildCategoryIds(int $categoryId): array
    {
        $childIds = [];
        $children = Category::where('parent_id', $categoryId)->pluck('id')->toArray();

        foreach ($children as $childId) {
            $childIds[] = $childId;
            $grandChildIds = self::getAllChildCategoryIds($childId);
            $childIds = array_merge($childIds, $grandChildIds);
        }

        return $childIds;
    }

    /**
     * Scope to get products by location
     */
    public static function scopeByLocation($zoneInfo, $query, $filter = []): Builder
    {
        $storeIds = self::getStoreIdsInZone($zoneInfo, $filter['store'] ?? null);
        if (empty($storeIds)) {
            return $query->whereRaw('1 = 0');
        }

        if (!empty($filter['categories'])) {
            $categoryIds = Category::whereIn('slug', $filter['categories'])->pluck('id')->toArray();

            // If include_child_categories is enabled, also get child category IDs
            if (!empty($filter['include_child_categories'])) {
                $allCategoryIds = $categoryIds;
                foreach ($categoryIds as $categoryId) {
                    $childIds = self::getAllChildCategoryIds($categoryId);
                    $allCategoryIds = array_merge($allCategoryIds, $childIds);
                }
                $categoryIds = array_unique($allCategoryIds);
            }

            $query->whereIn('category_id', $categoryIds);
        }
        
        if (!empty($filter['brands'])) {
            $brandIds = Brand::whereIn('slug', $filter['brands'])->pluck('id')->toArray();
            $query->whereIn('brand_id', $brandIds);
        }
        
        if (!empty($filter['exclude_product'])) {
            // Support excluding a single slug or multiple slugs
            $exclude = $filter['exclude_product'];
            if (is_array($exclude)) {
                $query->whereNotIn('slug', $exclude);
            } else {
                $query->whereNot('slug', $exclude);
            }
        }
        
        if (!empty($filter['search'])) {
            $searchTerm = $filter['search'];

            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('short_description', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('tags', 'LIKE', "%{$searchTerm}%")
                    ->orWhereHas('category', function ($categoryQuery) use ($searchTerm) {
                        $categoryQuery->where('title', 'LIKE', "%{$searchTerm}%");
                    })
                    ->orWhereHas('brand', function ($brandsQuery) use ($searchTerm) {
                        $brandsQuery->where('title', 'LIKE', "%{$searchTerm}%");
                    });
            });
        }

        $query->where('verification_status', ProductVarificationStatusEnum::APPROVED());
        $query->where('status', ProductStatusEnum::ACTIVE());

        $query->applySorting($filter['sort'] ?? null, $storeIds);

        return $query->with([
            'storeProducts' => function ($q) use ($storeIds) {
                $q->whereIn('store_id', $storeIds)->with('store');
            },
        ])->whereHas('storeProducts', function ($q) use ($storeIds) {
            $q->whereIn('store_id', $storeIds);
        });
    }

    /**
     * Get the brand associated with the product.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the categories associated with the product (for many-to-many if needed).
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product')
            ->withTimestamps();
    }

    /**
     * Get products by location with pagination
     */
    public static function getProductsByLocation(float $latitude, float $longitude, int $perPage = 15, array $filter = []): LengthAwarePaginator
    {
        // Get zones at the given coordinates
        $zoneInfo = DeliveryZoneService::getZonesAtPoint($latitude, $longitude);

        // Build a base query that excludes category/brand slug filters
        // so we can compute unique IDs from the unfiltered result set.
        $baseFilter = $filter;
        unset($baseFilter['categories'], $baseFilter['brands']);

        $baseQuery = self::scopeByLocation(zoneInfo: $zoneInfo, query: self::query(), filter: $baseFilter);

        // Collect unique category and brand IDs, limit to 50
        $categoryIds = (clone $baseQuery)
            ->distinct()
            ->pluck('category_id')
            ->filter()
            ->unique()
            ->take(50)
            ->values()
            ->toArray();

        $filteredQuery = self::scopeByLocation(
            zoneInfo: $zoneInfo,
            query: self::query(),
            filter: $filter
        );

        $brandIds = (clone $filteredQuery)
            ->distinct()
            ->pluck('brand_id')
            ->filter()
            ->unique()
            ->take(50)
            ->values()
            ->toArray();

        $products = $filteredQuery->with(['addons', 'addons.options'])
            ->orderBy('title')
            ->paginate($perPage);

        // Store the user's latitude and longitude in each product for delivery time calculation
        foreach ($products as $product) {
            $product->user_latitude = $latitude;
            $product->user_longitude = $longitude;
            $product->zone_info = $zoneInfo;
        }
        
        $relatedKeywords = [];
        if (!empty($filter['search'])) {
            $searchTerm = $filter['search'];
            $relatedKeywords = self::query()
                ->where('tags', 'LIKE', "%{$searchTerm}%")
                ->select('tags')
                ->limit(20)
                ->pluck('tags')
                ->flatMap(function ($tags) {
                    // handle if tags is JSON, array, or string
                    if (is_array($tags)) {
                        return $tags;
                    }

                    if (is_null($tags)) {
                        return [];
                    }

                    // handle JSON stored tags
                    if (is_string($tags) && str_starts_with(trim($tags), '[')) {
                        $decoded = json_decode($tags, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            return $decoded;
                        }
                    }

                    // fallback: comma-separated string
                    return array_map('trim', explode(',', $tags));
                })
                ->filter(function ($tag) use ($searchTerm) {
                    return is_string($tag) && stripos($tag, $searchTerm) !== false;
                })
                ->unique()
                ->take(10)
                ->values()
                ->toArray();
        }
        
        // Attach supplemental data to paginator
        $products->related_keywords = $relatedKeywords;
        $products->category_ids = $categoryIds;
        $products->brand_ids = $brandIds;
        
        return $products;
    }

    public static function getProductByLocation(float $latitude, float $longitude, $id): ?Model
    {
        // Get zones at the given coordinates
        $zoneInfo = DeliveryZoneService::getZonesAtPoint($latitude, $longitude);
        
        $product = self::scopeByLocation(zoneInfo: $zoneInfo, query: self::query()->with([
                'storeProducts.store',
                'customProductSections.fields',
                'category',
                'brand',
                'reviews',
                'faqs',
                'addons.options'
            ]))
            ->where('id', $id)
            ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
            ->first();
            
        if (!empty($product)) {
            $product->user_latitude = $latitude;
            $product->user_longitude = $longitude;
            $product->zone_info = $zoneInfo;
        }
        
        return $product;
    }

    protected static function booted(): void
    {
        static::deleting(function ($product) {
            // Delete store product records related to the product
            $product->storeProducts()->delete();
        });
        
        static::forceDeleted(function ($product) {
            $product->clearMediaCollection();
        });
    }

    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class, 'product_addons')->withTimestamps();
    }
}