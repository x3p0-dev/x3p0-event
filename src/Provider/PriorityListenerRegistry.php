<?php

/**
 * Priority listener registry class.
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
use X3P0\Event\ListenerRegistry;

/**
 * In-memory, writable listener registry that runs listeners in priority order —
 * the provider you register listeners and subscribers on (hence `Registry`, not
 * `Provider`, unlike the read-only providers alongside it). The behavior lives
 * in the `RegistersListeners` trait; this class supplies the constructor.
 */
final class PriorityListenerRegistry implements ListenerProvider, ListenerRegistry
{
	use RegistersListeners;

	/**
	 * Sets up the registry, optionally with a resolver used to build listeners
	 * registered by class name (defaults to `new $class()`).
	 *
	 * @param ?Closure(class-string): object $resolver
	 */
	public function __construct(?Closure $resolver = null)
	{
		$this->initListenerRegistry($resolver);
	}
}
