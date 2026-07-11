<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('orders', function (Blueprint $table): void { $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('number')->unique(); $table->enum('status', ['pending','paid','processing','shipped','cancelled'])->default('pending'); $table->enum('payment_method', ['stripe','duitnow'])->nullable(); $table->decimal('subtotal', 10, 2); $table->decimal('total', 10, 2); $table->json('shipping_address'); $table->timestamps(); }); }
    public function down(): void { Schema::dropIfExists('orders'); }
};
