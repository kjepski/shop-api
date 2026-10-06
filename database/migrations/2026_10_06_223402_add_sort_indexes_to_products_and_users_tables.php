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
        // Sorted, paginated lists: admins see every product, regular users only active ones.
        Schema::table('products', function (Blueprint $table) {
            $table->index('price');
            $table->index('created_at');
            $table->index(['is_active', 'price']);
            $table->index(['is_active', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['price']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['is_active', 'price']);
            $table->dropIndex(['is_active', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
