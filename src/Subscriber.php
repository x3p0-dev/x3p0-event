<?php

/**
 * Subscriber contract.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-event
 */

declare(strict_types=1);

namespace X3P0\Event;

/**
 * Contract for an object that registers several listeners at once. Rather than
 * wiring each listener individually, a subscriber declares the events it handles
 * and the method that handles each one, and the `ListenerProvider` registers
 * them all together. Subscribing this way also lets every listener be removed in
 * a single `unsubscribe()` call.
 */
interface Subscriber
{
	/**
	 * Returns a map of event class name to handler, where each handler is
	 * either the name of a method on this subscriber or an array with a
	 * `method` key and an optional `priority` key. A lower priority number runs
	 * earlier; the default is `0` when no priority is given. The priority may be
	 * a plain integer or a `ListenerPriority` case.
	 *
	 * @return array<class-string, string|array{method: string, priority?: int|ListenerPriority}>
	 */
	public function getSubscribedEvents(): array;
}
