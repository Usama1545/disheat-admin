<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_addons', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('product_addons', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('products') // ✅ fix here
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('product_addons', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('product_addons', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('ecom_products');
        });
    }
};
