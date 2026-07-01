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

use LogicException;
use X3P0\Event\Provider\PriorityListenerRegistry;

/**
 * Dispatches events synchronously, in process, in the calling request. It asks
 * the listener provider for the listeners interested in an event and calls each
 * one in turn, passing the event. When the event is stoppable, it stops calling
 * listeners the moment propagation has been stopped. The same event instance is
 * returned so callers can read whatever the listeners changed on it.
 */
final class EventDispatcher implements ListenerAwareDispatcher
{
	/**
	 * Stores the listener provider that supplies listeners for each event.
	 * Defaults to a fresh `PriorityListenerRegistry`, so a dispatcher built
	 * without arguments is ready to register listeners on immediately; pass a
	 * provider explicitly to dispatch from a different or combined source.
	 */
	public function __construct(
		private readonly ListenerProvider $listeners = new PriorityListenerRegistry()
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

	/**
	 * Registers a listener for the given event type. This is a convenience
	 * facade over the listener provider, so callers that hold the dispatcher
	 * need not also hold a reference to the provider. A lower priority number
	 * runs earlier; listeners sharing a priority run in registration order.
	 */
	public function listen(string $eventType, callable $listener, int $priority = 0): void
	{
		$this->registry()->listen($eventType, $listener, $priority);
	}

	/**
	 * Registers every listener a subscriber declares, as a facade over the
	 * listener provider.
	 */
	public function subscribe(Subscriber $subscriber): void
	{
		$this->registry()->subscribe($subscriber);
	}

	/**
	 * Removes every listener previously registered by the given subscriber,
	 * as a facade over the listener provider.
	 */
	public function unsubscribe(Subscriber $subscriber): void
	{
		$this->registry()->unsubscribe($subscriber);
	}

	/**
	 * Removes listeners for the given event type — a specific one when a
	 * listener is passed, or all of them when it is omitted — as a facade over
	 * the listener provider.
	 */
	public function forget(string $eventType, ?callable $listener = null): void
	{
		$this->registry()->forget($eventType, $listener);
	}

	/**
	 * Returns the listener provider as a registry so listeners can be
	 * registered through it, throwing when the provider is read-only (such
	 * as a lone hook bridge) and accepts no registrations.
	 */
	private function registry(): ListenerRegistry
	{
		if (! $this->listeners instanceof ListenerRegistry) {
			throw new LogicException(
				'Cannot register listeners: the dispatcher\'s listener provider does not implement ' . ListenerRegistry::class . '.'
			);
		}

		return $this->listeners;
	}
}
