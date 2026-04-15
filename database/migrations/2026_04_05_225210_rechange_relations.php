<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_products', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('store_products', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('products') // ✅ correct table now
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('store_products', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('store_products', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('ecom_products'); // rollback
        });
    }
};
