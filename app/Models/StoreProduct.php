<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreProduct extends Model
{
    protected $table = "store_products";
    protected $fillable = [
        'store_id',
        'product_id',
        'compare_at_price',
        'price',
        'cost_per_item',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'product_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
