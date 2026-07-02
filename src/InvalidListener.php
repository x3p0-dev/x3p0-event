<?php

/**
 * Invalid listener exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-event
 */

declare(strict_types=1);

namespace X3P0\Event;

use InvalidArgumentException;

/**
 * Thrown when a value registered as a listener is neither a callable nor the
 * class name of a `Listener`. It extends `InvalidArgumentException` because it
 * reports a bad argument handed to the registry at registration time.
 */
final class InvalidListener extends InvalidArgumentException implements EventException
{
}
