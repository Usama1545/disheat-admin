<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductAddonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'is_required' => $this->is_required,
            'min_select' => $this->min_select,
            'max_select' => $this->max_select,

            'options' => $this->whenLoaded('options', function () {
                return $this->options->map(function ($field) {
                    return [
                        'id' => $field->id,
                        'name' => $field->name,
                        'price' => $field->price,
                        'is_default' => $field->is_default,
                    ];
                })->sortBy('sort_order')->values();
            }),
        ];
    }
}
