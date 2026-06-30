<?php

/**
 * Hook listener provider class.
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
 * Bridges the event system to WordPress's action hooks. For each event it works
 * out a hook tag (the event's class name by default, or whatever the optional
 * closure returns) and, if anything is listening on that tag, yields a single
 * listener that fires the tag with `do_action()`, passing the event object
 * along. WordPress then runs every `add_action()` callback registered for the
 * tag, in its own priority order.
 *
 * The upshot: code anywhere in the WordPress ecosystem can react to a typed
 * event with the familiar `add_action()` it already knows, without depending on
 * this library at all. Because the event is an object, those callbacks receive
 * it by reference and may read or modify it like any other listener.
 *
 * This is the only WordPress-specific provider; the rest of the system has no
 * knowledge of WordPress.
 */
final class HookListenerProvider implements ListenerProvider
{
	/**
	 * Stores the closure that maps an event to the hook tag to fire for it.
	 * Returning an empty string opts the event out of the bridge.
	 *
	 * @var Closure(object): string
	 */
	private readonly Closure $resolveTag;

	/**
	 * Accepts the closure that returns the hook tag for a given event. When
	 * none is given, the event's fully-qualified class name is used as the tag,
	 * which is already unique and namespaced for correctly-namespaced code.
	 * Pass a closure to map events to custom tags or to opt an event out of the
	 * bridge by returning an empty string.
	 *
	 * @param ?Closure(object): string $resolveTag
	 */
	public function __construct(?Closure $resolveTag = null)
	{
		$this->resolveTag = $resolveTag
			?? static fn (object $event): string => $event::class;
	}

	/**
	 * @inheritDoc
	 */
	public function getListenersForEvent(object $event): iterable
	{
		$tag = ($this->resolveTag)($event);

		// Only bridge when a tag is given and something is actually listening
		// on it, so events with no hook callbacks cost nothing.
		if ($tag !== '' && has_action($tag)) {
			yield static function (object $event) use ($tag): void {
				do_action($tag, $event);
			};
		}
	}
}
