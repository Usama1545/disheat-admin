<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'product_count' => $this->product_count ?? 0,
            'description' => $this->description,
            'contact_number' => $this->contact_number,
            'contact_email' => $this->contact_email,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'distance' => $this->distance ?? 0,
            'timing' => $this->timing ?? null,
            'logo' => $this->store_logo,
            'banner' => $this->store_banner,
            'avg_products_rating' => number_format($this->avg_products_rating ?? 0, 2),
            'avg_store_rating' => number_format($this->avg_store_rating ?? 0, 2),
            'total_store_feedback' => $this->total_store_feedback ?? "0",
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'verification_status' => $this->verification_status,
            'visibility_status' => $this->visibility_status,
            'status' => optional(
                    $this
                )->checkStoreStatus() ?? []
        ];
    }
}
