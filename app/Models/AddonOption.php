<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddonOption extends Model
{
    protected $fillable = [
        'addon_id',
        'name',
        'price',
        'is_default',
    ];

    protected $casts = [
        'name' => 'array',
        'price' => 'decimal:2',
        'is_default' => 'boolean',
    ];

    // Relationships
    public function addon()
    {
        return $this->belongsTo(Addon::class);
    }
}
