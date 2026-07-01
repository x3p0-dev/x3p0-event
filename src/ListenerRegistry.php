<?php

/**
 * Listener registry contract.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-event
 */

declare(strict_types=1);

namespace X3P0\Event;

/**
 * Contract for the write side of the listener system: registering listeners and
 * subscribers, and removing them again. It is the counterpart to the read-only
 * `ListenerProvider`, which only answers which listeners apply to an event. A
 * provider may implement both to be the single place listeners are stored, or
 * just `ListenerProvider` when it sources listeners from elsewhere (such as the
 * WordPress hook bridge) and accepts no registrations.
 */
interface ListenerRegistry
{
	/**
	 * Registers a listener for the given event type. The listener may be any
	 * callable, or the class name of a `Listener` to resolve lazily when the
	 * event first fires. A lower priority number runs earlier; listeners
	 * sharing a priority run in registration order. The priority may be a plain
	 * integer or a `ListenerPriority` case.
	 */
	public function listen(string $eventType, callable|string $listener, int|ListenerPriority $priority = 0): void;

	/**
	 * Registers a listener that runs at most once: it removes itself before
	 * it is called, so it fires for the first matching event and never
	 * again. In every other respect it behaves like `listen()`.
	 */
	public function listenOnce(string $eventType, callable|string $listener, int|ListenerPriority $priority = 0): void;

	/**
	 * Registers every listener a subscriber declares, so the whole set can
	 * be removed together with `unsubscribe()`.
	 */
	public function subscribe(Subscriber $subscriber): void;

	/**
	 * Registers a subscriber's listeners so each one runs at most once,
	 * removing itself after it fires. Every declared handler is independent —
	 * one firing does not remove the others. As with `subscribe()`, the whole
	 * set can still be removed early with `unsubscribe()`.
	 */
	public function subscribeOnce(Subscriber $subscriber): void;

	/**
	 * Removes every listener previously registered by the given subscriber.
	 */
	public function unsubscribe(Subscriber $subscriber): void;

	/**
	 * Removes listeners registered for the given event type. When a listener is
	 * given, only listeners equal to it (by identity) are removed; when it is
	 * omitted, every listener for the type is removed. Listeners added as an
	 * inline closure can only be removed by passing back the same closure
	 * instance.
	 */
	public function forget(string $eventType, ?callable $listener = null): void;
}
