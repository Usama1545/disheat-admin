<?php

namespace App\Services;

use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductTypeEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Enums\Product\ProductVideoTypeEnum;
use App\Events\Product\ProductAfterUpdate;
use App\Events\Product\ProductStatusAfterUpdate;
use App\Http\Resources\User\ReviewResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\Review;
use App\Models\StoreProductVariant;
use App\Enums\SpatieMediaCollectionName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{
    public static function getProductWithVariants(int $productId)
    {
        return Product::with(['variants.attributes', 'variants.storeProductVariants', 'taxClasses'])->find($productId);
    }

    public function updateProduct(Product $product, array $validated, $request): array
    {
        return $this->processProduct($product, $validated, $request, 'update');
    }

    public function storeProduct(array $validated, $request): array
    {
        return $this->processProduct(null, $validated, $request, 'create');
    }

    /**
     * @throws \Exception
     */
    private function processProduct(?Product $product, array $validated, $request, string $mode): array
    {
        DB::beginTransaction();
        try {
            if ($mode === 'create') {
                $product = $this->createProduct($validated);
            } else {
                $this->updateProductDetails($product, $validated);
            }
            if (!empty($validated['tax_groups']) && is_array($validated['tax_groups'])) {
                $product->taxClasses()->sync($validated['tax_groups']);
            }
            $pricingData = json_decode($validated['pricing'], true);
            // Decide based on incoming request, so we can switch type on update as well
            $incomingIsVariant = ($validated['type'] ?? $product->type) === 'variant' && isset($validated['variants_json']);
            $isVariant = $incomingIsVariant;

            if ($isVariant) {
                $this->processVariantProduct($product, $validated, $pricingData, $mode, $request);
            } else {
                // If switching from variant -> simple during update, clean up old variants first
                if ($mode === 'update' && $product->type === 'variant') {
                    $this->cleanupAllVariants($product);
                }
                $this->processSimpleProduct($product, $request, $pricingData, $mode);
            }

            $this->handleMediaUploads($product, $request);
            DB::commit();
            return [
                'success' => true,
                'product' => $product,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function createProduct(array $validated)
    {
        $product = Product::create([
            'seller_id' => $validated['seller_id'],
            'category_id' => $validated['category_id'],
            'brand_id' => $validated['brand_id'] ?? null,
            'title' => $validated['title'],
            'type' => $validated['type'],
            'base_prep_time' => $validated['base_prep_time'] ?? 0,
            'short_description' => $validated['short_description'],
            'description' => $validated['description'],
            'indicator' => $validated['indicator'] ?? null,
            'image_fit' => $validated['image_fit'] ?? 'cover',
            'minimum_order_quantity' => $validated['minimum_order_quantity'] ?? 1,
            'quantity_step_size' => $validated['quantity_step_size'] ?? 1,
            'total_allowed_quantity' => $validated['total_allowed_quantity'] ?? null,
            'is_returnable' => (string)($validated['is_returnable'] ?? 0),
            'returnable_days' => $validated['returnable_days'] ?? null,
            'is_cancelable' => (string)($validated['is_cancelable'] ?? 0),
            'cancelable_till' => $validated['cancelable_till'] ?? null,
            'is_attachment_required' => (string)($validated['is_attachment_required'] ?? 0),
            'featured' => (string)($validated['featured'] ?? 0),
            'requires_otp' => (string)($validated['requires_otp'] ?? 0),
            'video_type' => $validated['video_type'],
            'warranty_period' => $validated['warranty_period'] ?? null,
            'guarantee_period' => $validated['guarantee_period'] ?? null,
            'made_in' => $validated['made_in'] ?? null,
            'tags' => json_encode($validated['tags'] ?? []),
            'custom_fields' => $validated['custom_fields'] ?? null,
        ]);
        $category = Category::findOrFail($validated['category_id']);
        if ($category->requires_approval) {
            $product->setStatusAttribute(ProductStatusEnum::DRAFT());
            $product->setVerificationStatus(ProductVarificationStatusEnum::PENDING());
        } else {
            $product->setStatusAttribute(ProductStatusEnum::ACTIVE());
            $product->setVerificationStatus(ProductVarificationStatusEnum::APPROVED());
        }
        $product->save();
        event(new ProductStatusAfterUpdate($product));
        return $product;
    }

    private function isVariantProduct(array $validated): bool
    {
        return $validated['type'] === 'variant' && isset($validated['variants_json']);
    }

    private function processVariantProduct($product, array $validated, array $pricingData, string $mode, $request): void
    {
        $variantsData = json_decode($validated['variants_json'], true);
        $newVariantIds = [];
        // Get existing variants if updating
        $existingVariants = ($mode === 'update')
            ? $product->variants()->with('attributes')->get()
            : collect();
        $existingVariantIds = $existingVariants->pluck('id')->toArray();
        foreach ($variantsData as $variantData) {
            $variant = null;
            $imageName = 'variant_image' . $variantData['id'];

            // Try to find matching variant if updating
            if ($mode === 'update' && !empty($variantData['attributes'])) {
                $variant = $this->findMatchingVariant($existingVariants, $variantData, $newVariantIds);
            }
            if ($variant) {
                // Update existing variant
                //                    !empty($variantData['weight']) ? (float)$variantData['weight'] : null
//!empty($variantData['height']) ? (float)$variantData['height'] : null
//!empty($variantData['breadth']) ? (float)$variantData['breadth'] : null
//!empty($variantData['length']) ? (float)$variantData['length'] : null
                $variant->update([
                    'title' => !empty($variantData['title']) ? $variantData['title'] : null,
                    'weight' => 1,
                    'height' => 1,
                    'breadth' => 1,
                    'length' => 1,
                    'availability' => $variantData['availability'] === 'no' ? false : true,
                    'barcode' => !empty($variantData['barcode']) ? $variantData['barcode'] : null,
                    'is_default' => $variantData['is_default'] == 'on' ? true : false,
                ]);


                // Update variant attributes
                if (!empty($variantData['attributes'])) {
                    $variant->attributes()->forceDelete();
                    $this->createVariantAttributes(productId: $product->id, variantId: $variant->id, attributes: $variantData['attributes']);
                }

                $newVariantIds[] = $variant->id;
            } else {
                // Create new variant
                $variant = ProductVariant::create([
                    'uuid' => (string)Str::uuid(),
                    'product_id' => $product->id,
                    'title' => !empty($variantData['title']) ? $variantData['title'] : null,
                    'weight' => 1,
                    'height' => 1,
                    'breadth' => 1,
                    'length' => 1,
                    'availability' => $variantData['availability'] === 'no' ? false : true,
                    'barcode' => !empty($variantData['barcode']) ? $variantData['barcode'] : null,
                    'is_default' => $variantData['is_default'] == 'on' ? true : false,
                ]);


                if (!empty($variantData['attributes'])) {
                    $this->createVariantAttributes(productId: $product->id, variantId: $variant->id, attributes: $variantData['attributes']);
                }

                $newVariantIds[] = $variant->id;
            }

            if ($request->hasFile($imageName)) {
                $this->handleVariantMediaUploads($variant, $imageName);
            }
            // Handle store pricing for this variant
            $this->handleVariantPricing($variant, $variantData, $pricingData, $mode);
        }

        // Delete variants that are no longer in the updated data (only when updating)
        if ($mode === 'update' && !empty($existingVariantIds)) {
            $variantsToDelete = array_diff($existingVariantIds, $newVariantIds);
            if (!empty($variantsToDelete)) {
                ProductVariant::whereIn('id', $variantsToDelete)->delete();
            }
        }
    }

    private function findMatchingVariant($existingVariants, $variantData, $alreadyMatchedIds)
    {
        // Create a map of attribute_id => value_id for easier comparison
        $variantAttributeMap = [];
        foreach ($variantData['attributes'] as $attr) {
            $variantAttributeMap[$attr['attribute_id']] = $attr['value_id'];
        }

        // Check each existing variant for a match
        foreach ($existingVariants as $existingVariant) {
            // Skip if this variant has already been matched
            if (in_array($existingVariant->id, $alreadyMatchedIds)) {
                continue;
            }

            // Get existing variant attributes
            $existingAttributes = $existingVariant->attributes;

            // If attribute count doesn't match, it's not the same variant
            if (count($existingAttributes) !== count($variantAttributeMap)) {
                continue;
            }

            // Check if all attributes match
            $allMatch = true;
            foreach ($existingAttributes as $attr) {
                if (!isset($variantAttributeMap[$attr->global_attribute_id]) ||
                    $variantAttributeMap[$attr->global_attribute_id] != $attr->global_attribute_value_id) {
                    $allMatch = false;
                    break;
                }
            }

            if ($allMatch) {
                return $existingVariant;
            }
        }

        return null;
    }

    private function handleVariantPricing($variant, $variantData, $pricingData, $mode): void
    {
        if (empty($pricingData['variant_pricing'])) {
            return;
        }

        // Delete existing store pricing if updating
        if ($mode === 'update') {
            StoreProductVariant::where('product_variant_id', $variant->id)->forceDelete();
        }

        // Find pricing data for this variant
        $variantPricing = array_filter(
            $pricingData['variant_pricing'],
            fn($vp) => isset($vp['variant_id']) && $vp['variant_id'] === $variantData['id']
        );

        // Create new store pricing
        if (!empty($variantPricing)) {
            $this->createStoreProductVariants($variant->id, $variantPricing);
        }
    }

    private function processSimpleProduct($product, $request, array $pricingData, string $mode): void
    {
        $variant = null;

        if ($mode === 'update') {
            // Get the existing variant or create a new one if it doesn't exist
            $variant = $product->variants()->first();
        }

        $variantData = [
            'uuid' => (string)Str::uuid(),
            'product_id' => $product->id,
            'title' => $product->title,
            'slug' => $product->slug,
            'weight' => 1,
            'height' => 1,
            'breadth' => 1,
            'length' => 1,
            'barcode' => !empty($request['barcode']) ? $request['barcode'] : null,
            'availability' => 1,
            'is_default' => true,
        ];

        if ($variant) {
            $variant->update($variantData);
        } else {
            $variant = ProductVariant::create($variantData);
        }
        if (!empty($pricingData['store_pricing'])) {
            // Delete existing store pricing if updating
            if ($mode === 'update') {
                StoreProductVariant::where('product_variant_id', $variant->id)->forceDelete();
            }

            // Create new store pricing
            $this->createStoreProductVariants($variant->id, $pricingData['store_pricing']);
        }
    }

    private function createVariantAttributes(int $productId, int $variantId, array $attributes): void
    {
        foreach ($attributes as $attribute) {
            ProductVariantAttribute::create([
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'global_attribute_id' => $attribute['attribute_id'],
                'global_attribute_value_id' => $attribute['value_id'],
            ]);
        }
    }

    private function createStoreProductVariants(int $variantId, array $storePricings): void
    {
        foreach ($storePricings as $pricing) {
            StoreProductVariant::create([
                'product_variant_id' => $variantId,
                'store_id' => $pricing['store_id'],
                'price' => $pricing['price'] ?? null,
                'sku' => $pricing['sku'],
                'special_price' => $pricing['special_price'] ?? null,
                'cost' => $pricing['cost'] ?? null,
                'stock' => $pricing['stock'] ?? 0,
            ]);
        }
    }

    private function updateProductDetails(Product $product, array $validated): void
    {
        $product->update([
            // Allow type to be updated so switching simple <-> variant is possible
            'type' => $validated['type'] ?? $product->type,
            'category_id' => $validated['category_id'],
            'brand_id' => $validated['brand_id'] ?? null,
            'title' => $validated['title'],
            'base_prep_time' => $validated['base_prep_time'] ?? 0,
            'short_description' => $validated['short_description'],
            'description' => $validated['description'],
            'indicator' => $validated['indicator'] ?? null,
            'image_fit' => $validated['image_fit'] ?? $product->image_fit,
            'hsn_code' => $validated['hsn_code'] ?? null,
            'minimum_order_quantity' => $validated['minimum_order_quantity'] ?? 1,
            'quantity_step_size' => $validated['quantity_step_size'] ?? 1,
            'total_allowed_quantity' => $validated['total_allowed_quantity'] ?? null,
            'is_returnable' => (string)($validated['is_returnable'] ?? 0),
            'returnable_days' => $validated['returnable_days'] ?? null,
            'is_cancelable' => (string)($validated['is_cancelable'] ?? 0),
            'cancelable_till' => $validated['cancelable_till'] ?? null,
            'is_attachment_required' => (string)($validated['is_attachment_required'] ?? 0),
            'featured' => (string)($validated['featured'] ?? 0),
            'requires_otp' => (string)($validated['requires_otp'] ?? 0),
            'video_type' => $validated['video_type'],
            'warranty_period' => $validated['warranty_period'] ?? null,
            'guarantee_period' => $validated['guarantee_period'] ?? null,
            'made_in' => $validated['made_in'] ?? null,
            'tags' => json_encode($validated['tags'] ?? []),
            'custom_fields' => $validated['custom_fields'] ?? null,
        ]);
        $category = Category::findOrFail($validated['category_id']);
        if ($category->requires_approval) {
            $product->setStatusAttribute(ProductStatusEnum::DRAFT());
            $product->setVerificationStatus(ProductVarificationStatusEnum::PENDING());
        } else {
            $product->setStatusAttribute(ProductStatusEnum::ACTIVE());
            $product->setVerificationStatus(ProductVarificationStatusEnum::APPROVED());
        }
        $product->save();
        event(new ProductStatusAfterUpdate($product));

    }

    /**
     * Clean up all variants and their related records for a product.
     * Used when switching from variant product to simple product during update.
     */
    private function cleanupAllVariants(Product $product): void
    {
        $variantIds = $product->variants()->pluck('id')->toArray();
        if (empty($variantIds)) {
            return;
        }
        // Delete related store product variants and attributes first
        StoreProductVariant::whereIn('product_variant_id', $variantIds)->forceDelete();
        ProductVariantAttribute::whereIn('product_variant_id', $variantIds)->forceDelete();
        // Now delete the variants themselves (force delete to clean media as well)
        ProductVariant::whereIn('id', $variantIds)->forceDelete();
    }

    private function handleVariantMediaUploads($variant, $payload_image): void
    {
        // Remove the existing main image
        $variant->clearMediaCollection(SpatieMediaCollectionName::VARIANT_IMAGE());
        // Upload the new image
        $variant->addMediaFromRequest($payload_image)->toMediaCollection(SpatieMediaCollectionName::VARIANT_IMAGE());
    }

    private function handleMediaUploads($product, $request): void
    {
        if ($request->hasFile('main_image')) {
            // Remove existing main image
            $product->clearMediaCollection(SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE());
            // Upload new main image
            SpatieMediaService::upload(model: $product, media: SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE());
        }

        if ($request->hasFile('additional_images')) {
            // Remove existing additional images if requested
            $product->clearMediaCollection(SpatieMediaCollectionName::PRODUCT_ADDITIONAL_IMAGE());

            // Upload new additional images
            foreach ($request->file('additional_images') as $image) {
                SpatieMediaService::uploadFromRequest($product, $image, SpatieMediaCollectionName::PRODUCT_ADDITIONAL_IMAGE());
            }
        }

        if (ProductVideoTypeEnum::LOCAL() === $request->video_type) {
            if ($request->hasFile('product_video')) {
                // Remove existing video
                $product->clearMediaCollection(SpatieMediaCollectionName::PRODUCT_VIDEO());
                // Upload new video
                SpatieMediaService::upload(model: $product, media: SpatieMediaCollectionName::PRODUCT_VIDEO());
            }
        } else {
            $product->update(['video_link' => $request['video_link']]);
        }
    }
}
