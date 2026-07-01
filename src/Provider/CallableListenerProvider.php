<?php

/**
 * Callable listener provider class.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-event
 */

declare(strict_types=1);

namespace X3P0\Event\Provider;

use Closure;
use X3P0\Event\ListenerProvider;

/**
 * Adapts any callable into a listener provider: the callable is handed the event
 * and returns the listeners for it. This is the read-only counterpart to
 * `PriorityListenerProvider` — where that one is a registry you write listeners
 * into, this one sources them from wherever the callable looks, so the listeners
 * live somewhere else entirely (a DI container tag, a config array, attribute
 * scanning, another event system).
 *
 * Because it holds no listeners of its own, it implements `ListenerProvider` but
 * not `ListenerRegistry`: there is nothing to register on. Ordering, type
 * matching, and priorities — if wanted — are the callable's concern; this class
 * only forwards. Combine it with other providers through
 * `AggregateListenerProvider`.
 */
final class CallableListenerProvider implements ListenerProvider
{
	/**
	 * Stores the callable that returns the listeners for a given event.
	 *
	 * @var Closure(object): iterable<callable>
	 */
	private readonly Closure $resolve;

	/**
	 * Accepts the callable that resolves listeners for an event. It receives
	 * the event and must return an iterable of callable listeners.
	 *
	 * @param callable(object): iterable<callable> $resolve
	 */
	public function __construct(callable $resolve)
	{
		$this->resolve = Closure::fromCallable($resolve);
	}

	/**
	 * @inheritDoc
	 */
	public function getListenersForEvent(object $event): iterable
	{
		return ($this->resolve)($event);
	}
}
