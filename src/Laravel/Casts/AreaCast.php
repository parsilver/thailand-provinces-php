<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Laravel\Casts;

use Farzai\ThailandAddress\Model as Area;
use Farzai\ThailandAddress\Thailand;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores an area's DOPA code and reads it back as the model.
 *
 * Setting an unknown code throws, as Laravel's enum casts do. Reading one back
 * gives null: a stored code can stop existing when DOPA abolishes an area and
 * the data is refreshed, and a row read must not fail over it.
 *
 * @template T of Area
 * @implements CastsAttributes<T|null, T|string|int|null>
 */
abstract class AreaCast implements CastsAttributes
{
    /** @return 'province'|'district'|'subdistrict' */
    abstract protected function level(): string;

    /** @return class-string<T> */
    abstract protected function modelClass(): string;

    /**
     * @param array<string, mixed> $attributes
     * @return T|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Area
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Thailand::repository()->{$this->level()}((string) $value);
    }

    /** @param array<string, mixed> $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        // An empty string is what a blank form field submits: no area.
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof Area) {
            $class = $this->modelClass();
            $value instanceof $class || throw new \InvalidArgumentException("{$key} expects a {$this->level()}, got " . $value::class);

            return $value->code;
        }
        if (!is_string($value) && !is_int($value)) {
            throw new \InvalidArgumentException("{$key} expects a {$this->level()} or its code");
        }

        return Thailand::repository()->{$this->level() . 'OrFail'}($value)->code;
    }
}
