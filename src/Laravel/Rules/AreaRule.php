<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Laravel\Rules;

use Closure;
use Farzai\ThailandAddress\AddressRepository;
use Farzai\ThailandAddress\Thailand;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

/**
 * The shared shape of the four rules: a value check, plus an optional check
 * against another field of the same request (the "parent" field). The parent
 * check passes when the parent field is empty or invalid, so that field's own
 * rule reports it and the user sees one error, not two.
 *
 * The object rule and the string rule (e.g. "thai_district:province") both call
 * failure(), so they cannot disagree.
 */
abstract class AreaRule implements ValidationRule, DataAwareRule, ValidatorAwareRule
{
    /** @var array<string, mixed> */
    protected array $data = [];

    protected ?Validator $validator = null;

    /** The other field this value must agree with, as a dot path ("*" allowed). */
    protected ?string $parentField = null;

    final public function __construct(protected ?AddressRepository $repository = null)
    {
    }

    public static function make(?AddressRepository $repository = null): static
    {
        return new static($repository);
    }

    /**
     * The translation key (under thailand-address::validation) of the reason
     * $value fails, or null when it passes.
     *
     * @param array<string, mixed> $data the whole request
     */
    abstract public function failure(mixed $value, array $data, string $attribute = ''): ?string;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $key = $this->failure($value, $this->data, $attribute);
        if ($key !== null) {
            $fail('thailand-address::validation.' . $key)->translate(['other' => $this->otherName($attribute)]);
        }
    }

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function setValidator(\Illuminate\Contracts\Validation\Validator $validator): static
    {
        $this->validator = $validator instanceof Validator ? $validator : null;

        return $this;
    }

    protected function repository(): AddressRepository
    {
        return $this->repository ?? Thailand::repository();
    }

    /** A request value as a code: digits only, else null. */
    protected static function code(mixed $value): ?string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        return is_string($value) && preg_match('/^\d+$/', $value) === 1 ? $value : null;
    }

    /**
     * The parent field's value for this attribute. A "*" in the parent path takes
     * the index at the same position in the attribute, so
     * "addresses.*.province" pairs with "addresses.2.district".
     *
     * @param array<string, mixed> $data
     */
    protected function parentValue(array $data, string $attribute): mixed
    {
        return $this->parentField === null ? null : Arr::get($data, $this->resolveParentField($attribute));
    }

    protected function resolveParentField(string $attribute): string
    {
        $field = (string) $this->parentField;
        if (!str_contains($field, '*')) {
            return $field;
        }
        $attributeSegments = explode('.', $attribute);
        $segments = explode('.', $field);
        foreach ($segments as $i => $segment) {
            if ($segment === '*' && isset($attributeSegments[$i])) {
                $segments[$i] = $attributeSegments[$i];
            }
        }

        return implode('.', $segments);
    }

    /** The parent field's display name for this attribute, its wildcards resolved. */
    public function otherName(string $attribute): string
    {
        if ($this->parentField === null) {
            return '';
        }
        $field = $this->resolveParentField($attribute);

        return $this->validator?->getDisplayableAttribute($field) ?? str_replace('_', ' ', $field);
    }
}
