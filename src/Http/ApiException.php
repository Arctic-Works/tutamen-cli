<?php

declare(strict_types=1);

namespace Tutamen\Cli\Http;

use RuntimeException;

/**
 * An API call failed in a way the user should see (bad token, server down,
 * snapshot rejected). The scan command turns this into exit code 2.
 */
final class ApiException extends RuntimeException
{
}
