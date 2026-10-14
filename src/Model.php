<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

/**
 * What every administrative area has: its DOPA code and its Thai and English
 * names, exactly as DOPA publishes them. Relations resolve lazily through the
 * repository that made the model.
 *
 * @implements Arrayable<string, mixed>
 */
abstract class Model implements Arrayable, Jsonable, \JsonSerializable
{
    public function __construct(
        protected readonly AddressRepository $repository,
        public readonly string $code,
        public readonly string $nameTh,
        public readonly string $nameEn,
    ) {
    }

    /** The name in 'th' or 'en'. */
    public function name(string $locale = 'th'): string
    {
        return match ($locale) {
            'th' => $this->nameTh,
            'en' => $this->nameEn,
            default => throw new \InvalidArgumentException("Locale must be 'th' or 'en', not '{$locale}'"),
        };
    }

    /** @return array<string, mixed> */
    abstract public function toArray(): array;

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson($options = 0): string
    {
        return (string) json_encode($this->jsonSerialize(), $options | JSON_THROW_ON_ERROR);
    }

    /** Relations read as properties, as in Eloquent: $subdistrict->district->province. */
    public function __get(string $name): mixed
    {
        if (in_array($name, $this->relations(), true)) {
            return $this->{$name}();
        }
        throw new \LogicException(sprintf('Undefined property %s::$%s', static::class, $name));
    }

    public function __isset(string $name): bool
    {
        return in_array($name, $this->relations(), true);
    }

    /** @return list<string> the parent relations readable as properties */
    abstract protected function relations(): array;
}
