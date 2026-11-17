<?php

namespace App\Enums;

/**
 * Labels come from lang/{locale}/enums.php under the enum's short class name, so the English site never shows an
 * Arabic value (PLAN 5.1 item 1).
 */
trait HasLabel
{
    public function label(?string $locale = null): string
    {
        $key = 'enums.'.class_basename(static::class).'.'.$this->value;
        $label = __($key, [], $locale);

        return is_string($label) ? $label : $key;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => (string) $case->value, self::cases());
    }
}
