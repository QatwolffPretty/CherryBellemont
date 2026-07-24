<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table): void {
                $table->id();
                $table->string('group', 60)->index();
                $table->string('key', 100);
                $table->longText('value')->nullable();
                $table->string('type', 20)->default('string');
                $table->boolean('is_public')->default(false);
                $table->boolean('is_encrypted')->default(false);
                $table->text('description')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['group', 'key']);
            });
        }

        if (! Schema::hasTable('settings_audit_logs')) {
            Schema::create('settings_audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('setting_id')->nullable()->constrained('settings')->nullOnDelete();
                $table->string('group', 60)->index();
                $table->string('key', 100)->index();
                $table->longText('old_value')->nullable();
                $table->longText('new_value')->nullable();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('ip_hash', 64)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['group', 'key', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings_audit_logs');
        Schema::dropIfExists('settings');
    }
};
