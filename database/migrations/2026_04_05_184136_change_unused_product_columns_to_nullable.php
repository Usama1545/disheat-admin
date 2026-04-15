<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Make products table fields nullable for simple products
        Schema::table('products', function (Blueprint $table) {
            // These are not needed for simple eatable products
            $table->string('hsn_code')->nullable()->change();
            $table->string('warranty_period')->nullable()->change();
            $table->string('guarantee_period')->nullable()->change();
            $table->string('made_in')->nullable()->change();
            $table->string('product_identity')->nullable()->change();
            $table->string('provider')->nullable()->change();
            $table->string('provider_product_id')->nullable()->change();
            $table->boolean('download_allowed')->default(0)->nullable()->change();
            $table->string('download_link')->nullable()->change();
            
            // Make variant-related columns nullable for simple products
            $table->string('type')->default('simple')->change();
        });
        
        // Make product_variants table fully optional (all columns nullable or remove constraints)
        if (Schema::hasTable('product_variants')) {
            Schema::table('product_variants', function (Blueprint $table) {
                // Make all dimension/weight fields nullable
                $table->string('weight')->nullable()->change();
                $table->string('height')->nullable()->change();
                $table->string('breadth')->nullable()->change();
                $table->string('length')->nullable()->change();
                $table->string('barcode')->nullable()->change();
                $table->string('provider')->nullable()->change();
                $table->string('provider_product_id')->nullable()->change();
                $table->json('provider_json')->nullable()->change();
                
                // Make product_id nullable? No, keep foreign key but allow null if needed
                // For simple products, we might not create variants at all
            });
        }
        
        // Ensure store_product_variants can exist without variants (for simple products)
        if (Schema::hasTable('store_product_variants')) {
            Schema::table('store_product_variants', function (Blueprint $table) {
                // Make product_variant_id nullable for simple products that don't use variants
                $table->foreignId('product_variant_id')->nullable()->change();
                
                // Add product_id directly for simple products to bypass variants
                if (!Schema::hasColumn('store_product_variants', 'product_id')) {
                    $table->foreignId('product_id')->nullable()->after('id');
                }
            });
        }
    }
    
    public function down()
    {
        // Revert changes if needed
        Schema::table('products', function (Blueprint $table) {
            $table->string('hsn_code')->nullable(false)->change();
            $table->string('warranty_period')->nullable(false)->change();
            $table->string('guarantee_period')->nullable(false)->change();
            $table->string('made_in')->nullable(false)->change();
            $table->string('product_identity')->nullable(false)->change();
            $table->string('type')->nullable(false)->change();
        });
        
        if (Schema::hasTable('product_variants')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->string('weight')->nullable(false)->change();
                $table->string('height')->nullable(false)->change();
                $table->string('breadth')->nullable(false)->change();
                $table->string('length')->nullable(false)->change();
            });
        }
    }
};