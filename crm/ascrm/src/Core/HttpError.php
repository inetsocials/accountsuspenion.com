<?php
declare(strict_types=1);

namespace DR\Core;

/** Thrown by controllers to render an error page with an HTTP status code. */
final class HttpError extends \RuntimeException
{
}
