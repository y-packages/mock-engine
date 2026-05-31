<?php

declare(strict_types=1);

namespace YakNet\MockEngine;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use ReturnTypeWillChange;
use Traversable;

/**
 * @template TKey of array-key
 * @template TValue
 * @implements ArrayAccess<TKey, TValue>
 * @implements IteratorAggregate<TKey, TValue>
 */
class Collection implements ArrayAccess, IteratorAggregate, Countable, JsonSerializable
{
    /**
     * @param array<TKey, TValue> $items
     */
    public function __construct(protected array $items = [])
    {
    }

    /**
     * Get all items.
     *
     * @return array<TKey, TValue>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get the first item.
     *
     * @return TValue|null
     */
    public function first(): mixed
    {
        if (empty($this->items)) {
            return null;
        }
        return reset($this->items);
    }

    /**
     * Get the last item.
     *
     * @return TValue|null
     */
    public function last(): mixed
    {
        if (empty($this->items)) {
            return null;
        }
        return end($this->items);
    }

    /**
     * Pluck a specific column/key from all items.
     *
     * @return array<int, mixed>
     */
    public function pluck(string $key): array
    {
        $plucked = [];
        foreach ($this->items as $item) {
            $plucked[] = Helper::getValue($item, $key);
        }
        return $plucked;
    }

    /**
     * Sum the values of a given key.
     */
    public function sum(?string $key = null): float|int
    {
        $sum = 0;
        foreach ($this->items as $item) {
            $value = $key === null ? $item : Helper::getValue($item, $key);
            if (is_numeric($value)) {
                $sum += $value;
            }
        }
        return $sum;
    }

    /**
     * Calculate the average of a given key.
     */
    public function avg(?string $key = null): float|int
    {
        $count = $this->count();
        if ($count === 0) {
            return 0;
        }
        return $this->sum($key) / $count;
    }

    /**
     * Find the minimum value of a given key.
     */
    public function min(?string $key = null): mixed
    {
        $values = $key === null ? $this->items : $this->pluck($key);
        $filtered = array_filter($values, fn($val) => $val !== null);
        if (empty($filtered)) {
            return null;
        }
        return min($filtered);
    }

    /**
     * Find the maximum value of a given key.
     */
    public function max(?string $key = null): mixed
    {
        $values = $key === null ? $this->items : $this->pluck($key);
        $filtered = array_filter($values, fn($val) => $val !== null);
        if (empty($filtered)) {
            return null;
        }
        return max($filtered);
    }

    /**
     * Count the items.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Convert the collection to JSON.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options) ?: '[]';
    }

    /**
     * Convert the collection to array.
     *
     * @return array<TKey, mixed>
     */
    public function toArray(): array
    {
        return array_map(function ($item) {
            if ($item instanceof self) {
                return $item->toArray();
            }
            if ($item instanceof JsonSerializable) {
                return $item->jsonSerialize();
            }
            if (is_object($item) && method_exists($item, 'toArray')) {
                return $item->toArray();
            }
            return $item;
        }, $this->items);
    }

    /**
     * ArrayAccess implementation: offsetExists
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * ArrayAccess implementation: offsetGet
     */
    #[ReturnTypeWillChange]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * ArrayAccess implementation: offsetSet
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * ArrayAccess implementation: offsetUnset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * IteratorAggregate implementation: getIterator
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * JsonSerializable implementation: jsonSerialize
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
