<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('newsletter_campaigns')) {
            Schema::create('newsletter_campaigns', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('subject');
                $table->string('preview_text')->nullable();
                $table->longText('content');
                $table->string('hero_image_path')->nullable();
                $table->string('cta_text')->nullable();
                $table->string('cta_url', 2048)->nullable();
                $table->string('status', 24)->default('draft');
                $table->string('audience_type', 48)->default('all_active');
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('sending_started_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->unsignedInteger('recipient_count')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('status');
                $table->index('scheduled_at');
            });
        }

        if (! Schema::hasTable('newsletter_campaign_deliveries')) {
            Schema::create('newsletter_campaign_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('newsletter_campaign_id')->constrained()->cascadeOnDelete();
                $table->foreignId('newsletter_subscriber_id')->nullable()->constrained()->nullOnDelete();
                $table->string('email')->index();
                $table->string('name')->nullable();
                $table->string('status', 24)->default('pending');
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->string('provider_message_id')->nullable();
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('clicked_at')->nullable();
                $table->timestamps();

                $table->unique(['newsletter_campaign_id', 'email'], 'campaign_delivery_email_unique');
                $table->index(['newsletter_campaign_id', 'status'], 'campaign_delivery_status_index');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaign_deliveries');
        Schema::dropIfExists('newsletter_campaigns');
    }
};
