<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Exceptions;

/** Thrown by the *OrFail lookups when no area has the code. */
final class NotFoundException extends \RuntimeException
{
    public static function for(string $level, int|string $code): self
    {
        return new self("No {$level} has the code \"{$code}\".");
    }
}
