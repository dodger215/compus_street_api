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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('category');
            $table->string('subcategory')->nullable();
            $table->decimal('price', 10, 2);
            $table->enum('condition', ['new', 'like_new', 'good', 'fair', 'poor']);
            $table->string('location');
            $table->json('images')->nullable();
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_available')->default(true);
            $table->integer('views_count')->default(0);
            $table->enum('status', ['draft', 'available', 'sold', 'pending'])->default('available');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
