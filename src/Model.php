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

    /**
     * A model serializes as its code alone. Its repository holds every area in
     * the country, so serializing it along (a queued job, a cache entry) would
     * write megabytes for one model.
     *
     * @return array{code: string}
     */
    public function __serialize(): array
    {
        return ['code' => $this->code];
    }

    /**
     * Rebuilt from the code through Thailand::repository(), so it is bound to
     * the repository in use when it is read back.
     *
     * @param array{code: string} $data
     * @throws Exceptions\NotFoundException when that repository has no such code
     */
    public function __unserialize(array $data): void
    {
        $source = static::lookup(Thailand::repository(), (string) $data['code']);
        $this->repository = $source->repository;
        $this->code = $source->code;
        $this->nameTh = $source->nameTh;
        $this->nameEn = $source->nameEn;
        $this->restoreFrom($source);
    }

    /**
     * What var_dump() and print_r() show: the model's own fields, not the
     * repository it points to.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return $this->toArray();
    }

    /** The model of this level with this code, in the given repository. */
    abstract protected static function lookup(AddressRepository $repository, string $code): static;

    /** Copies this level's own read-only fields from $source while unserializing. */
    abstract protected function restoreFrom(self $source): void;
}
