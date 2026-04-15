<?php

namespace App\Services;

use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Enums\Product\ProductVideoTypeEnum;
use App\Events\Product\ProductStatusAfterUpdate;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoreProduct;
use App\Enums\SpatieMediaCollectionName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductService
{
    /**
     * Store a simple product (no variants)
     */
    public function storeProduct(array $validated, $request): array
    {
        DB::beginTransaction();
        try {
            $product = $this->createProduct($validated);
            
            // Sync tax classes
            if (!empty($validated['tax_groups'])) {
                $product->taxClasses()->sync($validated['tax_groups']);
            }
            
            // Handle addons
            if (!empty($validated['addon_ids'])) {
                $addonIds = is_array($validated['addon_ids']) 
                    ? $validated['addon_ids'] 
                    : explode(',', $validated['addon_ids']);
                $product->addons()->sync($addonIds);
            }
            
            // Handle media uploads
            $this->handleMediaUploads($product, $request);
            
            // Handle store pricing (simple, no variants, no stock)
            if (!empty($validated['store_id']) && !empty($validated['price'])) {
                $this->saveStoreProduct($product, $validated);
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'product' => $product->load(['storeProducts', 'addons', 'taxClasses', 'category', 'brand']),
                'message' => 'Product created successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product creation failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update a simple product
     */
    public function updateProduct(Product $product, array $validated, $request): array
    {
        DB::beginTransaction();
        try {
            $this->updateProductDetails($product, $validated);
            
            // Sync tax classes
            if (!empty($validated['tax_groups'])) {
                $product->taxClasses()->sync($validated['tax_groups']);
            }
            
            // Handle addons
            if (isset($validated['addon_ids'])) {
                $addonIds = is_array($validated['addon_ids']) 
                    ? $validated['addon_ids'] 
                    : explode(',', $validated['addon_ids']);
                $product->addons()->sync($addonIds);
            }
            
            // Handle media uploads
            $this->handleMediaUploads($product, $request);
            
            // // Update store pricing
            if (!empty($validated['store_id']) && !empty($validated['price'])) {
                
                $this->updateStoreProduct($product, $validated);
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'product' => $product->load(['storeProducts', 'addons', 'taxClasses', 'category', 'brand']),
                'message' => 'Product updated successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product update failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create base product
     */
    private function createProduct(array $validated): Product
    {
        $productData = [
            'seller_id' => auth()->user()->id ?? null,
            'category_id' => $validated['category_id'],
            'store_id' => $validated['store_id'] ?? null,
            'brand_id' => $validated['brand_id'] ?? null,
            'title' => $validated['title'],
            'base_prep_time' => $validated['base_prep_time'] ?? 0,
            'short_description' => $validated['short_description'] ?? '',
            'description' => $validated['description'] ?? '',
            'image_fit' => $validated['image_fit'] ?? 'cover',
            'is_featured' => $validated['is_featured'] ?? false,
            'price' => $validated['price'] ?? 0,
            'compare_at_price' => $validated['compare_at_price'] ?? null,
            'tags' => isset($validated['tags']) ? (is_array($validated['tags']) ? $validated['tags'] : json_decode($validated['tags'], true)) : null,
            'custom_fields' => $validated['custom_fields'] ?? null,
            'verification_status' => ProductVarificationStatusEnum::PENDING(),
        ];
        
        // Add pricing fields if provided (these will be overridden by store-specific pricing)
        if (isset($validated['price'])) {
            $productData['price'] = $validated['price'];
        }
        if (isset($validated['compare_at_price'])) {
            $productData['compare_at_price'] = $validated['compare_at_price'];
        }
        
        $product = Product::create($productData);
        
        // Set status based on category approval requirement
        $category = Category::find($validated['category_id']);
        if ($category && $category->requires_approval) {
            $product->verification_status = ProductVarificationStatusEnum::PENDING();
        } else {
            $product->verification_status = ProductVarificationStatusEnum::APPROVED();
        }
        $product->save();
        
        event(new ProductStatusAfterUpdate($product));
        
        return $product;
    }
    
    /**
     * Update product details
     */
    private function updateProductDetails(Product $product, array $validated): void
    {
        $updateData = [
            'category_id' => $validated['category_id'],
            'brand_id' => $validated['brand_id'] ?? null,
            'store_id' => $validated['store_id'] ?? $product->store_id,
            'title' => $validated['title'],
            'base_prep_time' => $validated['base_prep_time'] ?? 0,
            'short_description' => $validated['short_description'] ?? '',
            'description' => $validated['description'] ?? '',
            'image_fit' => $validated['image_fit'] ?? $product->image_fit,
            'is_featured' => $validated['is_featured'] ?? $product->is_featured,
            'tags' => isset($validated['tags']) ? (is_array($validated['tags']) ? $validated['tags'] : json_decode($validated['tags'], true)) : $product->tags,
            'custom_fields' => $validated['custom_fields'] ?? $product->custom_fields,
            'price' => $validated['price'] ?? $product->price,
            'compare_at_price' => $validated['compare_at_price'] ?? $product->compare
        ];
        
        // Update pricing fields if provided
        if (isset($validated['price'])) {
            $updateData['price'] = $validated['price'];
        }
        if (isset($validated['compare_at_price'])) {
            $updateData['compare_at_price'] = $validated['compare_at_price'];
        }

        
        $product->update($updateData);
        
        // Update status based on category
        $category = Category::find($validated['category_id']);
        if ($category && $category->requires_approval) {
            $product->verification_status = ProductVarificationStatusEnum::PENDING();
        } else {
            $product->verification_status = ProductVarificationStatusEnum::APPROVED();
        }
        $product->save();
        
        event(new ProductStatusAfterUpdate($product));
    }
    
    /**
     * Save store product pricing (no stock)
     */
    private function saveStoreProduct(Product $product, array $validated): void
    {
        StoreProduct::updateOrCreate(
            [
                'product_id' => $product->id,
                'store_id' => $validated['store_id']
            ],
            [
                'cost_per_item' => $validated['price'],
                'compare_at_price' => $validated['compare_at_price'] ?? null,
            ]
        );
    }
    
    /**
     * Update store product pricing
     */
    private function updateStoreProduct(Product $product, array $validated): void
    {
        if (!empty($validated['store_id'])) {
            StoreProduct::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'store_id' => $validated['store_id']
                ],
                [
                    'cost_per_item' => $validated['price'],
                    'compare_at_price' => $validated['compare_at_price'] ?? null,
                ]
            );
        }
    }
    
    /**
     * Handle media uploads
     */
    private function handleMediaUploads($product, $request): void
    {
        // Handle main image - store directly in product model
        if ($request->hasFile('main_image')) {
            $mainImagePath = $request->file('main_image')->store('products/main', 'public');
            $product->update(['main_image' => $mainImagePath]);
        }
        
        // Handle additional images - store as JSON in product model
        if ($request->hasFile('additional_images')) {
            $additionalImagePaths = [];
            foreach ($request->file('additional_images') as $image) {
                $path = $image->store('products/additional', 'public');
                $additionalImagePaths[] = $path;
            }
            $product->update(['additional_images' => $additionalImagePaths]);
        }
        
        // Handle video if using Spatie media library
        if ($request->hasFile('product_video') && $request->video_type === ProductVideoTypeEnum::LOCAL()->value) {
            $product->clearMediaCollection(SpatieMediaCollectionName::PRODUCT_VIDEO());
            $product->addMedia($request->file('product_video'))
                ->toMediaCollection(SpatieMediaCollectionName::PRODUCT_VIDEO());
        }
    }
    
    /**
     * Delete a product and its related data
     */
    public function deleteProduct(Product $product): array
    {
        DB::beginTransaction();
        try {
            // Delete store product records
            $product->storeProducts()->delete();
            
            // Detach tax classes
            $product->taxClasses()->detach();
            
            // Detach addons
            $product->addons()->detach();
            
            // Delete the product (soft delete)
            $product->delete();
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Product deleted successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product deletion failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Force delete a product permanently
     */
    public function forceDeleteProduct(Product $product): array
    {
        DB::beginTransaction();
        try {
            // Delete store product records
            $product->storeProducts()->forceDelete();
            
            // Detach tax classes
            $product->taxClasses()->detach();
            
            // Detach addons
            $product->addons()->detach();
            
            // Clear media collections
            $product->clearMediaCollection();
            
            // Delete the product permanently
            $product->forceDelete();
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Product permanently deleted successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product force deletion failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Bulk update product prices for multiple stores
     */
    public function bulkUpdateStorePrices(int $productId, array $storePrices): array
    {
        DB::beginTransaction();
        try {
            $updated = 0;
            foreach ($storePrices as $storePrice) {
                $updated += StoreProduct::updateOrCreate(
                    [
                        'product_id' => $productId,
                        'store_id' => $storePrice['store_id']
                    ],
                    [
                        'cost_per_item' => $storePrice['price'],
                        'compare_at_price' => $storePrice['compare_at_price'] ?? null,
                    ]
                ) ? 1 : 0;
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => "{$updated} store prices updated successfully"
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk price update failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get product with all relations
     */
    public static function getProductWithDetails(int $productId)
    {
        return Product::with([
            'storeProducts' => function ($query) {
                $query->with('store');
            },
            'addons',
            'taxClasses.taxRates',
            'category',
            'brand',
            'reviews',
            'faqs'
        ])->find($productId);
    }
    
    /**
     * Get product by slug with store-specific pricing
     */
    public static function getProductBySlug(string $slug, ?int $storeId = null)
    {
        $query = Product::with([
            'storeProducts' => function ($query) use ($storeId) {
                if ($storeId) {
                    $query->where('store_id', $storeId);
                }
                $query->with('store');
            },
            'addons',
            'taxClasses.taxRates',
            'category',
            'brand',
            'reviews.user',
            'faqs'
        ]);
        
        return $query->where('slug', $slug)->first();
    }
    
    /**
     * Get products by store with pagination
     */
    public static function getStoreProducts(int $storeId, int $perPage = 15)
    {
        return StoreProduct::with(['product' => function ($query) {
            $query->with(['category', 'brand']);
        }])
        ->where('store_id', $storeId)
        ->paginate($perPage);
    }
    
    /**
     * Update product verification status
     */
    public function updateVerificationStatus(Product $product, string $status, ?string $rejectionReason = null): array
    {
        try {
            $product->verification_status = $status;
            if ($rejectionReason) {
                $product->rejection_reason = $rejectionReason;
            }
            $product->save();
            
            event(new ProductStatusAfterUpdate($product));
            
            return [
                'success' => true,
                'message' => "Product verification status updated to {$status}",
                'product' => $product
            ];
        } catch (\Exception $e) {
            Log::error('Verification status update failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Clone a product
     */
    public function cloneProduct(Product $originalProduct, ?int $newSellerId = null): array
    {
        DB::beginTransaction();
        try {
            // Clone the product
            $newProduct = $originalProduct->replicate();
            $newProduct->title = $originalProduct->title . ' (Copy)';
            $newProduct->slug = null; // Will be auto-generated
            $newProduct->verification_status = ProductVarificationStatusEnum::PENDING();
            
            if ($newSellerId) {
                $newProduct->seller_id = $newSellerId;
            }
            
            $newProduct->save();
            
            // Clone store products (without stock)
            foreach ($originalProduct->storeProducts as $storeProduct) {
                $newStoreProduct = $storeProduct->replicate();
                $newStoreProduct->product_id = $newProduct->id;
                $newStoreProduct->save();
            }
            
            // Clone tax classes
            $newProduct->taxClasses()->sync($originalProduct->taxClasses->pluck('id')->toArray());
            
            // Clone addons
            $newProduct->addons()->sync($originalProduct->addons->pluck('id')->toArray());
            
            DB::commit();
            
            return [
                'success' => true,
                'product' => $newProduct->load(['storeProducts', 'taxClasses', 'addons']),
                'message' => 'Product cloned successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product cloning failed: ' . $e->getMessage());
            throw $e;
        }
    }
}