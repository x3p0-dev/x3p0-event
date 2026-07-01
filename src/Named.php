<?php

/**
 * Named event trait.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-event
 */

declare(strict_types=1);

namespace X3P0\Event;

/**
 * Ready-made `NamedEvent` implementation that reads the name from a `NAME`
 * class constant. A using class defines the constant and the name comes for
 * free:
 *
 *     final class OrderPlaced implements NamedEvent
 *     {
 *         use Named;
 *         public const NAME = 'order.placed';
 *     }
 *
 * Keeping the name in a constant means listeners register with `OrderPlaced::NAME`
 * instead of a bare string, so it stays greppable and survives renaming.
 */
trait Named
{
	/**
	 * Returns the name declared in the using class's `NAME` constant.
	 */
	public function eventName(): string
	{
		return static::NAME;
	}
}
