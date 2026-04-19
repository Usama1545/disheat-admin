<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItemAddon extends Model
{
    protected $fillable = [
        'cart_item_id',
        'addon_group_id',
        'addon_option_id',
        'price',
        'name',
    ];

    public function cartItem()
    {
        return $this->belongsTo(CartItem::class);
    }

    public function addonOption()
    {
        return $this->belongsTo(AddonOption::class);
    }

    public function addonGroup()
    {
        return $this->belongsTo(Addon::class);
    }
}
