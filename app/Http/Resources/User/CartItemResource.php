<?php

namespace App\Http\Resources\User;

use App\Models\Review;
use App\Services\DeliveryZoneService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reviews = Review::scopeProductRatingStats($this->product->id);
        if (isset($request->latitude) && isset($request->longitude)) {
            $this->product->user_latitude = $request->latitude;
            $this->product->user_longitude = $request->longitude;
            $this->product->zone_info = DeliveryZoneService::getZonesAtPoint($request->latitude, $request->longitude);
        }
        return [
            'id' => $this->id,
            'cart_id' => $this->cart_id,
            'product_id' => $this->product_id,
            'store_id' => $this->store_id,
            'quantity' => $this->quantity,
            'save_for_later' => $this->save_for_later,
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->title,
                'slug' => $this->product->slug,
                
                'image' => $this->product->main_image ?? null,
                'estimated_delivery_time' => $this->product->estimated_delivery_time,
                'image_fit' => $this->product->image_fit,
                'store_status' => $this->product->store->checkStoreStatus() ?? [],
                'ratings' => $reviews['average_rating'] ?? 0,
                'rating_count' => $reviews['total_reviews'] ?? 0,
                'compare_at_price' => $this->product->compare_at_price,
                'price' => $this->product->price,
            ],
            'store' => [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'slug' => $this->store->slug,
                'total_products' => $this->store->product_count ?? 0,
                'status' => optional(
                        $this->store
                    )->checkStoreStatus() ?? [],
            ],
            'addons' => $this->addons,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s')
        ];
    }
}
