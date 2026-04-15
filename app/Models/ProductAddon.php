<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAddon extends Model
{
    protected $table = 'product_addons';

    protected $fillable = [
        'product_id',
        'addon_id',
    ];

    public $timestamps = true;

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function addon()
    {
        return $this->belongsTo(Addon::class);
    }
}
