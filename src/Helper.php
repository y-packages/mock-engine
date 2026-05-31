<?php

declare(strict_types=1);

namespace YakNet\MockEngine;

class Helper
{
    /**
     * Get a value from an array or object using dot notation.
     */
    public static function getValue(mixed $target, string $key, mixed $default = null): mixed
    {
        if (empty($key)) {
            return $target;
        }

        $segments = explode('.', $key);
        $current = $target;

        foreach ($segments as $segment) {
            if (is_array($current)) {
                if (array_key_exists($segment, $current)) {
                    $current = $current[$segment];
                } else {
                    return $default;
                }
            } elseif (is_object($current)) {
                if (property_exists($current, $segment) || isset($current->{$segment})) {
                    $current = $current->{$segment};
                } elseif (method_exists($current, $segment)) {
                    $current = $current->{$segment}();
                } elseif (method_exists($current, 'get' . ucfirst($segment))) {
                    $current = $current->{'get' . ucfirst($segment)}();
                } else {
                    return $default;
                }
            } else {
                return $default;
            }
        }

        return $current;
    }
}
