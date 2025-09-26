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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reviewer_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('target_id');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('type', ['item', 'seller']);
            $table->integer('rating');
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_helpful')->default(false);
            $table->boolean('is_reported')->default(false);
            $table->text('reported_reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'reported'])->default('pending');
            $table->timestamps();

            $table->index(['target_id', 'type']);
            $table->index(['reviewer_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
