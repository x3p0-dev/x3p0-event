<?php

/**
 * Event exception contract.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-event
 */

declare(strict_types=1);

namespace X3P0\Event;

use Throwable;

/**
 * Marker interface implemented by every exception this library throws, so a
 * caller can catch any failure from the event system in one place —
 * `catch (EventException $e)` — without knowing which concrete type was thrown.
 *
 * Each concrete exception also extends the SPL base that best describes its
 * cause (`InvalidArgumentException`, `LogicException`, and so on), so code that
 * catches those broader types keeps working; this interface only adds a
 * library-specific handle on top.
 */
interface EventException extends Throwable
{
}
