<?php

/**
 * Dispatcher contract.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-event
 */

declare(strict_types=1);

namespace X3P0\Event;

/**
 * Contract for the object that dispatches an event to its listeners. An event
 * is any object; the dispatcher hands it to each registered listener in turn and
 * returns the same instance, which listeners may have mutated.
 */
interface Dispatcher
{
	/**
	 * Provides the given event to all relevant listeners and returns it. If
	 * the event implements `StoppableEvent`, dispatch stops as soon as
	 * propagation has been stopped.
	 *
	 * The same event instance is always returned, so the concrete event type
	 * is preserved for static analysis: `dispatch(new Foo())` is typed `Foo`.
	 *
	 * @template TEvent of object
	 * @param    TEvent $event
	 * @return   TEvent
	 */
	public function dispatch(object $event): object;
}
