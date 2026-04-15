<?php

namespace App\Services;

use App\Models\Addon;

class AddonService
{
    public static function getAttributesWithValue($sellerId = null)
    {
        $query = Addon::select('id', 'name');

        if ($sellerId) {
            $query->where('seller_id', $sellerId);
        }

        return $query->get()->map(function ($addon) {
            return [
                    'id' => (string) $addon->id,
                    'name' => $addon->name,
                ];
            });
    }
}