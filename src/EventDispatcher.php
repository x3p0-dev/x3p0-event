<?php

/**
 * Event dispatcher class.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-event
 */

declare(strict_types=1);

namespace X3P0\Event;

/**
 * Dispatches events synchronously, in process, in the calling request. It asks
 * the listener provider for the listeners interested in an event and calls each
 * one in turn, passing the event. When the event is stoppable, it stops calling
 * listeners the moment propagation has been stopped. The same event instance is
 * returned so callers can read whatever the listeners changed on it.
 */
final class EventDispatcher implements Dispatcher
{
	/**
	 * Stores the listener provider that supplies listeners for each event.
	 */
	public function __construct(
		private readonly ListenerProvider $listeners
	) {}

	/**
	 * @inheritDoc
	 */
	public function dispatch(object $event): object
	{
		$stoppable = $event instanceof StoppableEvent;

		foreach ($this->listeners->getListenersForEvent($event) as $listener) {
			if ($stoppable && $event->isPropagationStopped()) {
				break;
			}

			$listener($event);
		}

		return $event;
	}
}
