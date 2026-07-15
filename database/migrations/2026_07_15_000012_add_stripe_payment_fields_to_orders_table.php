<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $columns = [
            'stripe_checkout_session_id' => fn (Blueprint $table) => $table->string('stripe_checkout_session_id')->nullable(),
            'stripe_payment_intent_id' => fn (Blueprint $table) => $table->string('stripe_payment_intent_id')->nullable(),
            'stripe_payment_status' => fn (Blueprint $table) => $table->string('stripe_payment_status')->nullable(),
            'stripe_paid_at' => fn (Blueprint $table) => $table->timestamp('stripe_paid_at')->nullable(),
            'stripe_failure_reason' => fn (Blueprint $table) => $table->string('stripe_failure_reason')->nullable(),
            'payment_provider' => fn (Blueprint $table) => $table->string('payment_provider')->nullable(),
        ];

        foreach ($columns as $column => $addColumn) {
            if (! Schema::hasColumn('orders', $column)) {
                Schema::table('orders', $addColumn);
            }
        }

        if (! Schema::hasIndex('orders', 'orders_stripe_checkout_session_id_unique')) {
            Schema::table('orders', fn (Blueprint $table) => $table->unique('stripe_checkout_session_id'));
        }

        if (! Schema::hasIndex('orders', 'orders_stripe_payment_intent_id_index')) {
            Schema::table('orders', fn (Blueprint $table) => $table->index('stripe_payment_intent_id'));
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('orders', 'orders_stripe_checkout_session_id_unique')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropUnique('orders_stripe_checkout_session_id_unique'));
        }

        if (Schema::hasIndex('orders', 'orders_stripe_payment_intent_id_index')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropIndex('orders_stripe_payment_intent_id_index'));
        }

        $columns = ['stripe_checkout_session_id', 'stripe_payment_intent_id', 'stripe_payment_status', 'stripe_paid_at', 'stripe_failure_reason', 'payment_provider'];
        $existing = array_values(array_filter($columns, fn (string $column) => Schema::hasColumn('orders', $column)));

        if ($existing !== []) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn($existing));
        }
    }
};
