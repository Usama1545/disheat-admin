<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // fix invalid data first
        DB::table('addons')
            ->whereNotIn('seller_id', DB::table('sellers')->pluck('id'))
            ->update(['seller_id' => null]);

        // then add FK
        Schema::table('addons', function (Blueprint $table) {
            $table->foreign('seller_id')
                ->references('id')
                ->on('sellers')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            // drop old FK
            $table->dropForeign(['seller_id']);

            // rename column
        });

        Schema::table('addons', function (Blueprint $table) {
            // add new FK
            $table->foreign('seller_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }
};
