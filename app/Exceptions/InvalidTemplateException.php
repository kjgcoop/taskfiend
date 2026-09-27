<?php

namespace App\Exceptions;

/**
 * A zip that isn't a usable project template. The message is safe to show
 * to the user as-is.
 */
class InvalidTemplateException extends \RuntimeException
{
}
