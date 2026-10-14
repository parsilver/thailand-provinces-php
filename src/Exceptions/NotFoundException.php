<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Exceptions;

/**
 * Thrown by the *OrFail lookups when no area has the code, and when a
 * serialized model is read back after its code stopped existing.
 */
final class NotFoundException extends \RuntimeException
{
    public static function for(string $level, int|string $code): self
    {
        return new self("No {$level} has the code \"{$code}\".");
    }

    /** @param class-string $class */
    public static function staleSerialized(string $class, string $code, self $previous): self
    {
        return new self(
            "Cannot unserialize {$class}: its serialized code \"{$code}\" no longer exists in the data. "
            . 'Its area was abolished, or the payload was written against other data.',
            0,
            $previous,
        );
    }
}
