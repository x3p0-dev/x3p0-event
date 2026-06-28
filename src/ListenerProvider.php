<?php

/**
 * Listener provider contract.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-event
 */

declare(strict_types=1);

namespace X3P0\Event;

/**
 * Contract for the object that maps an event to the listeners interested in it.
 * A listener is any callable that accepts the event as its only argument; the
 * provider decides which listeners apply to a given event and the order in which
 * they run.
 */
interface ListenerProvider
{
	/**
	 * Returns an iterable of callable listeners for the given event, in the
	 * order they should be called.
	 *
	 * @return iterable<callable>
	 */
	public function getListenersForEvent(object $event): iterable;
}
