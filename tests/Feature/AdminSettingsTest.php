<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\SettingAuditLog;
use App\Models\User;
use App\Services\GiftWrapping;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_admin_can_open_settings(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.settings.index'))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Store Settings');
    }

    public function test_an_admin_can_update_general_settings_and_an_audit_record_is_created(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.settings.update'), [
            'group' => 'store',
            'settings' => ['store.company_name' => 'Cherry Bellemont Atelier'],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('settings', ['group' => 'store', 'key' => 'company_name', 'value' => 'Cherry Bellemont Atelier']);
        $this->assertDatabaseHas('settings_audit_logs', ['group' => 'store', 'key' => 'company_name', 'changed_by' => $admin->id]);
        $this->assertSame('Cherry Bellemont Atelier', app(SettingsService::class)->get('store.company_name'));
    }

    public function test_invalid_values_and_secret_like_keys_are_rejected(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.settings.index'))
            ->patch(route('admin.settings.update'), [
                'group' => 'contact',
                'settings' => ['contact.general_email' => 'not-an-email'],
            ])->assertSessionHasErrors('settings.contact.general_email');

        $this->actingAs($this->admin())
            ->patch(route('admin.settings.update'), [
                'group' => 'stripe',
                'settings' => ['stripe.secret_key' => 'never-store-me'],
            ])->assertSessionHasErrors();

        $this->assertDatabaseMissing('settings', ['group' => 'stripe', 'key' => 'secret_key']);
    }

    public function test_at_least_one_public_payment_method_must_remain_available(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('admin.settings.update'), [
                'group' => 'payment',
                'settings' => [
                    'payment.stripe_enabled' => '0',
                    'payment.duitnow_enabled' => '0',
                ],
            ])->assertSessionHasErrors('settings');
    }

    public function test_branding_upload_uses_the_public_disk_and_does_not_expose_a_path(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post(route('admin.settings.media.upload'), [
                'setting_key' => 'store.logo_light',
                'file' => UploadedFile::fake()->image('cherry-logo.png'),
            ])->assertSessionHas('success');

        $setting = Setting::query()->where('group', 'store')->where('key', 'logo_light')->firstOrFail();
        Storage::disk('public')->assertExists($setting->value);
        $this->assertStringStartsWith('settings/', $setting->value);
    }

    public function test_gift_price_changes_only_the_current_server_side_price(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('gift.wrap_price', '45.00', $this->admin()->id);

        $this->assertSame(4500, app(GiftWrapping::class)->feeCents(true));
        $this->assertSame(0, app(GiftWrapping::class)->feeCents(false));
    }

    public function test_setting_cache_is_cleared_after_an_update(): void
    {
        $settings = app(SettingsService::class);
        $this->assertSame('Cherry Bellemont', $settings->get('store.company_name'));
        $settings->set('store.company_name', 'Cache Refreshed', $this->admin()->id);

        $this->assertSame('Cache Refreshed', $settings->get('store.company_name'));
    }

    public function test_audit_history_loads_existing_entries_and_supports_pagination(): void
    {
        $admin = $this->admin();
        $setting = Setting::query()->create([
            'group' => 'store',
            'key' => 'tagline',
            'value' => 'A timeless collection',
            'type' => 'string',
            'updated_by' => $admin->id,
        ]);

        foreach (range(1, 31) as $number) {
            SettingAuditLog::query()->create([
                'setting_id' => $setting->id,
                'group' => 'store',
                'key' => 'tagline',
                'old_value' => 'Earlier '.$number,
                'new_value' => 'Updated '.$number,
                'changed_by' => $admin->id,
                'created_at' => now()->addSeconds($number),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.settings.audit'))
            ->assertOk()
            ->assertSee('Settings Audit History')
            ->assertSee('Updated 31');

        $this->actingAs($admin)
            ->get(route('admin.settings.audit', ['page' => 2]))
            ->assertOk()
            ->assertSee('Updated 1');
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }
}
