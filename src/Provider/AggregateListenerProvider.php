<?php

/**
 * Aggregate listener provider class.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-event
 */

declare(strict_types=1);

namespace X3P0\Event\Provider;

use X3P0\Event\ListenerProvider;

/**
 * Combines several listener providers into one. For a given event it yields the
 * listeners from each child provider in turn, in the order the providers were
 * given. Ordering *within* a provider (such as priority) is that provider's own
 * concern; this class only concatenates, it does not re-sort across providers.
 *
 * This is the composition primitive of the system: pass it, say, the in-memory
 * `PriorityListenerProvider` together with a `HookListenerProvider`, and the
 * dispatcher sees a single provider that draws listeners from both sources.
 */
final class AggregateListenerProvider implements ListenerProvider
{
	/**
	 * Stores the child providers to draw listeners from, in order.
	 *
	 * @var array<int, ListenerProvider>
	 */
	private array $providers;

	/**
	 * Accepts the child providers to combine, in the order they should run.
	 */
	public function __construct(ListenerProvider ...$providers)
	{
		$this->providers = $providers;
	}

	/**
	 * @inheritDoc
	 */
	public function getListenersForEvent(object $event): iterable
	{
		foreach ($this->providers as $provider) {
			yield from $provider->getListenersForEvent($event);
		}
	}
}
