<?php

/**
 * Priority listener registry implementation trait.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-event
 */

declare(strict_types=1);

namespace X3P0\Event\Listener\Registry;

use Closure;
use ReflectionException;
use ReflectionFunction;
use ReflectionNamedType;
use SplObjectStorage;
use X3P0\Event\InvalidListener;
use X3P0\Event\Listener\Listener;
use X3P0\Event\Listener\ListenerPriority;
use X3P0\Event\Listener\Subscriber;
use X3P0\Event\NamedEvent;
use X3P0\Event\NotInvokable;

/**
 * Shared implementation of a priority-ordered listener registry, so registry
 * classes that differ only in how they are constructed can compose it without a
 * shared base class. A using class implements `ListenerProvider` and
 * `ListenerRegistry`, calls `initListenerRegistry()` from its constructor, and
 * gains the full behavior — while staying `final`. `PriorityRegistry` is the
 * built-in user; a framework might add another whose constructor takes a
 * container to resolve class-name listeners.
 *
 * Listeners are registered against an event type — a class or interface name —
 * and run, lowest priority number first, with ties broken by registration order.
 * An event matches a listener when the event's own class, any parent class, or
 * any implemented interface equals the registered type, so a listener on a base
 * type fires for every subtype. A `NamedEvent` additionally matches listeners
 * registered against the string its `eventName()` returns.
 *
 * At dispatch time the matching listeners are sorted, ascending, by priority
 * and then registration order. An `SplObjectStorage` remembers which listeners
 * each subscriber added so `unsubscribe()` can remove them all at once.
 */
trait RegistersListeners
{
	/**
	 * Stores listeners grouped by event type. Each entry records the
	 * callable listener, its priority, and a monotonic serial used to keep
	 * registration order stable when priorities tie.
	 *
	 * @var array<string, array<int, array{callable: callable, priority: int}>>
	 */
	private array $listeners = [];

	/**
	 * Stores the next serial number, incremented on each registration so
	 * every listener has a unique, ordered identifier.
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
	 * Stores the optional resolver used to build listeners registered by
	 * class name. It receives the class name and returns the instance; when
	 * none is given, listeners are built with `new $class()`.
	 *
	 * @var ?Closure(class-string): object
	 */
	private readonly ?Closure $resolver;

	/**
	 * Sets up the subscriber storage and stores the optional listener
	 * resolver. A using class must call this from its constructor.
	 *
	 * @param ?Closure(class-string): object $resolver
	 */
	private function initListenerRegistry(?Closure $resolver = null): void
	{
		$this->subscribers = new SplObjectStorage();
		$this->resolver = $resolver;
	}

	/**
	 * Registers a listener for the given event type. A lower priority number
	 * runs earlier; listeners sharing a priority run in registration order.
	 */
	public function listen(string $eventType, callable|string $listener, int|ListenerPriority $priority = 0): void
	{
		$this->add($eventType, $this->toCallable($listener), $priority);
	}

	/**
	 * @inheritDoc
	 * @throws ReflectionException
	 */
	public function listenTo(callable $listener, int|ListenerPriority $priority = 0): void
	{
		$this->add($this->deriveEventType($listener), $listener, $priority);
	}

	/**
	 * @inheritDoc
	 */
	public function listenOnce(string $eventType, callable|string $listener, int|ListenerPriority $priority = 0): void
	{
		$this->add(
			$eventType,
			$this->onceListener($eventType, $this->toCallable($listener)),
			$priority
		);
	}

	/**
	 * @inheritDoc
	 * @throws ReflectionException
	 */
	public function listenOnceTo(callable $listener, int|ListenerPriority $priority = 0): void
	{
		$eventType = $this->deriveEventType($listener);

		$this->add(
			$eventType,
			$this->onceListener($eventType, $listener),
			$priority
		);
	}

