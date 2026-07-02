<?php

/**
 * Non-invokable listener exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-event
 */

declare(strict_types=1);

namespace X3P0\Event;

use LogicException;

/**
 * Thrown when a listener registered by class name resolves to an object that
 * cannot be called — a `Listener` must define `__invoke()`. It extends
 * `LogicException` because a non-invokable listener class is a programming
 * error in the wiring, not a runtime input problem.
 */
final class NotInvokable extends LogicException implements EventException
{
}
