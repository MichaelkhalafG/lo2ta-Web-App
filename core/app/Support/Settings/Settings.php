<?php

namespace App\Support\Settings;

use App\Models\Setting;
use App\Support\Cache\VersionedCache;
use InvalidArgumentException;

/**
 * Typed access to the settings defined in config/settings.php. A key without a stored row answers its default.
 * Writes are validated against the definition and logged (Setting uses LogsActivity).
 */
final class Settings
{
    private const CACHE = 'settings';

    public function int(string $key): int
    {
        $value = $this->get($key);
        if (! is_int($value)) {
            throw new InvalidArgumentException("Setting {$key} is not an int.");
        }

        return $value;
    }

    public function bool(string $key): bool
    {
        $value = $this->get($key);
        if (! is_bool($value)) {
            throw new InvalidArgumentException("Setting {$key} is not a bool.");
        }

        return $value;
    }

    public function string(string $key): string
    {
        $value = $this->get($key);
        if (! is_string($value)) {
            throw new InvalidArgumentException("Setting {$key} is not a string.");
        }

        return $value;
    }

    public function get(string $key): int|bool|string
    {
        $all = $this->all();
        if (! array_key_exists($key, $all)) {
            throw new InvalidArgumentException("Unknown setting {$key}.");
        }

        return $all[$key]['value'];
    }

    /**
     * Every setting with its current value and whether that value is still provisional.
     *
     * @return array<string, array{value: int|bool|string, provisional: bool, group: string, public: bool}>
     */
    public function all(): array
    {
        return VersionedCache::remember(self::CACHE, 'all', function (): array {
            $stored = Setting::query()->get()->keyBy('key');

            $out = [];
            foreach (self::definitions() as $key => $def) {
                $row = $stored->get($key);
                $value = $row instanceof Setting
                    ? $this->coerce($key, $def, json_decode($row->value, true, 4, JSON_THROW_ON_ERROR))
                    : $def['default'];
                $provisional = $row instanceof Setting ? $row->is_provisional : ($def['provisional'] ?? false);

                $out[$key] = [
                    'value' => $value,
                    'provisional' => $provisional,
                    'group' => $def['group'],
                    'public' => $def['public'] ?? false,
                ];
            }

            return $out;
        });
    }

    /**
     * Values the public site may read (definition 'public' => true).
     *
     * @return array<string, int|bool|string>
     */
    public function publicValues(): array
    {
        $out = [];
        foreach ($this->all() as $key => $entry) {
            if ($entry['public']) {
                $out[$key] = $entry['value'];
            }
        }

        return $out;
    }

    /**
     * Store a value. $confirmed = true clears the provisional flag (the value is researched or confirmed).
     *
     * @param  positive-int|null  $adminId
     */
    public function set(string $key, mixed $value, bool $confirmed = true, ?int $adminId = null): void
    {
        $defs = self::definitions();
        if (! array_key_exists($key, $defs)) {
            throw new InvalidArgumentException("Unknown setting {$key}.");
        }

        $clean = $this->coerce($key, $defs[$key], $value);

        $row = Setting::query()->firstOrNew(['key' => $key]);
        $row->value = json_encode($clean, JSON_THROW_ON_ERROR);
        $row->is_provisional = ! $confirmed;
        $row->updated_by = $adminId;
        $row->save();

        VersionedCache::bump(self::CACHE);
    }

    /**
     * Write every definition that has no stored row yet, with its default and provisional flag. Never overwrites
     * a stored value (settings are dashboard-authored).
     */
    public function seedDefaults(): int
    {
        $created = 0;
        foreach (self::definitions() as $key => $def) {
            $row = Setting::query()->firstOrNew(['key' => $key]);
            if ($row->exists) {
                continue;
            }
            $row->value = json_encode($def['default'], JSON_THROW_ON_ERROR);
            $row->is_provisional = $def['provisional'] ?? false;
            $row->save();
            $created++;
        }

        VersionedCache::bump(self::CACHE);

        return $created;
    }

    /**
     * @return array<string, array{type: string, group: string, default: int|bool|string, min?: int, max?: int, options?: list<string>, public?: bool, provisional?: bool, format?: string}>
     */
    public static function definitions(): array
    {
        /** @var array<string, array{type: string, group: string, default: int|bool|string, min?: int, max?: int, options?: list<string>, public?: bool, provisional?: bool, format?: string}> $defs */
        $defs = config('settings');

        return $defs;
    }

    /**
     * @param  array{type: string, group: string, default: int|bool|string, min?: int, max?: int, options?: list<string>, public?: bool, provisional?: bool, format?: string}  $def
     */
    private function coerce(string $key, array $def, mixed $value): int|bool|string
    {
        switch ($def['type']) {
            case 'int':
                if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
                    $value = (int) $value;
                }
                if (! is_int($value)) {
                    throw new InvalidArgumentException("Setting {$key} needs a whole number.");
                }
                if (isset($def['min']) && $value < $def['min']) {
                    throw new InvalidArgumentException("Setting {$key} is below its minimum {$def['min']}.");
                }
                if (isset($def['max']) && $value > $def['max']) {
                    throw new InvalidArgumentException("Setting {$key} is above its maximum {$def['max']}.");
                }

                return $value;
            case 'bool':
                if (! is_bool($value)) {
                    throw new InvalidArgumentException("Setting {$key} needs true or false.");
                }

                return $value;
            case 'enum':
                if (! is_string($value) || ! in_array($value, $def['options'] ?? [], true)) {
                    throw new InvalidArgumentException("Setting {$key} must be one of: ".implode(', ', $def['options'] ?? []).'.');
                }

                return $value;
            case 'string':
                if (! is_string($value)) {
                    throw new InvalidArgumentException("Setting {$key} needs text.");
                }
                $value = trim($value);
                if (($def['format'] ?? null) === 'email_or_empty' && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    throw new InvalidArgumentException("Setting {$key} needs an e-mail address or nothing.");
                }

                return $value;
        }

        throw new InvalidArgumentException("Setting {$key} has an unknown type {$def['type']}.");
    }
}
