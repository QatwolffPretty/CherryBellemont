<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('newsletter_subscribers')) {
            return;
        }

        Schema::create('newsletter_subscribers', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('name', 160)->nullable();
            $table->string('status', 20)->default('subscribed');
            $table->timestamp('subscribed_at');
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('source', 80)->nullable();
            $table->string('verification_token', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['status', 'subscribed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
