<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress;

/**
 * An ordered, read-only set of areas keyed by code.
 *
 * @template T of Area
 * @implements \IteratorAggregate<array-key, T>
 */
final class Collection implements \Countable, \IteratorAggregate, \JsonSerializable
{
    /** @param array<array-key, T> $items keyed by code, in DOPA code order */
    public function __construct(private readonly array $items = [])
    {
    }

    /** @return T|null the area with this code, or null when there is none */
    public function find(int|string $code): ?Area
    {
        return $this->items[(string) $code] ?? null;
    }

    /**
     * The areas whose Thai or English name contains $query, ignoring case and
     * surrounding whitespace. A blank query matches nothing.
     *
     * @return self<T>
     */
    public function search(string $query): self
    {
        $needle = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $query)));
        if ($needle === '') {
            return new self();
        }

        return $this->filter(static fn (Area $area): bool => str_contains(mb_strtolower($area->nameTh), $needle)
            || str_contains(mb_strtolower($area->nameEn), $needle));
    }

    /**
     * @param callable(T): bool $predicate
     * @return self<T>
     */
    public function filter(callable $predicate): self
    {
        return new self(array_filter($this->items, $predicate));
    }

    /** @return T|null */
    public function first(): ?Area
    {
        return $this->items === [] ? null : $this->items[array_key_first($this->items)];
    }

    /** @return list<T> */
    public function all(): array
    {
        return array_values($this->items);
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_map('strval', array_keys($this->items));
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return \ArrayIterator<array-key, T> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    /** @return list<T> */
    public function jsonSerialize(): array
    {
        return $this->all();
    }
}
