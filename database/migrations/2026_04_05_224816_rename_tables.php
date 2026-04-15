<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Rename old products → ecom_products
        Schema::rename('products', 'ecom_products');

        // 2. Rename simple_products → products
        Schema::rename('simple_products', 'products');
    }

    public function down(): void
    {
        // rollback safely
        Schema::rename('products', 'simple_products');
        Schema::rename('ecom_products', 'products');
    }
};
