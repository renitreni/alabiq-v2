<?php

namespace Tests\Unit\Services;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private SettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = new SettingsService;
    }

    public function test_get_falls_back_to_config_defaults(): void
    {
        config(['alabiq.vat_rate' => 0.15]);

        $this->assertSame(0.15, $this->service->get('vat_rate'));
        $this->assertSame('SAR', $this->service->get('currency'));
    }

    public function test_db_row_overrides_config_default(): void
    {
        config(['alabiq.vat_rate' => 0.15]);
        SystemSetting::factory()->numeric('vat_rate', 0.05)->create();

        $this->assertSame(0.05, $this->service->get('vat_rate'));
    }

    public function test_set_creates_row_and_audits_change(): void
    {
        $admin = User::factory()->role(User::ROLE_SUPER_ADMIN)->create();

        $result = $this->service->set('vat_rate', 0.05, $admin, '10.0.0.1');

        $this->assertSame(0.15, $result['old']);
        $this->assertSame(0.05, $result['new']);
        $this->assertDatabaseHas('system_settings', ['key' => 'vat_rate', 'type' => 'numeric']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'settings.updated']);
    }

    public function test_set_is_coerced_to_declared_type(): void
    {
        $this->service->set('cod_enabled', 1);

        $setting = SystemSetting::where('key', 'cod_enabled')->sole();
        $this->assertSame('boolean', $setting->type);
        $this->assertTrue($setting->typed_value);
        $this->assertTrue($this->service->get('cod_enabled'));
    }

    public function test_set_rejects_unknown_keys(): void
    {
        $this->expectException(\DomainException::class);
        $this->service->set('hacked_key', 'x');
    }

    public function test_set_updates_existing_row_and_refreshes_cache(): void
    {
        $this->service->set('currency', 'USD');

        // Cached snapshot would still say USD without invalidation.
        $this->assertSame('USD', $this->service->get('currency'));

        $this->service->set('currency', 'EUR');

        $this->assertSame('EUR', $this->service->get('currency'));
        $this->assertSame(1, SystemSetting::where('key', 'currency')->count());
    }

    public function test_all_returns_known_keys_with_types(): void
    {
        $all = $this->service->all();

        foreach (array_keys(SettingsService::KNOWN_KEYS) as $key) {
            $this->assertArrayHasKey($key, $all);
        }
        $this->assertIsFloat($all['vat_rate']);
        $this->assertIsBool($all['cod_enabled']);
        $this->assertIsString($all['currency']);
    }

    public function test_public_settings_only_exposes_flagged_rows(): void
    {
        SystemSetting::factory()->numeric('vat_rate', 0.15)->create(['is_public' => false]);
        SystemSetting::factory()->create(['key' => 'currency', 'value' => 'SAR', 'is_public' => true]);

        $public = $this->service->publicSettings();

        $this->assertFalse($public->has('vat_rate'));
        $this->assertSame('SAR', $public->get('currency'));
    }
}
