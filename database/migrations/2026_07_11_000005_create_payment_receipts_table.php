<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('payment_receipts', function (Blueprint $table): void { $table->id(); $table->foreignId('order_id')->constrained()->cascadeOnDelete(); $table->string('path'); $table->enum('status', ['pending','approved','rejected'])->default('pending'); $table->timestamp('reviewed_at')->nullable(); $table->timestamps(); }); }
    public function down(): void { Schema::dropIfExists('payment_receipts'); }
};
