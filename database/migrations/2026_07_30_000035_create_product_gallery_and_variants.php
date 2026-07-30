<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_images')) {
            Schema::create('product_images', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('image_path');
                $table->string('alt_text')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->unique(['product_id', 'image_path']);
                $table->index(['product_id', 'is_primary']);
                $table->index(['product_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_size_id')->nullable()->constrained('product_sizes')->nullOnDelete();
                $table->foreignId('product_colour_id')->nullable()->constrained('product_colours')->nullOnDelete();
                $table->string('sku')->nullable()->unique();
                $table->decimal('price_override', 10, 2)->nullable();
                $table->unsignedInteger('stock')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['product_id', 'product_size_id', 'product_colour_id'], 'product_variant_combination_unique');
                $table->index(['product_id', 'is_active']);
                $table->index(['product_size_id', 'product_colour_id']);
            });
        }

        Schema::table('order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_items', 'product_variant_id')) {
                $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            }
            if (! Schema::hasColumn('order_items', 'sku')) {
                $table->string('sku')->nullable()->after('product_variant_id');
            }
            if (! Schema::hasColumn('order_items', 'size_name')) {
                $table->string('size_name')->nullable()->after('sku');
            }
            if (! Schema::hasColumn('order_items', 'colour_name')) {
                $table->string('colour_name')->nullable()->after('size_name');
            }
            if (! Schema::hasColumn('order_items', 'variant_description')) {
                $table->string('variant_description')->nullable()->after('colour_name');
            }
        });

        // Preserve legacy single-image products as the first gallery image. The
        // old column remains as a backward-compatible primary-image snapshot.
        if (Schema::hasColumn('products', 'image_path')) {
            DB::table('products')
                ->whereNotNull('image_path')
                ->where('image_path', '!=', '')
                ->orderBy('id')
                ->select(['id', 'image_path', 'name', 'created_at', 'updated_at'])
                ->each(function (object $product): void {
                    DB::table('product_images')->insertOrIgnore([
                        'product_id' => $product->id,
                        'image_path' => $product->image_path,
                        'alt_text' => $product->name,
                        'sort_order' => 0,
                        'is_primary' => true,
                        'created_at' => $product->created_at ?? now(),
                        'updated_at' => $product->updated_at ?? now(),
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            if (Schema::hasColumn('order_items', 'product_variant_id')) {
                $table->dropConstrainedForeignId('product_variant_id');
            }
            $columns = collect(['sku', 'size_name', 'colour_name', 'variant_description'])
                ->filter(fn (string $column): bool => Schema::hasColumn('order_items', $column))
                ->all();
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_images');
    }
};
