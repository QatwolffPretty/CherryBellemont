<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('category_product', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['category_id', 'product_id']);
            $table->index(['product_id', 'is_primary']);
        });

        Schema::create('product_sizes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index('sort_order');
        });

        Schema::create('product_colours', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('hex_code', 7)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index('sort_order');
        });

        Schema::create('product_product_size', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_size_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'product_size_id']);
            $table->index('product_size_id');
        });

        Schema::create('product_product_colour', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_colour_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'product_colour_id']);
            $table->index('product_colour_id');
        });

        Schema::create('product_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index('sort_order');
        });

        Schema::create('product_product_tag', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'product_tag_id']);
            $table->index('product_tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_product_tag');
        Schema::dropIfExists('product_tags');
        Schema::dropIfExists('product_product_colour');
        Schema::dropIfExists('product_product_size');
        Schema::dropIfExists('product_colours');
        Schema::dropIfExists('product_sizes');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('categories');
    }
};
