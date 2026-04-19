<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->decimal('base_price', 10, 2)->default(0)->after('quantity');
            $table->decimal('addons_total', 10, 2)->default(0)->after('base_price');
            $table->decimal('grand_total', 10, 2)->default(0)->after('addons_total');
            $table->dropForeign(['product_variant_id']);

            $table->unsignedBigInteger('product_variant_id')->nullable()->change();

            $table->foreign('product_variant_id')
                ->references('id')
                ->on('product_variants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn(['base_price', 'addons_total', 'grand_total']);
        });
    }
};
