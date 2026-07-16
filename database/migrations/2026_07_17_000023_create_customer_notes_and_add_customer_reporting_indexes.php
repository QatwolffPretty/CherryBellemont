<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ORDER_INDEX = 'orders_customer_email_created_at_index';

    private const COUPON_USAGE_INDEX = 'coupon_usages_used_at_coupon_id_index';

    private const NEWSLETTER_INDEX = 'newsletter_subscribers_status_subscribed_at_index';

    public function up(): void
    {
        if (! Schema::hasTable('customer_notes')) {
            Schema::create('customer_notes', function (Blueprint $table): void {
                $table->id();
                $table->string('customer_email')->index();
                $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('note');
                $table->timestamps();
            });
        }

        $this->addIndex('orders', self::ORDER_INDEX, ['customer_email', 'created_at']);
        $this->addIndex('coupon_usages', self::COUPON_USAGE_INDEX, ['used_at', 'coupon_id']);
        $this->addIndex('newsletter_subscribers', self::NEWSLETTER_INDEX, ['status', 'subscribed_at']);
    }

    public function down(): void
    {
        $this->dropIndex('orders', self::ORDER_INDEX);
        $this->dropIndex('coupon_usages', self::COUPON_USAGE_INDEX);
        $this->dropIndex('newsletter_subscribers', self::NEWSLETTER_INDEX);
        Schema::dropIfExists('customer_notes');
    }

    /** @param array<int, string> $columns */
    private function addIndex(string $table, string $name, array $columns): void
    {
        if (Schema::hasTable($table) && ! Schema::hasIndex($table, $name)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
        }
    }

    private function dropIndex(string $table, string $name): void
    {
        if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
        }
    }
};
