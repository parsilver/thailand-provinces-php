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
     * the row key at the same position in the attribute, so
     * "addresses.*.province" pairs with "addresses.2.district".
     *
     * $unresolved is set when the parent path has a "*" and no such field exists
     * in this row. The caller fails then, instead of passing (#20): only a parent
     * that is present but empty defers to its own rule.
     *
     * @param array<string, mixed> $data
     */
    protected function parentValue(array $data, string $attribute, ?bool &$unresolved = null): mixed
    {
        $unresolved = false;
        if ($this->parentField === null) {
            return null;
        }
        if (!str_contains($this->parentField, '*')) {
            return Arr::get($data, $this->parentField);
        }
        $path = $this->parentKeys($data, $attribute);
        if ($path === null) {
            $unresolved = true;

            return null;
        }
        $node = $data;
        foreach ($path as $key) {
            if (!is_array($node) || !array_key_exists($key, $node)) {
                $unresolved = true;

                return null;
            }
            $node = $node[$key];
        }

        return $node;
    }

    /**
     * The real data keys of the parent field for this attribute, or null.
     *
     * The attribute cannot simply be split on ".": a row key may contain a dot.
     * Laravel escapes such a key in the data with a placeholder but gives an
     * object rule the unescaped attribute ("addresses.a.b.district"). So the
     * attribute is matched against the data one level at a time, taking as many
     * of its segments as the next data key spells once its placeholders are
     * restored. A "*" in the parent path then takes the real key at its level.
     *
     * @param array<string, mixed> $data
     * @return list<array-key>|null
     */
    private function parentKeys(array $data, string $attribute): ?array
    {
        $keys = self::dataKeys($data, $attribute);
        if ($keys === null) {
            return null;
        }
        $path = [];
        foreach (explode('.', (string) $this->parentField) as $level => $segment) {
            if ($segment === '*') {
                if (!isset($keys[$level])) {
                    return null;
                }
                $path[] = $keys[$level];
            } else {
                $path[] = $segment;
            }
        }

        return $path;
    }

    /**
     * The value at an attribute, escaped or not, read the same way as the
     * parent: by the real data keys.
     *
     * @param array<string, mixed> $data
     */
    public static function valueAt(array $data, string $attribute): mixed
    {
        $keys = self::dataKeys($data, $attribute);
        if ($keys === null) {
            return null;
        }
        $node = $data;
        foreach ($keys as $key) {
            $node = $node[$key];
        }

        return $node;
    }

    /**
     * The real data keys an attribute path runs through, or null when it is not
     * in the data. Matched one level at a time, each data key taking as many of
     * the attribute's segments as it spells once its placeholders are restored.
     *
     * @param array<string, mixed> $data
     * @return list<array-key>|null
     */
    private static function dataKeys(array $data, string $attribute): ?array
    {
        // An object rule gets the attribute unescaped, a string rule escaped.
        $segments = explode('.', self::restored($attribute));
        $keys = [];
        $node = $data;
        for ($i = 0, $n = count($segments); $i < $n;) {
            if (!is_array($node)) {
                return null;
            }
            $match = null;
            foreach (array_keys($node) as $key) {
                $spelled = explode('.', self::restored((string) $key));
                if (array_slice($segments, $i, count($spelled)) === $spelled
                    && ($match === null || count($spelled) > $match[1])) {
                    $match = [$key, count($spelled)];
                }
            }
            if ($match === null) {
                return null;
            }
            $keys[] = $match[0];
            $node = $node[$match[0]];
            $i += $match[1];
        }

        return $keys;
    }

    /**
     * A data key with Laravel's escaping placeholders turned back into "." and
     * "*". The placeholder is "__dot__" plus Validator's Str::random() hash, 16
     * alphanumerics; the length must be exact, or "a.b" escaped as
     * "a__dot__<hash>b" would lose its "b". The dotted-key tests in
     * ValidationTest pin this against each Laravel version CI runs.
     */
    private static function restored(string $key): string
    {
        return (string) preg_replace(['/__dot__[A-Za-z0-9]{16}/', '/__asterisk__[A-Za-z0-9]{16}/'], ['.', '*'], $key);
    }

    /** The parent path for display: a "*" takes the row key at its position. */
    protected function resolveParentField(string $attribute): string
    {
        $field = (string) $this->parentField;
        if (!str_contains($field, '*')) {
            return $field;
        }
        $keys = $this->parentKeys($this->data, $attribute);
        if ($keys !== null) {
            return implode('.', array_map(fn ($key) => self::restored((string) $key), $keys));
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
