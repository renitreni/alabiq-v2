<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Platform settings with DB overrides on top of config defaults.
 *
 * A setting row overrides config('alabiq.<key>'); deleting a row (not
 * implemented here) would restore the config default. Values are typed
 * (string | numeric | boolean) and every write is audit logged.
 */
class SettingsService
{
    /**
     * Known keys with their types — anything outside this list is rejected.
     */
    public const KNOWN_KEYS = [
        'vat_rate' => 'numeric',
        'currency' => 'string',
        'timezone' => 'string',
        'default_delivery_fee' => 'numeric',
        'platform_commission_rate' => 'numeric',
        'cod_enabled' => 'boolean',
        'online_payments_enabled' => 'boolean',
        'maintenance_mode' => 'boolean',
    ];

    private const CACHE_KEY = 'alabiq:system_settings';

    /**
     * Effective value for a key: DB row first, config fallback.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    /**
     * All effective settings (DB overrides merged over config defaults).
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $defaults = [];
        foreach (self::KNOWN_KEYS as $key => $type) {
            $defaults[$key] = $this->coerce(config("alabiq.{$key}"), $type);
        }

        $overrides = Cache::rememberForever(self::CACHE_KEY, function () {
            return SystemSetting::query()->pluck('value', 'key')->all();
        });

        foreach ($overrides as $key => $value) {
            $type = self::KNOWN_KEYS[$key] ?? 'string';
            $decoded = json_decode((string) $value, true);
            $defaults[$key] = $this->coerce($decoded ?? $value, $type);
        }

        return $defaults;
    }

    /**
     * Upsert a setting and audit the change. Unknown keys are rejected.
     *
     * @return array{old: mixed, new: mixed}
     */
    public function set(string $key, mixed $value, ?User $actor = null, ?string $ip = null): array
    {
        if (! array_key_exists($key, self::KNOWN_KEYS)) {
            throw new \DomainException("Unknown setting key: {$key}");
        }

        $type = self::KNOWN_KEYS[$key];
        $old = $this->get($key);

        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => json_encode($this->coerce($value, $type)),
                'type' => $type,
                'is_public' => in_array($key, ['currency', 'timezone', 'cod_enabled'], true),
            ]
        );

        Cache::forget(self::CACHE_KEY);

        (new AuditLogService)->record($actor, 'settings.updated', null, [
            $key => $old,
        ], [
            $key => $this->coerce($value, $type),
        ], $ip);

        return ['old' => $old, 'new' => $this->coerce($value, $type)];
    }

    /**
     * Settings safe to expose publicly (no secrets).
     *
     * @return Collection<string, mixed>
     */
    public function publicSettings(): Collection
    {
        return SystemSetting::query()
            ->where('is_public', true)
            ->get()
            ->mapWithKeys(fn (SystemSetting $s) => [$s->key => $s->typed_value]);
    }

    private function coerce(mixed $value, string $type): mixed
    {
        return match ($type) {
            'numeric' => (float) $value,
            'boolean' => (bool) $value,
            default => (string) $value,
        };
    }
}