	/**
	 * @inheritDoc
	 */
	public function forget(string $eventType, ?callable $listener = null): void
	{
		if (! isset($this->listeners[$eventType])) {
			return;
		}

		if ($listener === null) {
			unset($this->listeners[$eventType]);
			return;
		}

		foreach ($this->listeners[$eventType] as $serial => $registered) {
			if ($registered['callable'] === $listener) {
				unset($this->listeners[$eventType][$serial]);
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function subscribe(Subscriber $subscriber): void
	{
		$this->subscribeEach($subscriber, false);
	}

	/**
	 * @inheritDoc
	 */
	public function subscribeOnce(Subscriber $subscriber): void
	{
		$this->subscribeEach($subscriber, true);
	}

	/**
	 * @inheritDoc
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
		$matched = [];

		foreach ($this->matchingTypes($event) as $type) {
			foreach ($this->listeners[$type] ?? [] as $serial => $registered) {
				$matched[] = [
					'priority' => $registered['priority'],
					'serial'   => $serial,
					'callable' => $registered['callable']
				];
			}
		}

		// Lowest priority number first, ties broken by registration
		// order (the serial). Both sort ascending.
		usort($matched, static fn (array $a, array $b): int =>
			[$a['priority'], $a['serial']] <=> [$b['priority'], $b['serial']]);

		foreach ($matched as $entry) {
			yield $entry['callable'];
		}
	}

	/**
	 * @inheritDoc
	 */
	public function hasListeners(string $eventType): bool
	{
		foreach ($this->typesForClass($eventType) as $type) {
			if (! empty($this->listeners[$type])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Registers every handler a subscriber declares and records the serials
	 * so `unsubscribe()` can remove them together. When `$once` is true, each
	 * handler is wrapped to run at most once, exactly as `listenOnce()` does.
	 */
	private function subscribeEach(Subscriber $subscriber, bool $once): void
	{
		$records = [];

		foreach ($subscriber->getSubscribedEvents() as $eventType => $handler) {
			[$method, $priority] = is_array($handler)
				? [$handler['method'], $handler['priority'] ?? 0]
				: [$handler, 0];

			$listener = [$subscriber, $method];

			$records[] = [
				'type'   => $eventType,
				'serial' => $this->add(
					$eventType,
					$once ? $this->onceListener($eventType, $listener) : $listener,
					$priority
				)
			];
		}

		$this->subscribers[$subscriber] = $records;
	}

	/**
	 * Normalizes a registered listener to a callable. A callable is returned
	 * as-is; a `Listener` class name is turned into a closure that resolves
	 * the class the first time it runs (lazily, then cached) and invokes it.
	 * Any other string is rejected.
	 */
	private function toCallable(callable|string $listener): callable
	{
		if (is_string($listener) && is_a($listener, Listener::class, true)) {
			$instance = null;

			return function (object $event) use ($listener, &$instance): void {
				$instance ??= $this->resolveListener($listener);
				$instance($event);
			};
		}

		if (! is_callable($listener)) {
			throw new InvalidListener(
				'Listener must be a callable or the class name of a ' . Listener::class . '.'
			);
		}

		return $listener;
	}

	/**
	 * Derives the event type a listener handles from the declared type of its
	 * first parameter, so `listenTo()` callers need not repeat the class they
	 * already type-hinted. `Closure::fromCallable()` normalizes every callable
	 * shape — closure, `[$object, 'method']`, function name, invokable object,
	 * first-class callable — into one thing to reflect. The parameter must
	 * declare a single class or interface: a missing, builtin, union, or
	 * intersection type names no event to register against, so it is rejected
	 * rather than guessed at.
	 *
	 * @return class-string
	 * @throws ReflectionException
	 */
	private function deriveEventType(callable $listener): string
	{
		$parameters = (new ReflectionFunction($listener(...)))->getParameters();
		$type = ($parameters[0] ?? null)?->getType();

		if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
			throw new InvalidListener(
				'Cannot derive the event type from the listener: its first '
				. 'parameter must declare a single class or interface type. '
				. 'Register it with listen($eventType, $listener) instead.'
			);
		}

		return $type->getName();
	}

	/**
	 * Builds a listener registered by class name and returns it, ensuring the
	 * result is invokable. Uses the resolver when one was given, otherwise
	 * `new $class()`.
	 *
	 * @param class-string $class
	 */
	private function resolveListener(string $class): callable
	{
		$listener = $this->resolver ? ($this->resolver)($class) : new $class();

		if (! is_callable($listener)) {
			throw new NotInvokable(
				'A ' . Listener::class . ' must be invokable — define an __invoke() method.'
			);
		}

		return $listener;
	}

	/**
	 * Wraps a listener so it removes itself before it runs — the shared
	 * basis for `listenOnce()` and `subscribeOnce()`. Removing it first
	 * means it fires at most once even if it (or something it calls)
	 * dispatches the same event again, and even if it throws. The `&$once`
	 * reference lets the closure forget the exact callable stored.
	 */
	private function onceListener(string $eventType, callable $listener): callable
	{
		// Assigned and returned in a single statement so the closure
		// can still capture itself by reference (`&$once`) — it needs
		// its own identity to forget the exact callable stored.
		return $once = function (object $event) use (&$once, $eventType, $listener): void {
			$this->forget($eventType, $once);
			$listener($event);
		};
	}

	/**
	 * Stores a listener and returns the serial assigned to it. A named
	 * `ListenerPriority` is resolved to its integer value here, so every caller
	 * can accept either form.
	 */
	private function add(string $eventType, callable $listener, int|ListenerPriority $priority): int
	{
		$serial = $this->serial++;

		$this->listeners[$eventType][$serial] = [
			'callable' => $listener,
			'priority' => $priority instanceof ListenerPriority ? $priority->toInt() : $priority
		];

		return $serial;
	}

	/**
	 * Returns every key a listener may be registered against to match the given
	 * event: its class hierarchy (via `typesForClass()`) plus, for a
	 * `NamedEvent`, its event name.
	 *
	 * @return array<int, string>
	 */
	private function matchingTypes(object $event): array
	{
		$types = $this->typesForClass($event::class);

		// A named event contributes its name as an extra key, so listeners
		// registered against that string match alongside the class-based ones.
		if ($event instanceof NamedEvent) {
			$types[] = $event->eventName();
		}

		return $types;
	}

	/**
	 * Returns the keys a listener may be registered against to match the given
	 * type: the class itself plus every parent class and implemented interface.
	 * A string that is not a loaded class or interface (such as a named event's
	 * name) is treated as an opaque key with no hierarchy.
	 *
	 * @return array<int, string>
	 */
	private function typesForClass(string $class): array
	{
		if (! class_exists($class) && ! interface_exists($class)) {
			return [$class];
		}

		return [
			$class,
			...array_values(class_parents($class) ?: []),
			...array_values(class_implements($class) ?: [])
		];
	}
}
