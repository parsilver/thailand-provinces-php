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
    /** Deeper attribute paths than this are refused (fail closed); no form nests so deep. */
    private const MAX_SEGMENTS = 64;

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
     * the row key at the same level as the attribute's, so
     * "addresses.*.province" pairs with "addresses.2.district".
     *
     * $unresolved is set when the parent path has a "*" and this row's parent
     * cannot be found unambiguously. The caller fails then instead of passing
     * (#20): only a parent that is present but empty defers to its own rule.
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
     * @param array<string, mixed> $data
     * @return list<array-key>|null
     */
    private function parentKeys(array $data, string $attribute): ?array
    {
        $keys = $this->dataKeys($data, $attribute);
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
     * The value at an attribute, read the same way as the parent.
     *
     * @param array<string, mixed> $data
     */
    public function valueAt(array $data, string $attribute): mixed
    {
        $keys = $this->dataKeys($data, $attribute);
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
     * The real data keys an attribute runs through, or null when there is not
     * exactly one way (PR #21 review).
     *
     * Laravel escapes a "." or "*" inside a data key with its own placeholder
     * (Validator::$placeholderHash), then hands a string rule the escaped
     * attribute but an object rule the unescaped one. So:
     *
     * - Without a validator, or for a string rule, the attribute is taken as
     *   it is: no key in escaped data contains a dot, so its segments are the
     *   keys, looked up exactly.
     * - For an object rule, every way of grouping the attribute's segments into
     *   keys is tried, each group escaped with the real placeholder and looked
     *   up exactly. A grouping counts only when it ends on a field (not a row).
     *   Exactly one may count; with none or several the parent is unresolved
     *   and the rule fails closed. Nothing is matched by pattern, so a key that
     *   merely looks escaped is never taken for an escaped one, and nothing
     *   scans a level's keys, so the cost does not grow with the row count.
     *
     * @param array<string, mixed> $data
     * @return list<array-key>|null
     */
    private function dataKeys(array $data, string $attribute): ?array
    {
        // A path no form has: refuse it before grouping, which tries up to
        // n spans per level. One 20,000-dot key took 16 s before this cap.
        if (substr_count($attribute, '.') >= self::MAX_SEGMENTS) {
            return null;
        }
        $segments = explode('.', $attribute);
        $hash = $this->placeholderHash();
        $found = [];
        $walk = function (mixed $node, int $from, array $keys) use (&$walk, &$found, $segments, $hash): void {
            if (count($found) > 1) {
                return;
            }
            if ($from === count($segments)) {
                if (!is_array($node)) {
                    $found[] = $keys;
                }

                return;
            }
            if (!is_array($node)) {
                return;
            }
            $last = $hash === null ? $from + 1 : count($segments);
            $key = '';
            for ($to = $from + 1; $to <= $last; $to++) {
                // The key spanning segments $from..$to-1, grown by one segment
                // each step, escaped as Laravel escapes data keys.
                $segment = $hash === null ? $segments[$to - 1] : str_replace('*', '__asterisk__' . $hash, $segments[$to - 1]);
                $key = $to === $from + 1 ? $segment : $key . '__dot__' . $hash . $segment;
                if (array_key_exists($key, $node)) {
                    $walk($node[$key], $to, [...$keys, $key]);
                }
            }
        };
        $walk($data, 0, []);

        return count($found) === 1 ? $found[0] : null;
    }

    /**
     * Laravel's escaping placeholder for this validation run, when the rule was
     * handed the validator: an object rule, or the string rules' message
     * replacer, both of which get the attribute unescaped. The string rules'
     * extension gets no validator and the escaped attribute.
     * Read exactly: matching any lookalike would let a literal key pass for an
     * escaped one.
     */
    private function placeholderHash(): ?string
    {
        if ($this->validator === null) {
            return null;
        }
        // A protected static of Laravel's Validator. If a Laravel release
        // renames or removes it, this reads null: the attribute is then taken
        // as already escaped, a dotted row key matches no data key, and its
        // rule fails closed instead of passing. The dotted-key tests in
        // ValidationTest would turn red in CI.

        return \Closure::bind(static fn (): ?string => Validator::$placeholderHash ?? null, null, Validator::class)();
    }

    /** The parent path for display, with each "*" resolved to this row. */
    protected function resolveParentField(string $attribute): string
    {
        $field = (string) $this->parentField;
        if (!str_contains($field, '*')) {
            return $field;
        }
        $keys = $this->dataKeys($this->data, $attribute);
        $segments = explode('.', $field);
        foreach ($segments as $i => $segment) {
            if ($segment === '*') {
                $segments[$i] = isset($keys[$i]) ? $this->unescaped((string) $keys[$i]) : (explode('.', $attribute)[$i] ?? '*');
            }
        }

        return implode('.', $segments);
    }

    /** A data key for display, the real placeholder turned back into "." and "*". */
    private function unescaped(string $key): string
    {
        $hash = $this->placeholderHash();

        return $hash === null ? $key : str_replace(['__dot__' . $hash, '__asterisk__' . $hash], ['.', '*'], $key);
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
