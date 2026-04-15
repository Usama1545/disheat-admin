<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    protected $fillable = [
        'name',
        'type',
        'is_required',
        'min_select',
        'max_select',
        'seller_id',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    // Relationships
    public function options()
    {
        return $this->hasMany(AddonOption::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_addons');
    }
}
