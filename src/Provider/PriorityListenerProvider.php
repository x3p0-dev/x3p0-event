<?php

/**
 * Priority listener provider class.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-event
 */

declare(strict_types=1);

namespace X3P0\Event\Provider;

use SplObjectStorage;
use SplPriorityQueue;
use X3P0\Event\ListenerProvider;
use X3P0\Event\ListenerRegistry;
use X3P0\Event\Subscriber;

/**
 * Listener provider and registry that runs listeners in priority order.
 * Listeners are registered against an event type — a class or interface name —
 * and run, lowest priority number first, with ties broken by registration order.
 * An event matches a listener when the event's own class, any parent class, or
 * any implemented interface equals the registered type, so a listener on a base
 * type fires for every subtype.
 *
 * Two SPL structures do the heavy lifting: an `SplPriorityQueue` orders the
 * matching listeners at dispatch time, and an `SplObjectStorage` remembers which
 * listeners each subscriber added so `unsubscribe()` can remove them all at once.
 */
final class PriorityListenerProvider implements ListenerProvider, ListenerRegistry
{
	/**
	 * Stores listeners grouped by event type. Each entry records the callable
	 * listener, its priority, and a monotonic serial used to keep registration
	 * order stable when priorities tie.
	 *
	 * @var array<string, array<int, array{callable: callable, priority: int}>>
	 */
	private array $listeners = [];

	/**
	 * Stores the next serial number, incremented on each registration so every
	 * listener has a unique, ordered identifier.
	 */
	private int $serial = 0;

	/**
	 * Maps each subscriber object to the `[type, serial]` records it
	 * registered, so the whole set can be removed on `unsubscribe()`.
	 *
	 * @var SplObjectStorage<object, array<int, array{type: string, serial: int}>>
	 */
	private SplObjectStorage $subscribers;

	/**
	 * Sets up the subscriber storage.
	 */
	public function __construct()
	{
		$this->subscribers = new SplObjectStorage();
	}

	/**
	 * Registers a listener for the given event type. A lower priority number
	 * runs earlier; listeners sharing a priority run in registration order.
	 */
	public function listen(string $eventType, callable $listener, int $priority = 0): void
	{
		$this->add($eventType, $listener, $priority);
	}

	/**
	 * Registers every listener a subscriber declares and remembers them so they
	 * can be removed together. A handler may be a method name or an array with a
	 * `method` key and an optional `priority` key.
	 */
	public function subscribe(Subscriber $subscriber): void
	{
		$records = [];

		foreach ($subscriber->getSubscribedEvents() as $eventType => $handler) {
			[$method, $priority] = is_array($handler)
				? [$handler['method'], $handler['priority'] ?? 0]
				: [$handler, 0];

			$records[] = [
				'type'   => $eventType,
				'serial' => $this->add($eventType, [$subscriber, $method], $priority)
			];
		}

		$this->subscribers[$subscriber] = $records;
	}

	/**
	 * Removes every listener previously registered by the given subscriber.
	 */
	public function unsubscribe(Subscriber $subscriber): void
	{
		if (! isset($this->subscribers[$subscriber])) {
			return;
		}

		foreach ($this->subscribers[$subscriber] as $record) {
			unset($this->listeners[$record['type']][$record['serial']]);
		}

		unset($this->subscribers[$subscriber]);
	}

	/**
	 * @inheritDoc
	 */
	public function getListenersForEvent(object $event): iterable
	{
		$queue = new SplPriorityQueue();

		foreach ($this->matchingTypes($event) as $type) {
			foreach ($this->listeners[$type] ?? [] as $serial => $registered) {
				// The queue dequeues the highest priority first, so both
				// values are negated: the lowest priority number comes out
				// first, and the earliest serial wins on ties.
				$queue->insert($registered['callable'], [
					-$registered['priority'],
					-$serial
				]);
			}
		}

		yield from $queue;
	}

	/**
	 * Stores a listener and returns the serial assigned to it.
	 */
	private function add(string $eventType, callable $listener, int $priority): int
	{
		$serial = $this->serial++;

		$this->listeners[$eventType][$serial] = [
			'callable' => $listener,
			'priority' => $priority
		];

		return $serial;
	}

	/**
	 * Returns the event types a listener may be registered against to match the
	 * given event: its own class, every parent class, and every implemented
	 * interface.
	 *
	 * @return array<int, string>
	 */
	private function matchingTypes(object $event): array
	{
		return [
			$event::class,
			...array_values(class_parents($event)),
			...array_values(class_implements($event))
		];
	}
}
